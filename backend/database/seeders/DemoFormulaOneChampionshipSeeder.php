<?php

namespace Database\Seeders;

use App\Enums\PlayerScoreStatus;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\PlayerScore;
use App\Models\PlayerSeasonRegistration;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Models\Standing;
use App\Models\TeamMatchdayScore;
use App\Models\TeamMatchdayScoreDetail;
use App\Models\User;
use App\Services\FantasyTeam\FantasyRosterService;
use Database\Seeders\Support\DemoFormationWriter;
use App\Services\League\InitializeChampionship;
use App\Services\League\LeagueSettingsService;
use App\Services\Matchday\FinalizeMatchday;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LogicException;

class DemoFormulaOneChampionshipSeeder extends Seeder
{
    public const LEAGUE_SLUG = 'demo-formula-one';
    public const LEAGUE_NAME = 'Demo Formula One Championship';
    public const SEASON_NAME = '2025/2026 Formula One Demo';
    public const FIRST_MATCHDAY_NUMBER = 300;
    public const CURRENT_MATCHDAY_NUMBER = 303;
    public const MATCHDAY_COUNT = 8;
    public const PAST_MATCHDAY_COUNT = 3;
    public const MISSING_FORMATION_TEAM_INDEX = 5;

    public const TEAMS = [
        ['demo.commissioner@example.com', 'commissioner', 'Formula Red Racing', 'formula-red-racing'],
        ['demo.cocommissioner@example.com', 'co_commissioner', 'Formula Blue Racing', 'formula-blue-racing'],
        ['demo.participant1@example.com', 'participant', 'Formula Gold Racing', 'formula-gold-racing'],
        ['demo.participant2@example.com', 'participant', 'Formula Green Racing', 'formula-green-racing'],
        ['demo.participant3@example.com', 'participant', 'Formula Purple Racing', 'formula-purple-racing'],
        ['demo.participant4@example.com', 'participant', 'Formula Silver Racing', 'formula-silver-racing'],
    ];

    public const SCORE_MATRIX = [
        [82, 78, 75, 72, 69, 66],
        [74, 80, 77, 70, 68, 0],
        [76, 76, 79, 71, 65, 67],
    ];

    private const ROSTER_REQUIREMENTS = ['goalkeeper' => 2, 'defender' => 5, 'midfielder' => 5, 'forward' => 3];
    private const STARTER_REQUIREMENTS = ['goalkeeper' => 1, 'defender' => 4, 'midfielder' => 4, 'forward' => 2];

    public function __construct(
        private readonly LeagueSettingsService $settings,
        private readonly FantasyRosterService $rosters,
        private readonly InitializeChampionship $initializer,
        private readonly DemoFormationWriter $formations,
        private readonly FinalizeMatchday $finalizer,
    ) {}

