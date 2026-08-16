<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueMembershipAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_commissioner_can_remove_participant(): void
    {
        [$league, $commissioner] = $this->managedLeague();
        $participant = $this->member($league, 'participant');

        $this->authenticate($commissioner)->deleteJson($this->memberUrl($league, $participant))->assertNoContent();

        $this->assertDatabaseMissing('league_user', ['league_id' => $league->id, 'user_id' => $participant->id]);
    }

    public function test_commissioner_can_remove_co_commissioner(): void
    {
        [$league, $commissioner] = $this->managedLeague();
        $coCommissioner = $this->member($league, 'co_commissioner');

        $this->authenticate($commissioner)->deleteJson($this->memberUrl($league, $coCommissioner))->assertNoContent();
    }

    public function test_commissioner_cannot_remove_themselves(): void
    {
        [$league, $commissioner] = $this->managedLeague();

        $this->authenticate($commissioner)->deleteJson($this->memberUrl($league, $commissioner))->assertForbidden();
        $this->assertDatabaseHas('league_user', ['league_id' => $league->id, 'user_id' => $commissioner->id]);
    }

    public function test_co_commissioner_can_remove_participant(): void
    {
        [$league] = $this->managedLeague();
        $coCommissioner = $this->member($league, 'co_commissioner');
        $participant = $this->member($league, 'participant');

        $this->authenticate($coCommissioner)->deleteJson($this->memberUrl($league, $participant))->assertNoContent();
    }

    public function test_co_commissioner_cannot_remove_commissioner_or_another_co_commissioner(): void
    {
        [$league, $commissioner] = $this->managedLeague();
        $actor = $this->member($league, 'co_commissioner');
        $other = $this->member($league, 'co_commissioner');

        $this->authenticate($actor)->deleteJson($this->memberUrl($league, $commissioner))->assertForbidden();
        $this->deleteJson($this->memberUrl($league, $other))->assertForbidden();
    }

    public function test_participant_and_non_member_cannot_remove_members(): void
    {
        [$league] = $this->managedLeague();
        $participant = $this->member($league, 'participant');
        $target = $this->member($league, 'participant');

        $this->authenticate($participant)->deleteJson($this->memberUrl($league, $target))->assertForbidden();
        $this->authenticate(User::factory()->create())->deleteJson($this->memberUrl($league, $target))->assertForbidden();
    }

    public function test_removed_member_loses_access_but_user_team_and_roster_history_are_preserved(): void
    {
        [$league, $commissioner] = $this->managedLeague();
        $participant = $this->member($league, 'participant');
        $team = FantasyTeam::factory()->create(['league_id' => $league->id, 'user_id' => $participant->id]);
        $assignment = FantasyTeamPlayer::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'player_id' => Player::factory()->create()->id,
        ]);

        $this->authenticate($commissioner)->deleteJson($this->memberUrl($league, $participant))->assertNoContent();

        $this->assertDatabaseHas('users', ['id' => $participant->id]);
        $this->assertDatabaseHas('fantasy_teams', ['id' => $team->id, 'user_id' => $participant->id]);
        $this->assertDatabaseHas('fantasy_team_players', ['id' => $assignment->id]);
        $this->authenticate($participant)->getJson("/api/v1/leagues/{$league->id}")->assertForbidden();
    }

    public function test_member_from_another_league_is_not_modified(): void
    {
        [$league, $commissioner] = $this->managedLeague();
        [$otherLeague] = $this->managedLeague();
        $outsider = $this->member($otherLeague, 'participant');

        $this->authenticate($commissioner)->deleteJson($this->memberUrl($league, $outsider))->assertNotFound();
        $this->assertDatabaseHas('league_user', ['league_id' => $otherLeague->id, 'user_id' => $outsider->id]);
    }

    public function test_commissioner_can_promote_and_revoke_co_commissioner_role(): void
    {
        [$league, $commissioner] = $this->managedLeague();
        $participant = $this->member($league, 'participant');

        $this->authenticate($commissioner)->patchJson($this->roleUrl($league, $participant), ['role' => 'co_commissioner'])
            ->assertOk()->assertJsonPath('data.role.key', 'co_commissioner');
        $this->patchJson($this->roleUrl($league, $participant), ['role' => 'participant'])
            ->assertOk()->assertJsonPath('data.role.key', 'participant');
    }

    public function test_role_endpoint_rejects_commissioner_unknown_numeric_and_protected_fields(): void
    {
        [$league, $commissioner] = $this->managedLeague();
        $target = $this->member($league, 'participant');
        $url = $this->roleUrl($league, $target);

        foreach ([['role' => 'commissioner'], ['role' => 'unknown'], ['role' => 1]] as $payload) {
            $this->authenticate($commissioner)->patchJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('role');
        }

        foreach (['league_id', 'user_id', 'league_role_id', 'commissioner_user_id'] as $field) {
            $this->patchJson($url, ['role' => 'participant', $field => 1])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_only_commissioner_can_change_another_members_role(): void
    {
        [$league] = $this->managedLeague();
        $coCommissioner = $this->member($league, 'co_commissioner');
        $participant = $this->member($league, 'participant');

        $this->authenticate($coCommissioner)->patchJson($this->roleUrl($league, $participant), ['role' => 'co_commissioner'])->assertForbidden();
        $this->authenticate($participant)->patchJson($this->roleUrl($league, $coCommissioner), ['role' => 'participant'])->assertForbidden();
    }

    public function test_user_cannot_modify_their_own_role(): void
    {
        [$league, $commissioner] = $this->managedLeague();

        $this->authenticate($commissioner)->patchJson($this->roleUrl($league, $commissioner), ['role' => 'participant'])->assertForbidden();
    }

    public function test_role_target_must_be_a_member_of_nested_league(): void
    {
        [$league, $commissioner] = $this->managedLeague();
        [$otherLeague] = $this->managedLeague();
        $target = $this->member($otherLeague, 'participant');

        $this->authenticate($commissioner)->patchJson($this->roleUrl($league, $target), ['role' => 'co_commissioner'])->assertNotFound();
        $this->assertMembershipRole($otherLeague, $target, 'participant');
    }

    public function test_role_update_changes_only_the_intended_pivot(): void
    {
        [$league, $commissioner] = $this->managedLeague();
        [$otherLeague] = $this->managedLeague();
        $target = $this->member($league, 'participant');
        $this->attach($otherLeague, $target, 'participant');

        $this->authenticate($commissioner)->patchJson($this->roleUrl($league, $target), ['role' => 'co_commissioner'])->assertOk();

        $this->assertMembershipRole($league, $target, 'co_commissioner');
        $this->assertMembershipRole($otherLeague, $target, 'participant');
    }

    private function managedLeague(): array
    {
        $commissioner = User::factory()->create();
        $league = League::factory()->create(['commissioner_user_id' => $commissioner->id]);
        $this->attach($league, $commissioner, 'commissioner');

        return [$league, $commissioner];
    }

    private function member(League $league, string $role): User
    {
        $user = User::factory()->create();
        $this->attach($league, $user, $role);

        return $user;
    }

    private function attach(League $league, User $user, string $role): void
    {
        $league->users()->attach($user->id, [
            'league_role_id' => LeagueRole::query()->where('key', $role)->value('id'),
            'joined_at' => now(),
        ]);
    }

    private function authenticate(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function memberUrl(League $league, User $user): string
    {
        return "/api/v1/leagues/{$league->id}/members/{$user->id}";
    }

    private function roleUrl(League $league, User $user): string
    {
        return $this->memberUrl($league, $user) . '/role';
    }

    private function assertMembershipRole(League $league, User $user, string $role): void
    {
        $this->assertDatabaseHas('league_user', [
            'league_id' => $league->id,
            'user_id' => $user->id,
            'league_role_id' => LeagueRole::query()->where('key', $role)->value('id'),
        ]);
    }
}
