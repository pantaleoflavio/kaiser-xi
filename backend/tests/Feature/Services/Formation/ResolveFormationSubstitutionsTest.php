<?php

namespace Tests\Feature\Services\Formation;

use App\Enums\PlayerScoreStatus;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerScore;
use App\Models\PlayerSeasonRegistration;
use App\Models\SeasonClub;
use App\Services\Formation\ResolveFormationSubstitutions;
use App\Services\Formation\SaveFormationService;
use App\Services\League\LeagueSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResolveFormationSubstitutionsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, PlayerSeasonRegistration> */
    private array $registrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_all_playable_starters_are_unchanged(): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender']);
        $this->scoreAll($formation, [...$starters, ...$bench]);

        $result = $this->resolver()->resolve($formation);

        $this->assertSame([], $result->substitutions->all());
        $this->assertSame($starters, $result->effectiveStarters->pluck('fantasy_team_player_id')->all());
        $this->assertSame($bench, $result->unusedBench->pluck('fantasy_team_player_id')->all());
        $this->assertSame($formation->formation_module_id, $result->effectiveFormationModule->id);
    }

    #[DataProvider('unplayableStates')]
    public function test_missing_pending_and_did_not_play_starters_are_replaced(?PlayerScoreStatus $status): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender']);
        $this->scoreAll($formation, array_slice($starters, 1));
        $this->score($formation, $bench[0], PlayerScoreStatus::Confirmed, 5);
        if ($status !== null) {
            $this->score($formation, $starters[0], $status, $status === PlayerScoreStatus::Pending ? null : null);
        }

        $result = $this->resolver()->resolve($formation);

        $this->assertCount(1, $result->substitutions);
        $this->assertSame($starters[0], $result->substitutions->first()->outgoing->fantasy_team_player_id);
        $this->assertSame($bench[0], $result->substitutions->first()->incoming->fantasy_team_player_id);
    }

    /** @return iterable<string, array{?PlayerScoreStatus}> */
    public static function unplayableStates(): iterable
    {
        yield 'missing' => [null];
        yield 'pending' => [PlayerScoreStatus::Pending];
        yield 'did not play' => [PlayerScoreStatus::DidNotPlay];
    }

    public function test_confirmed_zero_is_playable(): void
    {
        [$formation, $starters] = $this->formation(['defender']);
        $this->scoreAll($formation, $starters);
        $this->score($formation, $starters[0], PlayerScoreStatus::Confirmed, 0);

        $result = $this->resolver()->resolve($formation);

        $this->assertCount(0, $result->substitutions);
        $this->assertCount(0, $result->unavailableStartersNotReplaced);
    }

    public function test_zero_maximum_reports_every_unavailable_starter(): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender']);
        $this->setting($formation->league, LeagueSetting::MAX_SUBSTITUTIONS, LeagueSetting::integerPayload(LeagueSetting::MAX_SUBSTITUTIONS, 0));
        $this->scoreAll($formation, [...array_slice($starters, 1), ...$bench]);

        $result = $this->resolver()->resolve($formation);

        $this->assertCount(0, $result->substitutions);
        $this->assertSame([$starters[0]], $result->unavailableStartersNotReplaced->pluck('fantasy_team_player_id')->all());
    }

    public function test_maximum_and_unique_bench_consumption_are_respected(): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender']);
        $this->setting($formation->league, LeagueSetting::MAX_SUBSTITUTIONS, LeagueSetting::integerPayload(LeagueSetting::MAX_SUBSTITUTIONS, 1));
        $this->scoreAll($formation, [...array_slice($starters, 2), ...$bench]);

        $result = $this->resolver()->resolve($formation);

        $this->assertCount(1, $result->substitutions);
        $this->assertSame([$starters[1]], $result->unavailableStartersNotReplaced->pluck('fantasy_team_player_id')->all());
        $this->assertSame([$bench[0]], $result->substitutions->pluck('incoming.fantasy_team_player_id')->all());
    }

    public function test_bench_order_skips_unplayable_and_never_optimizes_score(): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender', 'defender', 'defender']);
        $this->scoreAll($formation, array_slice($starters, 1));
        $this->score($formation, $bench[0], PlayerScoreStatus::Pending, null);
        $this->score($formation, $bench[1], PlayerScoreStatus::Confirmed, 1);
        $this->score($formation, $bench[2], PlayerScoreStatus::Confirmed, 10);

        $result = $this->resolver()->resolve($formation);

        $this->assertSame($bench[1], $result->substitutions->first()->incoming->fantasy_team_player_id);
        $this->assertSame(2, $result->substitutions->first()->incomingBenchPosition);
    }

    public function test_role_priority_prefers_later_same_role_and_respects_same_role_bench_order(): void
    {
        [$formation, $starters, $bench] = $this->formation(['forward', 'defender', 'defender']);
        $this->setting($formation->league, LeagueSetting::SUBSTITUTION_ORDER_MODE, LeagueSetting::stringPayload(LeagueSetting::SUBSTITUTION_ORDER_ROLE_PRIORITY));
        $this->scoreAll($formation, [...array_slice($starters, 1), ...$bench]);

        $result = $this->resolver()->resolve($formation);

        $this->assertSame($bench[1], $result->substitutions->first()->incoming->fantasy_team_player_id);
    }

    public function test_role_priority_without_same_role_is_unresolved_when_changes_are_disabled(): void
    {
        [$formation, $starters, $bench] = $this->formation(['midfielder']);
        $this->setting($formation->league, LeagueSetting::SUBSTITUTION_ORDER_MODE, LeagueSetting::stringPayload(LeagueSetting::SUBSTITUTION_ORDER_ROLE_PRIORITY));
        $this->scoreAll($formation, [...array_slice($starters, 1), ...$bench]);

        $result = $this->resolver()->resolve($formation);

        $this->assertCount(0, $result->substitutions);
        $this->assertSame([$starters[0]], $result->unavailableStartersNotReplaced->pluck('fantasy_team_player_id')->all());
    }

    public function test_allowed_database_module_change_succeeds_and_disallowed_one_does_not(): void
    {
        [$formation, $starters, $bench] = $this->formation(['midfielder']);
        $this->setting($formation->league, LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION, LeagueSetting::booleanPayload(true));
        $this->setting($formation->league, LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES, LeagueSetting::stringListPayload(['3-4-3', '4-3-3']));
        $this->scoreAll($formation, [...array_slice($starters, 1), ...$bench]);

        $result = $this->resolver()->resolve($formation);
        $this->assertSame('3-4-3', $result->effectiveFormationModule->name);

        $this->setting($formation->league, LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES, LeagueSetting::stringListPayload(['4-3-3']));
        $result = $this->resolver()->resolve($formation->fresh());
        $this->assertCount(0, $result->substitutions);
        $this->assertSame('4-3-3', $result->effectiveFormationModule->name);
    }

    public function test_original_module_is_preferred_deterministically_when_duplicate_requirements_match(): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender']);
        $duplicate = FormationModule::factory()->create(['name' => 'duplicate', 'is_active' => true]);
        foreach ($formation->formationModule->requirements as $requirement) {
            $duplicate->requirements()->create(['player_role_id' => $requirement->player_role_id, 'required_count' => $requirement->required_count]);
        }
        $this->setting($formation->league, LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION, LeagueSetting::booleanPayload(true));
        $this->setting($formation->league, LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES, LeagueSetting::stringListPayload(['duplicate', '4-3-3']));
        $this->scoreAll($formation, [...array_slice($starters, 1), ...$bench]);

        $first = $this->resolver()->resolve($formation);
        $second = $this->resolver()->resolve($formation->fresh());

        $this->assertSame('4-3-3', $first->effectiveFormationModule->name);
        $this->assertSame(
            $first->substitutions->map(fn ($substitution): array => [$substitution->outgoing->id, $substitution->incoming->id])->all(),
            $second->substitutions->map(fn ($substitution): array => [$substitution->outgoing->id, $substitution->incoming->id])->all(),
        );
    }

    public function test_released_historical_assignment_remains_eligible_and_resolution_is_side_effect_free(): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender'], true);
        $this->scoreAll($formation, [...array_slice($starters, 1), ...$bench]);
        FantasyTeamPlayer::query()->findOrFail($bench[0])->update(['released_at' => now()]);
        $beforeFormation = $formation->fresh()->getAttributes();
        $beforePlayers = $formation->players()->orderBy('id')->get()->map->getAttributes()->all();
        $beforeAssignments = FantasyTeamPlayer::query()->orderBy('id')->get()->map->getAttributes()->all();

        $result = $this->resolver()->resolve($formation->fresh());

        $this->assertSame($bench[0], $result->substitutions->first()->incoming->fantasy_team_player_id);
        $this->assertFalse($result->substitutions->first()->incoming->is_captain);
        $this->assertTrue($result->substitutions->first()->outgoing->is_captain);
        $this->assertSame($beforeFormation, $formation->fresh()->getAttributes());
        $this->assertSame($beforePlayers, $formation->players()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($beforeAssignments, FantasyTeamPlayer::query()->orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_unsubmitted_formation_is_rejected_as_programmer_misuse(): void
    {
        [$formation] = $this->formation([]);
        $formation->update(['is_confirmed' => false, 'submitted_at' => null]);

        $this->expectException(LogicException::class);
        $this->resolver()->resolve($formation);
    }

    /** @return array{Formation, list<int>, list<int>} */
    private function formation(array $benchRoles, bool $captain = false): array
    {
        $league = League::factory()->create();
        app(LeagueSettingsService::class)->initializeDefaults($league);
        if ($captain) {
            $this->setting($league, LeagueSetting::CAPTAIN_ENABLED, LeagueSetting::booleanPayload(true));
        }
        $team = FantasyTeam::factory()->create(['league_id' => $league->id]);
        $matchday = Matchday::factory()->create(['season_id' => $league->season_id, 'starts_at' => now()->addDay()]);
        $module = FormationModule::query()->with('requirements.playerRole')->where('name', '4-3-3')->firstOrFail();
        $starterRows = [];
        foreach ($module->requirements as $requirement) {
            for ($i = 0; $i < $requirement->required_count; $i++) {
                $starterRows[] = [
                    'id' => $this->assignment($league, $team, $requirement->playerRole->key)->id,
                    'role' => $requirement->playerRole->key,
                ];
            }
        }
        $starters = collect($starterRows)
            ->sortBy(fn (array $row): int => $row['role'] === 'defender' ? 0 : 1)
            ->pluck('id')
            ->all();
        $bench = array_map(fn (string $role): int => $this->assignment($league, $team, $role)->id, $benchRoles);
        $formation = app(SaveFormationService::class)->save($league, $matchday, $team, [
            'formation_module_id' => $module->id,
            'starters' => $starters,
            'bench' => collect($bench)->map(fn (int $id, int $index): array => ['fantasy_team_player_id' => $id, 'order' => $index + 1])->all(),
            'captain_fantasy_team_player_id' => $captain ? $starters[0] : null,
        ]);
        $formation->update(['is_confirmed' => true, 'submitted_at' => now()]);

        return [$formation->fresh(), $starters, $bench];
    }

    private function assignment(League $league, FantasyTeam $team, string $role): FantasyTeamPlayer
    {
        $player = Player::factory()->create();
        $registration = PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => SeasonClub::factory()->create(['season_id' => $league->season_id])->id,
            'player_role_id' => PlayerRole::query()->where('key', $role)->firstOrFail()->id,
        ]);
        $assignment = FantasyTeamPlayer::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
            'assigned_at' => now()->subDay(),
        ]);
        $this->registrations[$assignment->id] = $registration;

        return $assignment;
    }

    /** @param list<int> $assignments */
    private function scoreAll(Formation $formation, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $this->score($formation, $assignment, PlayerScoreStatus::Confirmed, 6);
        }
    }

    private function score(Formation $formation, int $assignment, PlayerScoreStatus $status, ?float $score): void
    {
        PlayerScore::query()->updateOrCreate(
            ['player_season_registration_id' => $this->registrations[$assignment]->id, 'matchday_id' => $formation->matchday_id],
            ['status' => $status, 'final_score' => $score],
        );
    }

    private function setting(League $league, string $key, array $value): void
    {
        $league->settings()->updateOrCreate(['key' => $key], ['value' => $value]);
        $league->unsetRelation('settings');
    }

    private function resolver(): ResolveFormationSubstitutions
    {
        return app(ResolveFormationSubstitutions::class);
    }
}
