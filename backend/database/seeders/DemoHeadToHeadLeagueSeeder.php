<?php

namespace Database\Seeders;

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueStatus;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use Illuminate\Database\Seeder;

class DemoHeadToHeadLeagueSeeder extends Seeder
{
    public const LEAGUE_SLUG = 'demo-h2h-schedule-lab';

    public const LEAGUE_NAME = 'Demo H2H Schedule Lab';

    public const COMMISSIONER_EMAIL = 'demo.commissioner@example.com';

    public const MAX_PARTICIPANTS = 10;

    public const FUTURE_MATCHDAY_COUNT = 12;

    public const FIRST_MATCHDAY_NUMBER = 100;

    public const PARTICIPANTS = [
        'demo.commissioner@example.com' => ['commissioner', 'H2H Commissioner FC', 'h2h-commissioner-fc'],
        'demo.cocommissioner@example.com' => ['co_commissioner', 'H2H Co-Commissioner United', 'h2h-co-commissioner-united'],
        'demo.participant1@example.com' => ['participant', 'H2H Participant 1 FC', 'h2h-participant-1-fc'],
        'demo.participant2@example.com' => ['participant', 'H2H Participant 2 FC', 'h2h-participant-2-fc'],
        'demo.participant3@example.com' => ['participant', 'H2H Participant 3 FC', 'h2h-participant-3-fc'],
        'demo.participant4@example.com' => ['participant', 'H2H Participant 4 FC', 'h2h-participant-4-fc'],
    ];

    public function __construct(private LeagueSettingsService $leagueSettingsService) {}

    public function run(): void
    {
        $season = Season::query()->where('name', DemoLeagueSeeder::SEASON_NAME)->firstOrFail();
        $commissioner = User::query()->where('email', self::COMMISSIONER_EMAIL)->firstOrFail();

        $league = League::query()->firstOrCreate(
            ['season_id' => $season->id, 'slug' => self::LEAGUE_SLUG],
            [
                'league_type_id' => LeagueType::query()->where('key', 'head_to_head')->firstOrFail()->id,
                'league_status_id' => LeagueStatus::query()->where('key', LeagueStatus::ACTIVE)->firstOrFail()->id,
                'commissioner_user_id' => $commissioner->id,
                'name' => self::LEAGUE_NAME,
                'description' => 'Uninitialized head-to-head league for deterministic schedule testing.',
                'max_participants' => self::MAX_PARTICIPANTS,
            ],
        );

        $this->leagueSettingsService->initializeDefaults($league);

        $roles = LeagueRole::query()->pluck('id', 'key');
        foreach (self::PARTICIPANTS as $email => [$role, $teamName, $teamSlug]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $league->users()->syncWithoutDetaching([
                $user->id => ['league_role_id' => $roles[$role], 'joined_at' => '2025-08-01 00:00:00'],
            ]);
            $league->memberships()->where('user_id', $user->id)->update(['league_role_id' => $roles[$role]]);

            FantasyTeam::query()->updateOrCreate(
                ['league_id' => $league->id, 'user_id' => $user->id],
                [
                    'name' => $teamName,
                    'slug' => $teamSlug,
                    'logo_path' => null,
                    'budget' => $league->initialFantasyBudget(),
                    'remaining_budget' => $league->initialFantasyBudget(),
                ],
            );
        }

        foreach (range(0, self::FUTURE_MATCHDAY_COUNT - 1) as $offset) {
            $number = self::FIRST_MATCHDAY_NUMBER + $offset;

            Matchday::query()->updateOrCreate(
                ['season_id' => $season->id, 'number' => $number],
                [
                    'name' => "Demo H2H Matchday {$number}",
                    'starts_at' => sprintf('2099-09-%02d 18:00:00', $offset + 1),
                    'ends_at' => sprintf('2099-09-%02d 23:59:59', $offset + 2),
                ],
            );
        }
    }
}
