<?php

namespace Database\Seeders;

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueStatus;
use App\Models\LeagueType;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoLeagueSeeder extends Seeder
{
    public function __construct(
        private LeagueSettingsService $leagueSettingsService
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $competition = RealCompetition::query()->where('code', 'serie_a')->firstOrFail();
        $season = Season::query()->firstOrCreate(
            [
            'real_competition_id' => $competition->id,
            'name' => '2025/2026',
            ],
            [
                'starts_at' => now()->startOfYear(),
                'ends_at' => now()->endOfYear(),
                'is_active' => true,
            ]
        );

        $commissioner = User::query()->firstOrCreate(
            [
                'email' => 'demo.commissioner@example.com',
            ],
            [
                'name' => 'Demo Commissioner',
                'email_verified_at' => now(),
                'password' =>  Hash::make('password'),
            ]
        );

        $league = League::query()->firstOrCreate(
            [
                'season_id' => $season->id,
                'slug' => 'demo-league',
            ],
            [
                'league_type_id' => LeagueType::query()
                    ->where('key', 'classic')
                    ->firstOrFail()
                    ->id,
                'league_status_id' => LeagueStatus::query()
                    ->where('key', 'draft')
                    ->firstOrFail()
                    ->id,
                'commissioner_user_id' => $commissioner->id,
                'name' => 'Demo League',
                'description' => 'Demo league for local development.',
                'max_participants' => 10,
            ]
        );

        $this->leagueSettingsService->initializeDefaults($league);

        $commissionerRoleId = LeagueRole::query()
            ->where('key', 'commissioner')
            ->firstOrFail()
            ->id;

        $participantRoleId = LeagueRole::query()
            ->where('key', 'participant')
            ->firstOrFail()
            ->id;

        $this->attachMember($league, $commissioner, $commissionerRoleId);

        $this->createFantasyTeam(
            $league,
            $commissioner,
            'Commissioner FC',
            'commissioner-fc'
        );
        for ($index = 1; $index <= 7; $index++) {
            $user = User::query()->firstOrCreate(
                [
                    'email' => "demo.participant{$index}@example.com",
                ],
                [
                    'name' => "Demo Participant {$index}",
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ]
            );

            $this->attachMember($league, $user, $participantRoleId);

            $name = "Participant {$index} FC";

            $this->createFantasyTeam(
                $league,
                $user,
                $name,
                Str::slug($name)
            );
        }
    }

    private function attachMember(
        League $league,
        User $user,
        int $leagueRoleId
    ): void {
        $league->users()->syncWithoutDetaching([
            $user->id => [
                'league_role_id' => $leagueRoleId,
                'joined_at' => now(),
            ],
        ]);
    }

    private function createFantasyTeam(
        League $league,
        User $user,
        string $name,
        string $slug
    ): void {
        FantasyTeam::query()->firstOrCreate(
            [
                'league_id' => $league->id,
                'user_id' => $user->id,
            ],
            [
                'name' => $name,
                'slug' => $slug,
                'logo_path' => null,
                'budget' => null,
                'remaining_budget' => null,
            ]
        );
    }
}
