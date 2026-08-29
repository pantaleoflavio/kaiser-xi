<?php

namespace Database\Seeders;

use App\Enums\PlayerScoreStatus;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\Matchday;
use App\Models\PlayerScore;
use App\Models\PlayerSeasonRegistration;
use App\Models\Season;
use App\Models\TeamMatchdayScore;
use App\Models\TeamMatchdayScoreDetail;
use App\Services\FantasyTeam\FantasyRosterService;
use Database\Seeders\Support\DemoFormationWriter;
use App\Services\League\InitializeClassicChampionship;
use App\Services\League\LeagueSettingsService;
use App\Services\Matchday\FinalizeMatchday;
use Database\Seeders\DemoExtendedPlayerPoolSeeder;
use Database\Seeders\DemoLeagueSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LogicException;

class DemoClassicChampionshipSeeder extends Seeder
{
    public const FIRST_MATCHDAY_NUMBER = 200;
    public const PAST_MATCHDAY_COUNT = 3;
    public const CURRENT_MATCHDAY_NUMBER = 203;
    public const MATCHDAY_COUNT = 8;
    public const MISSING_FORMATION_TEAM_SLUG = 'participant-7-fc';
    private const ROSTER_REQUIREMENTS = ['goalkeeper' => 2, 'defender' => 5, 'midfielder' => 5, 'forward' => 3];
    private const STARTER_REQUIREMENTS = ['goalkeeper' => 1, 'defender' => 4, 'midfielder' => 4, 'forward' => 2];

    public function __construct(
        private readonly LeagueSettingsService $settings,
        private readonly FantasyRosterService $rosters,
        private readonly InitializeClassicChampionship $initializer,
        private readonly DemoFormationWriter $formations,
        private readonly FinalizeMatchday $finalizer,
    ) {}

    public function run(): void
    {
        $previousTestNow = Carbon::getTestNow();

        try {
            $season = Season::query()->where('name', DemoLeagueSeeder::SEASON_NAME)->firstOrFail();
            $league = League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->firstOrFail();
            $teams = $league->fantasyTeams()->with('user')->orderBy('id')->get();
            $matchdays = $this->matchdays($season);

            $this->settings->update($league, [
                LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED => true,
                LeagueSetting::REAL_CAPTAIN_BONUS_POINTS => 0.5,
            ]);

            Carbon::setTestNow($matchdays->first()->starts_at->copy()->subMonth());
            $rosters = $this->rosters($season, $league, $teams);
            if (! $league->hasInitializedClassicChampionship()) {
                $this->initializer->handle($league, $matchdays->first()->id);
                $league->refresh();
            }

            $module = FormationModule::query()->where('name', '4-4-2')->firstOrFail();
            foreach ($matchdays->take(self::PAST_MATCHDAY_COUNT) as $matchdayIndex => $matchday) {
                if ($this->pastMatchdayIsComplete($league, $matchday, $teams, $matchdayIndex)) {
                    continue;
                }
                Carbon::setTestNow($matchday->starts_at->copy()->subDay());
                foreach ($teams as $teamIndex => $team) {
                    if ($matchdayIndex === 1 && $team->slug === self::MISSING_FORMATION_TEAM_SLUG) {
                        continue;
                    }

                    $formation = $this->saveFormation($league, $matchday, $team, $rosters[$team->id], $module);
                    $this->formations->submit($formation, $matchday);
                    $this->scores($season, $matchday, $formation, $teamIndex, $matchdayIndex);
                }

                Carbon::setTestNow($matchday->ends_at->copy()->addHour());
                $this->finalizer->finalize($matchday);
            }

            $current = $matchdays->firstWhere('number', self::CURRENT_MATCHDAY_NUMBER);
            Carbon::setTestNow($current->starts_at->copy()->subDay());
            if (! $this->submittedFormationExists($league, $current, $teams->first())) {
                $submitted = $this->saveFormation($league, $current, $teams->first(), $rosters[$teams->first()->id], $module);
                $this->formations->submit($submitted, $current);
            }
            if (! Formation::query()->whereBelongsTo($league)->whereBelongsTo($current)->whereBelongsTo($teams->get(1))->exists()) {
                $this->saveFormation($league, $current, $teams->get(1), $rosters[$teams->get(1)->id], $module);
            }
        } finally {
            Carbon::setTestNow($previousTestNow);
        }
    }

    /** @return Collection<int, Matchday> */
    private function matchdays(Season $season): Collection
    {
        return collect(range(0, self::MATCHDAY_COUNT - 1))->map(function (int $index) use ($season): Matchday {
            $startsAt = $index < self::PAST_MATCHDAY_COUNT
                ? Carbon::parse('2025-07-18 18:00:00')->addWeeks($index)
                : Carbon::parse('2099-06-01 18:00:00')->addWeeks($index - self::PAST_MATCHDAY_COUNT);
            $number = self::FIRST_MATCHDAY_NUMBER + $index;

            return Matchday::query()->updateOrCreate(
                ['season_id' => $season->id, 'number' => $number],
                [
                    'name' => 'Demo Classic Matchday ' . ($index + 1),
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addDay(),
                ],
            );
        });
    }

