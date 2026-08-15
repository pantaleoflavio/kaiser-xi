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
use App\Models\User;
use App\Services\FantasyTeam\FantasyRosterService;
use App\Services\Formation\SaveFormationService;
use App\Services\Formation\SubmitFormationService;
use App\Services\League\GenerateHeadToHeadSchedule;
use App\Services\League\LeagueSettingsService;
use App\Services\Matchday\FinalizeMatchday;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoHeadToHeadResultsSeeder extends Seeder
{
    public const LEAGUE_SLUG = 'demo-h2h-results';
    public const LEAGUE_NAME = 'Demo H2H Results Arena';
    public const MATCHDAY_NUMBER = 90;

    public const CURRENT_MATCHDAY_NUMBER = 94;

    public const TEAMS = [
        ['demo.commissioner@example.com', 'commissioner', 'Arena Red FC', 'h2h-results-red-fc'],
        ['demo.cocommissioner@example.com', 'co_commissioner', 'Arena Blue United', 'h2h-results-blue-fc'],
        ['demo.participant1@example.com', 'participant', 'Arena Golden Eagles', 'h2h-results-golden-eagles'],
        ['demo.participant2@example.com', 'participant', 'Arena Green Rovers', 'h2h-results-green-rovers'],
        ['demo.participant3@example.com', 'participant', 'Arena Purple City', 'h2h-results-purple-city'],
        ['demo.participant4@example.com', 'participant', 'Arena Silver Stars', 'h2h-results-silver-stars'],
    ];

    private const PAST_MATCHDAYS = 4;
    private const MATCHDAY_COUNT = 14;
    private const ROSTER_REQUIREMENTS = ['goalkeeper' => 2, 'defender' => 5, 'midfielder' => 5, 'forward' => 3];
    private const STARTER_REQUIREMENTS = ['goalkeeper' => 1, 'defender' => 4, 'midfielder' => 4, 'forward' => 2];

    public function __construct(
        private readonly LeagueSettingsService $settings,
        private readonly FantasyRosterService $rosters,
        private readonly GenerateHeadToHeadSchedule $schedule,
        private readonly SaveFormationService $formations,
        private readonly SubmitFormationService $submissions,
        private readonly FinalizeMatchday $finalizer,
    ) {}

    public function run(): void
    {
        $previousTestNow = Carbon::getTestNow();

        try {
            Carbon::setTestNow('2025-06-01 12:00:00');
            $season = Season::query()->where('name', DemoLeagueSeeder::SEASON_NAME)->firstOrFail();
            $league = $this->league($season);
            $teams = $this->teams($league);
            $matchdays = $this->matchdays($season);
            if (! $league->hasInitializedHeadToHeadSchedule()) {
                $this->schedule->handle($league, $matchdays->first()->id);
                $league->refresh();
            }

            $rosters = $this->rosters($season, $league, $teams);
            $module = FormationModule::query()->where('name', '4-4-2')->firstOrFail();

            foreach ($matchdays->take(self::PAST_MATCHDAYS) as $matchdayIndex => $matchday) {
                Carbon::setTestNow($matchday->starts_at->copy()->subDay());
                foreach ($teams as $teamIndex => $team) {
                    $formation = $this->saveFormation($league, $matchday, $team, $rosters[$team->id], $module);
                    $this->submissions->submit($formation, $matchday);
                    $this->scores($season, $matchday, $formation, $teamIndex, $matchdayIndex);
                }

                Carbon::setTestNow($matchday->ends_at->copy()->addHour());
                $this->finalizer->finalize($matchday);
            }

            $current = $matchdays->firstWhere('number', self::CURRENT_MATCHDAY_NUMBER);
            Carbon::setTestNow('2025-08-15 12:00:00');
            foreach ($teams->take(4) as $teamIndex => $team) {
                $formation = $this->saveFormation($league, $current, $team, $rosters[$team->id], $module);
                if ($teamIndex < 3) {
                    $this->submissions->submit($formation, $current);
                }
            }
        } finally {
            Carbon::setTestNow($previousTestNow);
        }
    }

    private function league(Season $season): League
    {
        $commissioner = User::query()->where('email', self::TEAMS[0][0])->firstOrFail();
        $league = League::query()->updateOrCreate(
            ['season_id' => $season->id, 'slug' => self::LEAGUE_SLUG],
            [
                'league_type_id' => LeagueType::query()->where('key', 'head_to_head')->firstOrFail()->id,
                'league_status_id' => LeagueStatus::query()->where('key', LeagueStatus::ACTIVE)->firstOrFail()->id,
                'commissioner_user_id' => $commissioner->id,
                'name' => self::LEAGUE_NAME,
                'description' => 'Initialized six-team H2H league with deterministic past, current, and future rounds.',
                'max_participants' => 6,
            ],
        );
        $this->settings->initializeDefaults($league);
        $this->settings->update($league, [
            LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED => true,
            LeagueSetting::REAL_CAPTAIN_BONUS_POINTS => 0.5,
        ]);

        return $league;
    }

    /** @return Collection<int, FantasyTeam> */
    private function teams(League $league): Collection
    {
        $roles = LeagueRole::query()->pluck('id', 'key');

        return collect(self::TEAMS)->map(function (array $definition) use ($league, $roles): FantasyTeam {
            [$email, $role, $name, $slug] = $definition;
            $user = User::query()->where('email', $email)->firstOrFail();
            $league->users()->syncWithoutDetaching([$user->id => ['league_role_id' => $roles[$role], 'joined_at' => now()]]);
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
            $number = self::MATCHDAY_NUMBER + $index + ($index >= 9 ? 1 : 0);
            $startsAt = match (true) {
                $index < self::PAST_MATCHDAYS => Carbon::parse('2025-07-18 18:00:00')->addWeeks($index),
                $index < 9 => Carbon::parse('2099-06-01 18:00:00')->addWeeks($index - self::PAST_MATCHDAYS),
                default => Carbon::parse('2099-08-08 18:00:00')->addWeeks($index - 9),
            };

            return Matchday::query()->updateOrCreate(
                ['season_id' => $season->id, 'number' => $number],
                [
                    'name' => 'Demo H2H Results Round ' . ($index + 1),
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
        $result = [];
        $commissioner = $teams->first()->user;

        foreach ($teams as $team) {
            foreach (self::ROSTER_REQUIREMENTS as $role => $count) {
                $result[$team->id][$role] = $pool[$role]->slice($offsets[$role], $count)->map(function ($registration) use ($league, $team, $commissioner): FantasyTeamPlayer {
                    return FantasyTeamPlayer::query()->active()->where('league_id', $league->id)
                        ->where('player_id', $registration->player_id)->first()
                        ?? $this->rosters->assign($league, $team, $registration->player, $commissioner, 10);
                })->values();
                $offsets[$role] += $count;
            }
        }

        return $result;
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

    private function scores(Season $season, Matchday $matchday, Formation $formation, int $teamIndex, int $matchdayIndex): void
    {
        $value = 5.5 + (0.5 * (($teamIndex + $matchdayIndex) % 5));
        $players = $formation->players()->orderBy('slot_type', 'desc')->orderBy('position_index')->get();
        $missingStarterId = $teamIndex === 0 && $matchdayIndex === 0
            ? $players->first(fn($player) => $player->slot_type === 'starter' && $player->playerRole->key === 'defender')?->player_id
            : null;

        foreach ($players as $index => $formationPlayer) {
            if ($formationPlayer->player_id === $missingStarterId) {
                continue;
            }
            $registration = PlayerSeasonRegistration::query()->where('player_id', $formationPlayer->player_id)
                ->activeForSeason($season->id)->firstOrFail();
            PlayerScore::query()->updateOrCreate(
                ['player_season_registration_id' => $registration->id, 'matchday_id' => $matchday->id],
                [
                    'base_rating' => $value,
                    'final_score' => $value,
                    'status' => PlayerScoreStatus::Confirmed,
                    'is_captain' => $teamIndex === 0 && $matchdayIndex === 0 && $index === 0,
                ],
            );
        }
    }
}
