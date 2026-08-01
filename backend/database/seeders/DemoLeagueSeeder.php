<?php

namespace Database\Seeders;

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
use App\Models\LeagueType;
use App\Models\RealClub;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoLeagueSeeder extends Seeder
{
    public const LEAGUE_SLUG = 'demo-league';

    public const SEASON_NAME = '2025/2026';

    public const CLUBS = [
        ['Aurora FC', 'Aurora', 'demo-aurora-fc'],
        ['Borealis United', 'Borealis', 'demo-borealis-united'],
        ['Calcio Marina', 'Marina', 'demo-calcio-marina'],
        ['Dolomiti Athletic', 'Dolomiti', 'demo-dolomiti-athletic'],
    ];

    public const MEMBERS = [
        'demo.commissioner@example.com' => ['commissioner', 'Commissioner FC', 'commissioner-fc'],
        'demo.cocommissioner@example.com' => ['co_commissioner', 'Co-Commissioner United', 'co-commissioner-united'],
        'demo.participant1@example.com' => ['participant', 'Participant 1 FC', 'participant-1-fc'],
        'demo.participant2@example.com' => ['participant', 'Participant 2 FC', 'participant-2-fc'],
        'demo.participant3@example.com' => ['participant', 'Participant 3 FC', 'participant-3-fc'],
        'demo.participant4@example.com' => ['participant', 'Participant 4 FC', 'participant-4-fc'],
        'demo.participant5@example.com' => ['participant', 'Participant 5 FC', 'participant-5-fc'],
        'demo.participant6@example.com' => ['participant', 'Participant 6 FC', 'participant-6-fc'],
        'demo.participant7@example.com' => ['participant', 'Participant 7 FC', 'participant-7-fc'],
    ];

    private const USER_NAMES = [
        'demo.commissioner@example.com' => 'Demo Commissioner',
        'demo.cocommissioner@example.com' => 'Demo Co-Commissioner',
        'demo.participant1@example.com' => 'Demo Participant 1',
        'demo.participant2@example.com' => 'Demo Participant 2',
        'demo.participant3@example.com' => 'Demo Participant 3',
        'demo.participant4@example.com' => 'Demo Participant 4',
        'demo.participant5@example.com' => 'Demo Participant 5',
        'demo.participant6@example.com' => 'Demo Participant 6',
        'demo.participant7@example.com' => 'Demo Participant 7',
        'demo.nonmember@example.com' => 'Demo Non-Member',
    ];

    public function __construct(private LeagueSettingsService $leagueSettingsService) {}

    public function run(): void
    {
        foreach (self::USER_NAMES as $email => $name) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'email_verified_at' => now(), 'password' => Hash::make('password')],
            );
            $user->update(['name' => $name]);
        }

        $competition = RealCompetition::query()->where('code', 'serie_a')->firstOrFail();
        $season = Season::query()->updateOrCreate(
            ['real_competition_id' => $competition->id, 'name' => self::SEASON_NAME],
            ['starts_at' => '2025-08-01', 'ends_at' => '2026-05-31', 'is_active' => true],
        );

        foreach (self::CLUBS as [$name, $shortName, $slug]) {
            $club = RealClub::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'short_name' => $shortName, 'country_code' => 'IT', 'logo_path' => null],
            );
            SeasonClub::query()->updateOrCreate(
                ['season_id' => $season->id, 'real_club_id' => $club->id],
                ['display_name' => $name, 'is_active' => true],
            );
        }
        $commissioner = User::query()->where('email', 'demo.commissioner@example.com')->firstOrFail();
        $league = League::query()->updateOrCreate(
            ['season_id' => $season->id, 'slug' => self::LEAGUE_SLUG],
            [
                'league_type_id' => LeagueType::query()->where('key', 'classic')->firstOrFail()->id,
                'league_status_id' => LeagueStatus::query()->where('key', LeagueStatus::DRAFT)->firstOrFail()->id,
                'commissioner_user_id' => $commissioner->id,
                'name' => 'Demo League',
                'description' => 'Deterministic league for local development.',
                'max_participants' => 10,
            ],
        );

        $this->leagueSettingsService->initializeDefaults($league);
        $this->leagueSettingsService->update($league, [
            'initial_budget' => 500,
            'release_refund_percentage' => 50,
            'max_roster_players' => LeagueSetting::DEFAULT_MAX_ROSTER_PLAYERS,
            'roster_role_limits' => LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS,
            'budget_rules_mutable' => false,
            'roster_size_mutable' => false,
            'roster_role_limits_mutable' => false,
        ]);
        $roles = LeagueRole::query()->pluck('id', 'key');
        foreach (self::MEMBERS as $email => [$role, $teamName, $teamSlug]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $league->users()->syncWithoutDetaching([
                $user->id => ['league_role_id' => $roles[$role], 'joined_at' => '2025-08-01 00:00:00'],
            ]);
            $league->memberships()->where('user_id', $user->id)->update(['league_role_id' => $roles[$role]]);
            $team = FantasyTeam::query()->updateOrCreate(
                ['league_id' => $league->id, 'user_id' => $user->id],
                [
                    'name' => $teamName,
                    'slug' => $teamSlug,
                    'logo_path' => null,
                ],
            );
            if ($team->budget === null || $team->remaining_budget === null) {
                $team->update([
                    'budget' => $league->initialFantasyBudget(),
                    'remaining_budget' => $league->initialFantasyBudget(),
                ]);
            }
        }
    }
}
