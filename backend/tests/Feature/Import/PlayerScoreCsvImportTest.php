<?php

namespace Tests\Feature\Import;

use App\Enums\CsvImportType;
use App\Enums\PlayerScoreStatus;
use App\Jobs\ExecuteCsvImportJob;
use App\Models\User;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\PlayerExternalIdentity;
use App\Models\PlayerScore;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClub;
use App\Models\RealClubExternalIdentity;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Services\Import\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PlayerScoreCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_confirmed_score_by_direct_identity_without_writing_during_analysis(): void
    {
        $this->fixture();

        $analysis = $this->analyse(
            $this->row(
                status: 'confirmed',
                finalScore: '10.00',
                extras: '7.25,,,,,,,,,,,',
            ),
        );

        $this->assertFalse($analysis['has_errors']);
        $this->assertSame(1, $analysis['counts']['create']);
        $this->assertSame(0, PlayerScore::count());

        $this->execute($analysis);

        $score = PlayerScore::firstOrFail();

        $this->assertSame(PlayerScoreStatus::Confirmed, $score->status);
        $this->assertSame('10.00', $score->final_score);
    }

    public function test_it_creates_using_fallback_identity_and_normalizes_did_not_play(): void
    {
        $this->fixture();
        $analysis = $this->analyse($this->row(direct: false, status: 'did_not_play', finalScore: '12', extras: '7.5,2,3,1,1,1,1,1,1,4,true,true'));
        $this->execute($analysis);
        $score = PlayerScore::firstOrFail();

        $this->assertNull($score->base_rating);
        $this->assertNull($score->final_score);
        foreach (\App\Services\PlayerScore\PlayerScoreService::EVENT_FIELDS as $field) $this->assertSame(0, $score->{$field});
        $this->assertFalse($score->clean_sheet);
        $this->assertFalse($score->is_captain);
    }

    public function test_update_preserves_empty_fields_and_decimal_equality_is_unchanged(): void
    {
        $fixture = $this->fixture();
        PlayerScore::factory()->create(['player_season_registration_id' => $fixture['registration']->id, 'matchday_id' => $fixture['matchday']->id, 'status' => 'pending', 'base_rating' => '10.00', 'goals' => 3, 'clean_sheet' => true]);

        $analysis = $this->analyse($this->row(status: 'pending', finalScore: '', extras: '10.0,,,,,,,,,,,'));

        $this->assertSame(1, $analysis['counts']['unchanged']);
        $this->execute($analysis);
        $score = PlayerScore::firstOrFail();
        $this->assertSame(3, $score->goals);
        $this->assertTrue($score->clean_sheet);
    }

    public function test_direct_and_fallback_conflict_is_rejected(): void
    {
        $fixture = $this->fixture();
        $other = PlayerSeasonRegistration::factory()->create([
            'external_provider' => 'opta',
            'external_id' => 'Other',
        ]);
        $csv = str_replace('Registration-1', 'Other', $this->row(direct: true, fallback: true));

        $analysis = $this->analyse($csv);

        $this->assertTrue($analysis['has_errors']);
        $this->assertStringContainsString('conflicts', implode(' ', $analysis['rows'][0]['errors']));
        $this->assertNotSame($fixture['registration']->id, $other->id);
    }

    public function test_duplicate_resolved_identity_marks_every_row_as_error(): void
    {
        $this->fixture();
        $csv = $this->row();
        $csv .= substr($csv, strpos($csv, "\n") + 1);

        $analysis = $this->analyse($csv);

        $this->assertSame(2, $analysis['counts']['errors']);
    }

    public function test_confirmed_without_base_rating_and_invalid_scalar_values_are_rejected(): void
    {
        $this->fixture();

        $this->assertError(
            $this->row(status: 'confirmed', finalScore: ''),
            __('admin.validation.player_scores.confirmed_base_rating_required'),
        );

        $this->assertError($this->row(status: 'invalid'), 'status');
        $this->assertError($this->row(extras: ',1.5,,,,,,,,,,'), 'integer');
        $this->assertError($this->row(extras: ',,,,,,,,,,yes,'), 'clean_sheet');
        $this->assertError($this->row(extras: '100.00,,,,,,,,,,,'), 'base rating');
    }

    public function test_execution_rejects_stale_registration_identity_without_writing(): void
    {
        $fixture = $this->fixture();
        $analysis = $this->analyse($this->row());
        $fixture['registration']->update(['external_id' => 'Remapped']);

        $this->expectException(RuntimeException::class);
        try {
            $this->execute($analysis);
        } finally {
            $this->assertSame(0, PlayerScore::count());
        }
    }

    public function test_unknown_player_between_valid_players_is_skipped_and_reported(): void
    {
        $fixture = $this->fixture();
        $second = Player::factory()->create();
        PlayerExternalIdentity::factory()->create(['player_id' => $second->id, 'provider' => 'opta', 'external_id' => 'Player-2']);
        PlayerSeasonRegistration::factory()->create(['player_id' => $second->id, 'season_club_id' => $fixture['seasonClub']->id]);
        $csv = $this->combineRows($this->row(direct: false), str_replace('Player-1', 'Missing-Player', $this->row(direct: false)), str_replace('Player-1', 'Player-2', $this->row(direct: false)));

        $analysis = $this->analyse($csv);

        $this->assertFalse($analysis['has_errors']);
        $this->assertSame(['create', 'unmatched', 'create'], array_column($analysis['rows'], 'action'));
        $this->assertSame(1, $analysis['counts']['unmatched']);
        $this->assertSame('Missing-Player', $analysis['rows'][1]['data']['player_external_id']);
        $this->execute($analysis);
        $this->assertSame(2, PlayerScore::count());
        $this->assertSame(2, Player::count());
    }

    public function test_repeated_unknown_player_rows_remain_separate_diagnostics(): void
    {
        $this->fixture();
        $unknown = str_replace('Player-1', 'Missing-Player', $this->row(direct: false));
        $analysis = $this->analyse($this->combineRows($unknown, $unknown));

        $this->assertFalse($analysis['has_errors']);
        $this->assertSame(2, $analysis['counts']['unmatched']);
        $this->assertSame([2, 3], array_column($analysis['rows'], 'row_number'));
        $this->execute($analysis);
        $this->assertSame(0, PlayerScore::count());
        $this->assertSame(1, Player::count());
    }

    public function test_unknown_player_does_not_hide_invalid_score_or_unrelated_reference(): void
    {
        $this->fixture();
        $unknown = str_replace('Player-1', 'Missing-Player', $this->row(direct: false, status: 'invalid'));
        $this->assertError($unknown, 'status');
        $unknownClub = str_replace(['Player-1', 'Club-1'], ['Missing-Player', 'Missing-Club'], $this->row(direct: false));
        $this->assertError($unknownClub, 'Unknown RealClub external identity');
    }

    public function test_queued_execution_persists_unmatched_rows_and_excludes_them_from_successes(): void
    {
        $this->fixture();
        $csv = $this->combineRows($this->row(direct: false), str_replace('Player-1', 'Missing-Player', $this->row(direct: false)));
        $service = app(CsvImportService::class);
        $import = $service->createHistory(CsvImportType::PlayerScores, 'scores.csv', $csv, User::factory()->create()->id);
        $service->queue($import);

        (new ExecuteCsvImportJob($import->id))->handle($service);

        $import->refresh();
        $this->assertSame(1, PlayerScore::count());
        $this->assertSame(1, Player::count());
        $this->assertSame(2, $import->total_rows);
        $this->assertSame(1, $import->successful_rows);
        $this->assertSame(0, $import->failed_rows);
        $this->assertSame('Missing-Player', $import->unmatchedRows()->sole()->row_data['player_external_id']);
    }

    private function combineRows(string ...$csvs): string
    {
        $header = strtok($csvs[0], "\n");
        $rows = array_map(fn(string $csv): string => substr($csv, strpos($csv, "\n") + 1), $csvs);

        return $header . "\n" . implode('', $rows);
    }

    private function fixture(): array
    {
        $competition = RealCompetition::factory()->create(['code' => 'serie_a']);
        $season = Season::factory()->create(['real_competition_id' => $competition->id, 'name' => '2026/27']);
        $club = RealClub::factory()->create();
        $seasonClub = SeasonClub::factory()->create(['season_id' => $season->id, 'real_club_id' => $club->id]);
        $player = Player::factory()->create();
        PlayerExternalIdentity::factory()->create(['player_id' => $player->id, 'provider' => 'opta', 'external_id' => 'Player-1']);
        RealClubExternalIdentity::factory()->create(['real_club_id' => $club->id, 'provider' => 'opta', 'external_id' => 'Club-1']);
        $registration = PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => $seasonClub->id, 'external_provider' => 'opta', 'external_id' => 'Registration-1']);
        $matchday = Matchday::factory()->create(['season_id' => $season->id, 'number' => 1]);

        return compact('competition', 'season', 'club', 'seasonClub', 'player', 'registration', 'matchday');
    }

    private function row(bool $direct = true, bool $fallback = false, string $status = 'pending', string $finalScore = '', string $extras = ''): string
    {
        $header = 'competition_code,season_name,matchday_number,registration_provider,registration_external_id,player_provider,player_external_id,club_provider,club_external_id,status,base_rating,goals,assists,yellow_cards,red_cards,own_goals,penalties_scored,penalties_missed,penalties_saved,goals_conceded,clean_sheet,is_captain,final_score';
        $identity = ($direct ? ' OPTA ,Registration-1' : ',') . ',' . ($fallback || ! $direct ? 'opta,Player-1,opta,Club-1' : ',,,');
        $extras = $extras !== '' ? $extras : str_repeat(',', 11);

        return $header . "\nserie-a,2026/27,1,$identity,$status,$extras,$finalScore\n";
    }

    private function analyse(string $csv): array
    {
        return app(CsvImportService::class)->analyse(CsvImportType::PlayerScores, $csv);
    }

    private function execute(array $analysis): void
    {
        app(CsvImportService::class)->importer(CsvImportType::PlayerScores)->execute($analysis);
    }

    private function assertError(string $csv, string $message): void
    {
        $analysis = $this->analyse($csv);

        $this->assertTrue($analysis['has_errors']);

        $this->assertStringContainsString(
            $message,
            implode(' ', $analysis['rows'][0]['errors']),
        );
    }
}