    /** @return array<int, array<string, Collection<int, FantasyTeamPlayer>>> */
    private function rosters(Season $season, League $league, Collection $teams): array
    {
        $pool = PlayerSeasonRegistration::query()->activeForSeason($season->id)
            ->whereHas('player', fn($query) => $query
                ->whereNotIn('slug', collect(DemoExtendedPlayerPoolSeeder::FREE_AGENTS)->pluck(1))
                ->where('slug', '!=', 'demo-carlo-cielo'))
            ->with(['player', 'playerRole'])->orderBy('id')->get()->groupBy('playerRole.key');
        $result = [];
        $commissioner = $teams->first()->user;
        $assignedPlayerIds = FantasyTeamPlayer::query()->active()->where('league_id', $league->id)
            ->pluck('player_id')->flip();

        foreach ($teams as $team) {
            foreach (self::ROSTER_REQUIREMENTS as $role => $count) {
                $existing = FantasyTeamPlayer::query()->active()->where('league_id', $league->id)
                    ->where('fantasy_team_id', $team->id)->whereHas('player.playerSeasonRegistrations', fn($query) => $query
                        ->activeForSeason($season->id)->whereHas('playerRole', fn($roleQuery) => $roleQuery->where('key', $role)))
                    ->orderBy('id')->get();
                $needed = max(0, $count - $existing->count());
                $available = $pool[$role]->reject(fn($registration) => $assignedPlayerIds->has($registration->player_id))->take($needed);
                $added = $available->map(function ($registration) use ($league, $team, $commissioner, $assignedPlayerIds) {
                    $assignment = $this->rosters->assign($league, $team, $registration->player, $commissioner, 10);
                    $assignedPlayerIds->put($registration->player_id, true);

                    return $assignment;
                });
                $result[$team->id][$role] = $existing->concat($added)->take($count)->values();
                $this->assertRosterRoleCount($league, $team, $role, $count, $result[$team->id][$role]->count());
            }
        }

        return $result;
    }

    private function assertRosterRoleCount(League $league, FantasyTeam $team, string $role, int $expected, int $actual): void
    {
        if ($actual !== $expected) {
            throw new LogicException("Demo roster invariant failed for league {$league->slug}, team {$team->slug}, role {$role}: expected {$expected}, got {$actual}.");
        }
    }

    /** @param Collection<int, FantasyTeam> $teams */
    private function pastMatchdayIsComplete(League $league, Matchday $matchday, Collection $teams, int $matchdayIndex): bool
    {
        $expected = $teams->count() - ($matchdayIndex === 1 ? 1 : 0);
        $formations = Formation::query()->whereBelongsTo($league)->whereBelongsTo($matchday)
            ->whereNotNull('submitted_at')->where('is_confirmed', true)->get();

        if (
            $formations->count() !== $expected
            || $formations->contains(fn(Formation $formation): bool => $formation->players()->count() !== 15)
            || TeamMatchdayScore::query()->whereBelongsTo($league)->whereBelongsTo($matchday)->count() !== $expected
            || TeamMatchdayScoreDetail::query()->whereIn('team_matchday_score_id', TeamMatchdayScore::query()
                ->whereBelongsTo($league)->whereBelongsTo($matchday)->pluck('id'))->count() !== ($expected * 15)
            || PlayerScore::query()->where('matchday_id', $matchday->id)
            ->where('status', PlayerScoreStatus::Confirmed)->count() !== ($expected * 15)
        ) {
            return false;
        }

        return $league->classicParticipants()->count() === $teams->count()
            && $league->standings()->count() === $teams->count();
    }

    private function submittedFormationExists(League $league, Matchday $matchday, FantasyTeam $team): bool
    {
        return Formation::query()->whereBelongsTo($league)->whereBelongsTo($matchday)->whereBelongsTo($team)
            ->whereNotNull('submitted_at')->where('is_confirmed', true)->exists();
    }

    /** @param array<string, Collection<int, FantasyTeamPlayer>> $roster */
    private function saveFormation(League $league, Matchday $matchday, FantasyTeam $team, array $roster, FormationModule $module): Formation
    {
        $starters = collect(self::STARTER_REQUIREMENTS)->flatMap(
            fn(int $count, string $role) => $roster[$role]->take($count)->pluck('id')
        )->values()->all();
        $bench = collect(array_keys(self::ROSTER_REQUIREMENTS))->map(fn(string $role, int $index): array => [
            'fantasy_team_player_id' => $roster[$role]->last()->id,
            'order' => $index + 1,
        ])->all();

        return $this->formations->save($league, $matchday, $team, [
            'formation_module_id' => $module->id,
            'starters' => $starters,
            'bench' => $bench,
        ]);
    }

    private function scores(
        Season $season,
        Matchday $matchday,
        Formation $formation,
        int $teamIndex,
        int $matchdayIndex
    ): void {
        $value = 7.5 - (0.5 * (($teamIndex + $matchdayIndex) % 5));

        $players = $formation->players()
            ->orderBy('slot_type', 'desc')
            ->orderBy('position_index')
            ->orderBy('id')
            ->get();

        $captainPlayerId = $teamIndex === 0 && $matchdayIndex === 0
            ? $players
            ->firstWhere('slot_type', 'starter')
            ?->player_id
            : null;

        foreach ($players as $formationPlayer) {
            $registration = PlayerSeasonRegistration::query()
                ->where('player_id', $formationPlayer->player_id)
                ->activeForSeason($season->id)
                ->firstOrFail();

            PlayerScore::query()->updateOrCreate(
                [
                    'player_season_registration_id' => $registration->id,
                    'matchday_id' => $matchday->id,
                ],
                [
                    'base_rating' => $value,
                    'final_score' => $value,
                    'status' => PlayerScoreStatus::Confirmed,
                    'is_captain' => $formationPlayer->player_id === $captainPlayerId,
                ],
            );
        }
    }
}