    public function run(): void
    {
        $previousTestNow = Carbon::getTestNow();

        try {
            Carbon::setTestNow('2025-06-01 12:00:00');
            $season = $this->isolatedSeason();
            $league = $this->league($season);
            $teams = $this->teams($league);
            $matchdays = $this->matchdays($season);
            $rosters = $this->rosters($season, $league, $teams);

            if (! $league->hasInitializedChampionship()) {
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
                    if ($matchdayIndex === 1 && $teamIndex === self::MISSING_FORMATION_TEAM_INDEX) {
                        continue;
                    }
                    $formation = $this->saveFormation($league, $matchday, $team, $rosters[$team->id], $module);
                    $this->formations->submit($formation, $matchday);
                    $this->scores($season, $matchday, $formation, self::SCORE_MATRIX[$matchdayIndex][$teamIndex]);
                }

                Carbon::setTestNow($matchday->ends_at->copy()->addHour());
                $this->finalizer->finalize($matchday);
            }

            $current = $matchdays->firstWhere('number', self::CURRENT_MATCHDAY_NUMBER);
            Carbon::setTestNow($current->starts_at->copy()->subDay());
            if (! $this->submittedFormationExists($league, $current, $teams->first())) {
                $formation = $this->saveFormation($league, $current, $teams->first(), $rosters[$teams->first()->id], $module);
                $this->formations->submit($formation, $current);
            }
            if (! Formation::query()->whereBelongsTo($league)->whereBelongsTo($current)->whereBelongsTo($teams->get(1))->exists()) {
                $this->saveFormation($league, $current, $teams->get(1), $rosters[$teams->get(1)->id], $module);
            }
        } finally {
            Carbon::setTestNow($previousTestNow);
        }
    }

    private function isolatedSeason(): Season
    {
        $source = Season::query()->where('name', DemoLeagueSeeder::SEASON_NAME)->firstOrFail();
        $season = Season::query()->updateOrCreate(
            ['real_competition_id' => $source->real_competition_id, 'name' => self::SEASON_NAME],
            ['starts_at' => $source->starts_at, 'ends_at' => $source->ends_at, 'is_active' => true],
        );

        foreach (SeasonClub::query()->where('season_id', $source->id)->with('playerSeasonRegistrations')->get() as $sourceClub) {
            $club = SeasonClub::query()->updateOrCreate(
                ['season_id' => $season->id, 'real_club_id' => $sourceClub->real_club_id],
                ['display_name' => $sourceClub->display_name, 'is_active' => true],
            );
            foreach ($sourceClub->playerSeasonRegistrations as $registration) {
                PlayerSeasonRegistration::query()->updateOrCreate(
                    ['player_id' => $registration->player_id, 'season_club_id' => $club->id],
                    [
                        'player_role_id' => $registration->player_role_id,
                        'shirt_number' => $registration->shirt_number,
                        'quotation' => $registration->quotation,
                        'is_active' => true,
                        'registered_on' => $registration->registered_on,
                        'released_on' => null,
                    ],
                );
            }
        }

        return $season;
    }

    private function league(Season $season): League
    {
        $commissioner = User::query()->where('email', self::TEAMS[0][0])->firstOrFail();
        $league = League::query()->updateOrCreate(
            ['season_id' => $season->id, 'slug' => self::LEAGUE_SLUG],
            [
                'league_type_id' => LeagueType::query()->where('key', 'formula_one')->firstOrFail()->id,
                'league_status_id' => LeagueStatus::query()->where('key', LeagueStatus::ACTIVE)->firstOrFail()->id,
                'commissioner_user_id' => $commissioner->id,
                'name' => self::LEAGUE_NAME,
                'description' => 'Deterministic six-team Formula One championship for manual verification.',
                'max_participants' => 6,
            ],
        );
        $this->settings->initializeDefaults($league);
        if (! $league->hasInitializedChampionship()) {
            $this->settings->update($league, [
                LeagueSetting::FORMULA_ONE_POSITION_POINTS => LeagueSetting::DEFAULT_FORMULA_ONE_POSITION_POINTS,
                LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED => false,
                LeagueSetting::DEFENSE_MODIFIER_ENABLED => false,
            ]);
        }

        return $league;
    }

    /** @return Collection<int, FantasyTeam> */
    private function teams(League $league): Collection
    {
        $roles = LeagueRole::query()->pluck('id', 'key');

        return collect(self::TEAMS)->map(function (array $definition) use ($league, $roles): FantasyTeam {
            [$email, $role, $name, $slug] = $definition;
            $user = User::query()->where('email', $email)->firstOrFail();
            $league->users()->syncWithoutDetaching([$user->id => ['league_role_id' => $roles[$role], 'joined_at' => '2025-06-01 12:00:00']]);
            $league->memberships()->where('user_id', $user->id)->update(['league_role_id' => $roles[$role]]);
            $team = FantasyTeam::query()->updateOrCreate(
                ['league_id' => $league->id, 'user_id' => $user->id],
                ['name' => $name, 'slug' => $slug],
            );
            if ($team->budget === null || $team->remaining_budget === null) {
                $team->update(['budget' => 500, 'remaining_budget' => 500]);
            }

            return $team;
        });
    }

    /** @return Collection<int, Matchday> */
    private function matchdays(Season $season): Collection
    {
        return collect(range(0, self::MATCHDAY_COUNT - 1))->map(function (int $index) use ($season): Matchday {
            $startsAt = $index < self::PAST_MATCHDAY_COUNT
                ? Carbon::parse('2025-07-18 18:00:00')->addWeeks($index)
                : Carbon::parse('2099-06-01 18:00:00')->addWeeks($index - self::PAST_MATCHDAY_COUNT);

            return Matchday::query()->updateOrCreate(
                ['season_id' => $season->id, 'number' => self::FIRST_MATCHDAY_NUMBER + $index],
                [
                    'name' => 'Demo Formula One Matchday ' . ($index + 1),
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
            ->with(['player', 'playerRole'])->orderBy('id')->get()->groupBy('playerRole.key');
        $offsets = array_fill_keys(array_keys(self::ROSTER_REQUIREMENTS), 0);
        $assignments = FantasyTeamPlayer::query()->active()->whereBelongsTo($league)->get()->keyBy('player_id');
        $result = [];
        $commissioner = $teams->first()->user;

        foreach ($teams as $team) {
            foreach (self::ROSTER_REQUIREMENTS as $role => $expected) {
                $result[$team->id][$role] = ($pool[$role] ?? collect())->slice($offsets[$role], $expected)
                    ->map(function ($registration) use ($league, $team, $commissioner, $assignments): FantasyTeamPlayer {
                        if ($assignment = $assignments->get($registration->player_id)) {
                            if ($assignment->fantasy_team_id !== $team->id) {
                                throw new LogicException("Demo roster invariant failed for league {$league->slug}: player {$registration->player_id} belongs to the wrong team.");
                            }

                            return $assignment;
                        }
                        $assignment = $this->rosters->assign($league, $team, $registration->player, $commissioner, 10);
                        $assignments->put($registration->player_id, $assignment);

                        return $assignment;
                    })->values();
                $actual = $result[$team->id][$role]->count();
                if ($actual !== $expected) {
                    throw new LogicException("Demo roster invariant failed for league {$league->slug}, team {$team->slug}, role {$role}: expected {$expected}, got {$actual}.");
                }
                $offsets[$role] += $expected;
            }
        }

        return $result;
    }

    /** @param Collection<int, FantasyTeam> $teams */
    private function pastMatchdayIsComplete(League $league, Matchday $matchday, Collection $teams, int $matchdayIndex): bool
    {
        $expected = $teams->count() - ($matchdayIndex === 1 ? 1 : 0);
        $formations = Formation::query()->whereBelongsTo($league)->whereBelongsTo($matchday)
            ->whereNotNull('submitted_at')->where('is_confirmed', true)->get();

        return $formations->count() === $expected
            && ! $formations->contains(fn(Formation $formation): bool => $formation->players()->count() !== 15)
            && PlayerScore::query()->where('matchday_id', $matchday->id)->where('status', PlayerScoreStatus::Confirmed)->count() === ($expected * 15)
            && TeamMatchdayScore::query()->whereBelongsTo($league)->whereBelongsTo($matchday)->count() === $expected
            && TeamMatchdayScoreDetail::query()->whereIn('team_matchday_score_id', TeamMatchdayScore::query()
                ->whereBelongsTo($league)->whereBelongsTo($matchday)->pluck('id'))->count() === ($expected * 15)
            && $league->championshipParticipants()->count() === $teams->count()
            && Standing::query()->whereBelongsTo($league)->count() === $teams->count();
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

    private function scores(Season $season, Matchday $matchday, Formation $formation, int $target): void
    {
        $players = $formation->players()->orderBy('slot_type', 'desc')->orderBy('position_index')->orderBy('id')->get();
        $startersSeen = 0;
        foreach ($players as $formationPlayer) {
            $score = $formationPlayer->slot_type === 'starter'
                ? ($startersSeen++ === 0 ? $target - 50 : 5)
                : 5;
            $registration = PlayerSeasonRegistration::query()->where('player_id', $formationPlayer->player_id)
                ->activeForSeason($season->id)->firstOrFail();
            PlayerScore::query()->updateOrCreate(
                ['player_season_registration_id' => $registration->id, 'matchday_id' => $matchday->id],
                [
                    'base_rating' => $score,
                    'final_score' => $score,
                    'status' => PlayerScoreStatus::Confirmed,
                    'is_captain' => false,
                ],
            );
        }
    }
}
