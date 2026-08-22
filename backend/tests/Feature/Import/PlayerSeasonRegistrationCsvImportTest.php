<?php

namespace Tests\Feature\Import;

use App\Enums\CsvImportType;
use App\Enums\ImportStatus;
use App\Jobs\ExecuteCsvImportJob;
use App\Models\Player;
use App\Models\PlayerExternalIdentity;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClub;
use App\Models\RealClubExternalIdentity;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Models\User;
use App\Services\Import\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerSeasonRegistrationCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_registration_without_writing_during_analysis(): void
    {
        $this->fixture();
        $analysis = $this->analyse($this->csv('Serie A', 'opta,Registration-1,forward,9,2026-08-20T12:00:00+02:00,,25.50,true'));

        $this->assertFalse($analysis['has_errors']);
        $this->assertSame(1, $analysis['counts']['create']);
        $this->assertSame(0, PlayerSeasonRegistration::count());

        $this->service()->importer(CsvImportType::PlayerSeasonRegistrations)->execute($analysis);
        $registration = PlayerSeasonRegistration::firstOrFail();
        $this->assertSame('25.50', $registration->quotation);
        $this->assertSame('opta', $registration->external_provider);
        $this->assertSame('forward', $registration->playerRole->key);
        $this->assertSame('10:00', $registration->registered_at->format('H:i'));
    }

    public function test_it_updates_and_then_classifies_the_same_values_as_unchanged(): void
    {
        $fixture = $this->fixture();
        $registration = PlayerSeasonRegistration::factory()->create(['player_id' => $fixture['player']->id, 'season_club_id' => $fixture['seasonClub']->id, 'player_role_id' => $fixture['role']->id, 'quotation' => 10, 'shirt_number' => 4]);
        $analysis = $this->analyse($this->csv('serie-a', ',,forward,8,,,30.25,false'));
        $this->assertSame(['quotation', 'shirt_number', 'is_active'], $analysis['rows'][0]['changes']);
        $this->service()->importer(CsvImportType::PlayerSeasonRegistrations)->execute($analysis);
        $this->assertSame('30.25', $registration->refresh()->quotation);
        $this->assertFalse($registration->is_active);

        $unchanged = $this->analyse($this->csv('serie_a', ',,forward,8,,,30.25,false'));
        $this->assertSame(1, $unchanged['counts']['unchanged']);
    }

    public function test_preferred_direct_identity_updates_the_same_natural_registration(): void
    {
        $fixture = $this->fixture();
        $registration = PlayerSeasonRegistration::factory()->create([
            'player_id' => $fixture['player']->id,
            'season_club_id' => $fixture['seasonClub']->id,
            'player_role_id' => $fixture['role']->id,
            'external_provider' => 'feed',
            'external_id' => 'Registration-1',
            'shirt_number' => 4,
        ]);

        $analysis = $this->analyse($this->csv('serie_a', ' FEED ,Registration-1,,8,,,,'));

        $this->assertSame('update', $analysis['rows'][0]['action']);
        $this->assertSame($registration->id, $analysis['rows'][0]['model_id']);
        $this->assertSame(['shirt_number'], $analysis['rows'][0]['changes']);
    }

    public function test_partial_direct_identity_pair_is_rejected(): void
    {
        $this->fixture();

        $this->assertErrorContains(
            $this->analyse($this->csv('serie_a', 'opta,,forward,,,,,')),
            'must be supplied together',
        );
    }

    public function test_empty_optional_cells_preserve_values(): void
    {
        $fixture = $this->fixture();
        $registration = PlayerSeasonRegistration::factory()->create(['player_id' => $fixture['player']->id, 'season_club_id' => $fixture['seasonClub']->id, 'player_role_id' => $fixture['role']->id, 'quotation' => 42.75, 'shirt_number' => 7, 'registered_at' => '2026-07-01 10:00:00', 'released_at' => '2026-08-01 10:00:00', 'is_active' => false]);
        $analysis = $this->analyse($this->csv('serie_a', ',,,,,,,'));
        $this->assertSame(1, $analysis['counts']['unchanged']);
        $this->service()->importer(CsvImportType::PlayerSeasonRegistrations)->execute($analysis);
        $this->assertSame('42.75', $registration->refresh()->quotation);
        $this->assertSame(7, $registration->shirt_number);
        $this->assertFalse($registration->is_active);
        $this->assertSame('2026-07-01 10:00:00', $registration->registered_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-01 10:00:00', $registration->released_at->format('Y-m-d H:i:s'));
        $this->assertSame($fixture['role']->id, $registration->player_role_id);
    }

    public function test_it_reports_unknown_competition_season_player_club_and_role_dependencies(): void
    {
        $fixture = $this->fixture();
        $this->assertErrorContains($this->analyse($this->csv('unknown')), 'Unknown competition_code.');
        $this->assertErrorContains($this->analyse(str_replace('2026/27', '2025/26', $this->csv('serie_a'))), 'Unknown season.');
        $this->assertErrorContains($this->analyse(str_replace('Player-1', 'Missing', $this->csv('serie_a'))), 'Unknown player external identity.');
        $this->assertErrorContains($this->analyse(str_replace('Club-1', 'Missing', $this->csv('serie_a'))), 'Unknown club external identity.');
        $this->assertErrorContains($this->analyse($this->csv('serie_a', ',,unknown,,,,,')), 'Unknown player_role key.');
        $this->assertSame(0, PlayerSeasonRegistration::count());
        $this->assertNotNull($fixture);
    }

    public function test_it_rejects_a_global_club_that_is_not_in_the_season(): void
    {
        $this->fixture();
        $otherClub = RealClub::factory()->create();
        RealClubExternalIdentity::factory()->create(['real_club_id' => $otherClub->id, 'provider' => 'opta', 'external_id' => 'Other-Club']);
        $this->assertErrorContains($this->analyse(str_replace('Club-1', 'Other-Club', $this->csv('serie_a'))), 'not registered for the target season');
    }

    public function test_it_reports_duplicate_natural_identity_inside_the_csv(): void
    {
        $fixture = $this->fixture();
        PlayerExternalIdentity::factory()->create(['player_id' => $fixture['player']->id, 'provider' => 'stats', 'external_id' => 'Player-Alt']);
        $csv = $this->csv('serie_a');
        $header = strtok($csv, "\n");
        $row = substr($csv, strpos($csv, "\n") + 1);
        $alternateIdentityRow = str_replace(' OPTA ,Player-1', ' stats ,Player-Alt', $row);

        $analysis = $this->analyse($header . "\n" . $row . $alternateIdentityRow);

        $this->assertSame(2, $analysis['counts']['errors']);
        $this->assertStringContainsString('CSV row 3', $analysis['rows'][0]['errors'][0]);
    }

    public function test_it_reports_duplicate_direct_identity_inside_the_csv(): void
    {
        $this->fixture();
        $player = Player::factory()->create();
        PlayerExternalIdentity::factory()->create(['player_id' => $player->id, 'provider' => 'opta', 'external_id' => 'Player-2']);
        $first = $this->csv('serie_a', 'opta,Registration-1,forward,,,,,');
        $header = strtok($first, "\n");
        $firstRow = substr($first, strpos($first, "\n") + 1);
        $secondRow = str_replace('Player-1', 'Player-2', $firstRow);

        $analysis = $this->analyse($header . "\n" . $firstRow . $secondRow);

        $this->assertSame(2, $analysis['counts']['errors']);
        $this->assertStringContainsString('same registration provider identity', $analysis['rows'][0]['errors'][0]);
    }

    public function test_direct_identity_must_agree_with_the_natural_identity(): void
    {
        $fixture = $this->fixture();
        $natural = PlayerSeasonRegistration::factory()->create([
            'player_id' => $fixture['player']->id,
            'season_club_id' => $fixture['seasonClub']->id,
            'player_role_id' => $fixture['role']->id,
        ]);
        $other = PlayerSeasonRegistration::factory()->create(['external_provider' => 'opta', 'external_id' => 'Registration-1']);
        $analysis = $this->analyse($this->csv('serie_a', 'opta,Registration-1,forward,,,,,'));
        $this->assertErrorContains($analysis, 'conflicts with the resolved player');
        $this->assertDatabaseHas('player_season_registrations', ['id' => $natural->id]);
        $this->assertDatabaseHas('player_season_registrations', ['id' => $other->id]);
    }

    public function test_it_preserves_released_history_and_rejects_an_ambiguous_active_transfer(): void
    {
        $fixture = $this->fixture();
        $oldClub = SeasonClub::factory()->create(['season_id' => $fixture['season']->id]);
        $old = PlayerSeasonRegistration::factory()->create(['player_id' => $fixture['player']->id, 'season_club_id' => $oldClub->id, 'player_role_id' => $fixture['role']->id, 'is_active' => true, 'released_at' => null]);
        $this->assertErrorContains($this->analyse($this->csv('serie_a')), 'another active registration');
        $old->update(['released_at' => now(), 'is_active' => false]);

        $analysis = $this->analyse($this->csv('serie_a'));
        $this->assertSame(1, $analysis['counts']['create']);
        $this->service()->importer(CsvImportType::PlayerSeasonRegistrations)->execute($analysis);
        $this->assertSame(2, PlayerSeasonRegistration::count());
        $this->assertNotNull($old->refresh()->released_at);
    }

    public function test_released_registration_does_not_block_a_new_registration(): void
    {
        $this->assertNonActiveRegistrationDoesNotBlock(['is_active' => true, 'released_at' => now()]);
    }

    public function test_inactive_registration_does_not_block_a_new_registration(): void
    {
        $this->assertNonActiveRegistrationDoesNotBlock(['is_active' => false, 'released_at' => null]);
    }

    public function test_updating_the_same_active_natural_registration_is_not_blocked(): void
    {
        $fixture = $this->fixture();
        $registration = PlayerSeasonRegistration::factory()->create([
            'player_id' => $fixture['player']->id,
            'season_club_id' => $fixture['seasonClub']->id,
            'player_role_id' => $fixture['role']->id,
            'shirt_number' => 9,
            'is_active' => true,
            'released_at' => null,
        ]);

        $analysis = $this->analyse($this->csv('serie_a', ',,forward,12,,,,true'));

        $this->assertSame('update', $analysis['rows'][0]['action']);
        $this->assertSame($registration->id, $analysis['rows'][0]['model_id']);
        $this->assertFalse($analysis['has_errors']);
    }

    public function test_existing_registration_can_be_explicitly_released_without_hidden_coupling(): void
    {
        $fixture = $this->fixture();
        $registration = PlayerSeasonRegistration::factory()->create([
            'player_id' => $fixture['player']->id,
            'season_club_id' => $fixture['seasonClub']->id,
            'player_role_id' => $fixture['role']->id,
            'is_active' => true,
            'released_at' => null,
        ]);
        $analysis = $this->analyse($this->csv('serie_a', ',,,,,2026-09-01T12:00:00+02:00,,false'));

        $this->service()->importer(CsvImportType::PlayerSeasonRegistrations)->execute($analysis);

        $this->assertFalse($registration->refresh()->is_active);
        $this->assertSame('2026-09-01 10:00:00', $registration->released_at->format('Y-m-d H:i:s'));
    }

    public function test_quotation_equivalent_text_is_unchanged_and_external_ids_remain_opaque(): void
    {
        $fixture = $this->fixture();
        PlayerSeasonRegistration::factory()->create([
            'player_id' => $fixture['player']->id,
            'season_club_id' => $fixture['seasonClub']->id,
            'player_role_id' => $fixture['role']->id,
            'quotation' => '10.00',
        ]);

        $analysis = $this->analyse($this->csv('serie_a', ',,,,,,10,'));

        $this->assertSame('unchanged', $analysis['rows'][0]['action']);
        $this->assertSame('unchanged', $this->analyse($this->csv('serie_a', ',,,,,,10.0,'))['rows'][0]['action']);
        $this->assertErrorContains(
            $this->analyse(str_replace('Player-1', 'player-1', $this->csv('serie_a'))),
            'Unknown player external identity',
        );
    }

    public function test_execution_rejects_stale_dependency_or_identity_state(): void
    {
        $fixture = $this->fixture();
        $analysis = $this->analyse($this->csv('serie_a'));
        PlayerExternalIdentity::where('player_id', $fixture['player']->id)->update(['external_id' => 'Changed']);
        $this->expectExceptionMessage('dependencies changed since analysis');
        $this->service()->importer(CsvImportType::PlayerSeasonRegistrations)->execute($analysis);
    }

    public function test_execution_rejects_a_player_role_that_changed_after_analysis(): void
    {
        $this->fixture();
        $role = PlayerRole::create(['key' => 'temporary_role', 'label' => 'Temporary role', 'sort_order' => 99]);
        $analysis = $this->analyse($this->csv('serie_a', ',,temporary_role,,,,,'));
        $role->delete();

        $this->expectExceptionMessage('dependencies changed since analysis');
        $this->service()->importer(CsvImportType::PlayerSeasonRegistrations)->execute($analysis);
    }

    public function test_queued_execution_persists_once_and_repeated_job_execution_is_safe(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->fixture();
        $service = $this->service();
        $contents = $this->csv('serie_a');
        $import = $service->createHistory(CsvImportType::PlayerSeasonRegistrations, 'registrations.csv', $contents, User::factory()->create()->id);

        $this->assertTrue($service->queue($import));
        $job = new ExecuteCsvImportJob($import->id);
        $job->handle($service);
        $job->handle($service);

        $this->assertSame(1, PlayerSeasonRegistration::count());
        $this->assertSame(ImportStatus::Completed, $import->refresh()->status);
    }

    private function fixture(): array
    {
        $competition = RealCompetition::factory()->create(['code' => 'serie_a']);
        $season = Season::factory()->create(['real_competition_id' => $competition->id, 'name' => '2026/27']);
        $player = Player::factory()->create();
        PlayerExternalIdentity::factory()->create(['player_id' => $player->id, 'provider' => 'opta', 'external_id' => 'Player-1']);
        $club = RealClub::factory()->create();
        RealClubExternalIdentity::factory()->create(['real_club_id' => $club->id, 'provider' => 'opta', 'external_id' => 'Club-1']);
        $seasonClub = SeasonClub::factory()->create(['season_id' => $season->id, 'real_club_id' => $club->id]);
        $role = PlayerRole::query()->firstOrCreate(['key' => 'forward'], ['label' => 'Forward', 'sort_order' => 1]);
        return compact('season', 'player', 'seasonClub', 'role');
    }

    private function assertNonActiveRegistrationDoesNotBlock(array $state): void
    {
        $fixture = $this->fixture();
        $oldClub = SeasonClub::factory()->create(['season_id' => $fixture['season']->id]);
        PlayerSeasonRegistration::factory()->create($state + [
            'player_id' => $fixture['player']->id,
            'season_club_id' => $oldClub->id,
            'player_role_id' => $fixture['role']->id,
        ]);

        $analysis = $this->analyse($this->csv('serie_a'));

        $this->assertSame('create', $analysis['rows'][0]['action']);
        $this->assertFalse($analysis['has_errors']);
    }

    private function csv(string $competition, string $tail = ',,forward,,,,,'): string
    {
        return "competition_code,season_name,player_provider,player_external_id,club_provider,club_external_id,registration_provider,registration_external_id,player_role,shirt_number,registered_at,released_at,quotation,is_active\n{$competition},2026/27, OPTA ,Player-1, OPTA ,Club-1,{$tail}\n";
    }

    private function analyse(string $csv): array
    {
        return $this->service()->analyse(CsvImportType::PlayerSeasonRegistrations, $csv);
    }
    private function service(): CsvImportService
    {
        return app(CsvImportService::class);
    }
    private function assertErrorContains(array $analysis, string $message): void
    {
        $this->assertStringContainsString($message, implode(' ', $analysis['rows'][0]['errors']));
    }
}
