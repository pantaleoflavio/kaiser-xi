<?php

namespace Database\Seeders;

use App\Enums\PlayerScoreStatus;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
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

class DemoHeadToHeadResultsSeeder extends Seeder
{
    public const LEAGUE_SLUG = 'demo-h2h-results';

    public const LEAGUE_NAME = 'Demo H2H Results Arena';

    public const MATCHDAY_NUMBER = 90;

    public const MATCHDAY_NAME = 'Demo H2H Results Matchday';

    public const HOME_TEAM_SLUG = 'h2h-results-red-fc';

    public const AWAY_TEAM_SLUG = 'h2h-results-blue-fc';

    private const DEMO_NOW = '2025-08-15 12:00:00';

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
        Carbon::setTestNow(self::DEMO_NOW);

        try {
            $season = Season::query()->where('name', DemoLeagueSeeder::SEASON_NAME)->firstOrFail();
            $commissioner = User::query()->where('email', 'demo.commissioner@example.com')->firstOrFail();
            $opponent = User::query()->where('email', 'demo.cocommissioner@example.com')->firstOrFail();
            $league = League::query()->updateOrCreate(
                ['season_id' => $season->id, 'slug' => self::LEAGUE_SLUG],
                [
                    'league_type_id' => LeagueType::query()->where('key', 'head_to_head')->firstOrFail()->id,
                    'league_status_id' => LeagueStatus::query()->where('key', LeagueStatus::ACTIVE)->firstOrFail()->id,
                    'commissioner_user_id' => $commissioner->id,
                    'name' => self::LEAGUE_NAME,
                    'description' => 'Initialized H2H league with deterministic submitted lineups, scores, results, and standings.',
                    'max_participants' => 2,
                ],
            );

            $this->settings->initializeDefaults($league);
            $this->settings->update($league, [
                LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED => true,
                LeagueSetting::REAL_CAPTAIN_BONUS_POINTS => 0.5,
            ]);

            $roles = LeagueRole::query()->pluck('id', 'key');
            $teams = collect([
                [$commissioner, 'commissioner', 'H2H Results Red FC', self::HOME_TEAM_SLUG],
                [$opponent, 'participant', 'H2H Results Blue FC', self::AWAY_TEAM_SLUG],
            ])->map(function (array $participant) use ($league, $roles): FantasyTeam {
                [$user, $role, $name, $slug] = $participant;
                $league->users()->syncWithoutDetaching([
                    $user->id => ['league_role_id' => $roles[$role], 'joined_at' => self::DEMO_NOW],
                ]);
                $league->memberships()->where('user_id', $user->id)->update(['league_role_id' => $roles[$role]]);

                $team = FantasyTeam::query()->firstOrCreate(
                    ['league_id' => $league->id, 'user_id' => $user->id],
                    ['name' => $name, 'slug' => $slug, 'budget' => 500, 'remaining_budget' => 500],
                );
                $team->update(['name' => $name, 'slug' => $slug]);

                return $team;
            });

            $matchday = Matchday::query()->updateOrCreate(
                ['season_id' => $season->id, 'number' => self::MATCHDAY_NUMBER],
                ['name' => self::MATCHDAY_NAME, 'starts_at' => '2025-09-01 18:00:00', 'ends_at' => '2025-09-02 23:59:59'],
            );

            if (! $league->hasInitializedHeadToHeadSchedule()) {
                $this->schedule->handle($league, $matchday->id);
                $league->refresh();
            }

            $playersByRole = PlayerSeasonRegistration::query()
                ->activeForSeason($season->id)
                ->with(['player', 'playerRole'])
                ->orderBy('id')
                ->get()
                ->groupBy('playerRole.key');
            $roleOffsets = ['goalkeeper' => 0, 'defender' => 0, 'midfielder' => 0, 'forward' => 0];
            $requirements = ['goalkeeper' => 1, 'defender' => 4, 'midfielder' => 4, 'forward' => 2];
            $module = FormationModule::query()->where('name', '4-4-2')->firstOrFail();

            foreach ($teams as $teamIndex => $team) {
                $starterIds = [];
                foreach ($requirements as $role => $count) {
                    foreach ($playersByRole[$role]->slice($roleOffsets[$role], $count) as $registration) {
                        $assignment = FantasyTeamPlayer::query()->active()
                            ->where('league_id', $league->id)->where('player_id', $registration->player_id)->first();
                        if (! $assignment) {
                            $assignment = $this->rosters->assign($league, $team, $registration->player, $commissioner, 10);
                        }
                        $starterIds[] = $assignment->id;
                    }
                    $roleOffsets[$role] += $count;
                }

                $formation = $this->formations->save($league, $matchday, $team, [
                    'formation_module_id' => $module->id,
                    'starters' => $starterIds,
                    'bench' => [],
                ]);
                $this->submissions->submit($formation, $matchday);

                foreach ($starterIds as $index => $assignmentId) {
                    $assignment = FantasyTeamPlayer::query()->findOrFail($assignmentId);
                    $registration = PlayerSeasonRegistration::query()
                        ->where('player_id', $assignment->player_id)->activeForSeason($season->id)->firstOrFail();
                    PlayerScore::query()->updateOrCreate(
                        ['player_season_registration_id' => $registration->id, 'matchday_id' => $matchday->id],
                        [
                            'base_rating' => $teamIndex === 0 ? 7.0 : 6.0,
                            'final_score' => $teamIndex === 0 ? 7.0 : 6.0,
                            'status' => PlayerScoreStatus::Confirmed,
                            'is_captain' => $teamIndex === 0 && $index === 0,
                        ],
                    );
                }
            }

            Carbon::setTestNow('2025-09-03 12:00:00');
            $this->finalizer->finalize($matchday);
        } finally {
            Carbon::setTestNow($previousTestNow);
        }
    }
}
