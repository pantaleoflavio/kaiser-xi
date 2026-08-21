<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TradeProposalStatus;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\SeasonClub;
use App\Models\TradeProposal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketTradeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        CarbonImmutable::setTestNow('2026-08-21 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_index_enforces_privacy_league_scope_membership_and_latest_order(): void
    {
        $f = $this->fixture();
        $thirdUser = $this->member($f['league'], 'participant');
        $third = FantasyTeam::factory()->forLeagueAndUser($f['league'], $thirdUser)->create();
        $participantTrade = $this->trade($f, ['created_at' => now()->subMinute()]);
        $unrelated = $this->trade($f, ['from_team_id' => $third->id, 'to_team_id' => $f['to']->id, 'created_at' => now()]);
        $other = $this->fixture();
        $this->trade($other);

        Sanctum::actingAs($f['fromUser']);
        $this->getJson($this->url($f['league']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $participantTrade->id);

        foreach (['commissioner', 'co_commissioner'] as $role) {
            $manager = $this->member($f['league'], $role);
            Sanctum::actingAs($manager);
            $this->getJson($this->url($f['league']))->assertOk()->assertJsonPath('data.0.id', $unrelated->id)->assertJsonPath('data.1.id', $participantTrade->id);
        }

        $teamless = $this->member($f['league'], 'participant');
        Sanctum::actingAs($teamless);
        $this->getJson($this->url($f['league']))->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($this->url($f['league']))->assertForbidden();
    }

    public function test_store_returns_created_resource_and_derives_proposer_without_mutation(): void
    {
        $f = $this->fixture();
        Sanctum::actingAs($f['fromUser']);
        $response = $this->postJson($this->url($f['league']), $this->payload($f) + ['from_team_id' => $f['to']->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.proposing_fantasy_team.id', $f['from']->id)
            ->assertJsonPath('data.receiving_fantasy_team.id', $f['to']->id)
            ->assertJsonPath('data.offered_player.id', $f['offered']->player_id)
            ->assertJsonPath('data.requested_player.id', $f['requested']->player_id)
            ->assertJsonPath('data.cash_from_fantasy_team', null)
            ->assertJsonPath('data.cash_amount', 0)
            ->assertJsonPath('data.capabilities.can_accept', false)
            ->assertJsonPath('data.capabilities.can_reject', false)
            ->assertJsonPath('data.capabilities.can_cancel', true);

        $this->assertDatabaseHas('trade_proposals', ['id' => $response->json('data.id'), 'from_team_id' => $f['from']->id]);
        $this->assertNull($f['offered']->refresh()->released_at);
        $this->assertNull($f['requested']->refresh()->released_at);
        $this->assertSame('100.00', $f['from']->refresh()->remaining_budget);
    }

    public function test_store_conflicts_have_stable_409_payloads_and_validation_is_422(): void
    {
        $f = $this->fixture();
        Sanctum::actingAs($f['fromUser']);
        $f['league']->settings()->where('key', LeagueSetting::TRADE_MARKET_ENABLED)->update(['value' => LeagueSetting::booleanPayload(false)]);
        $this->postJson($this->url($f['league']), $this->payload($f))->assertConflict()->assertJson(['code' => 'market_closed'])->assertJsonStructure(['message', 'code']);

        $this->postJson($this->url($f['league']), $this->payload($f, ['cash_amount' => -1]))->assertUnprocessable()->assertJsonValidationErrors('cash_amount');
    }

    public function test_store_accepts_integer_cash_and_rejects_decimal_cash(): void
    {
        $f = $this->fixture();
        Sanctum::actingAs($f['fromUser']);

        $this->postJson($this->url($f['league']), $this->payload($f, ['cash_amount' => 5, 'cash_from_fantasy_team_id' => $f['from']->id]))
            ->assertCreated()->assertJsonPath('data.cash_amount', 5);

        foreach ([0.5, 10.50, -1] as $invalid) {
            $this->postJson($this->url($f['league']), $this->payload($f, ['cash_amount' => $invalid]))
                ->assertUnprocessable()->assertJsonValidationErrors('cash_amount');
        }
    }

    public function test_repeated_identical_pending_proposal_returns_stable_conflict(): void
    {
        $f = $this->fixture();
        Sanctum::actingAs($f['fromUser']);

        $this->postJson($this->url($f['league']), $this->payload($f))->assertCreated();
        $this->postJson($this->url($f['league']), $this->payload($f))
            ->assertConflict()->assertJsonPath('code', 'duplicate_trade_proposal');

        $this->assertSame(1, TradeProposal::query()->where('status', TradeProposalStatus::Pending)->count());
    }

    public function test_terminal_proposal_does_not_block_recreating_identical_proposal(): void
    {
        $f = $this->fixture();
        Sanctum::actingAs($f['fromUser']);
        $first = $this->postJson($this->url($f['league']), $this->payload($f))->assertCreated()->json('data.id');
        $this->postJson($this->url($f['league']) . "/{$first}/cancel")->assertOk();

        $this->postJson($this->url($f['league']), $this->payload($f))->assertCreated();
        $this->assertSame(2, TradeProposal::query()->count());
    }

    public function test_store_rejects_teamless_same_team_wrong_owner_and_cross_league_inputs(): void
    {
        $f = $this->fixture();
        $teamless = $this->member($f['league'], 'participant');
        Sanctum::actingAs($teamless);
        $this->postJson($this->url($f['league']), $this->payload($f))->assertConflict()->assertJsonPath('code', 'invalid_teams');
        Sanctum::actingAs($f['fromUser']);
        $this->postJson($this->url($f['league']), $this->payload($f, ['receiving_fantasy_team_id' => $f['from']->id, 'requested_fantasy_team_player_id' => $f['offered']->id]))->assertConflict()->assertJsonPath('code', 'same_team');
        $this->postJson($this->url($f['league']), $this->payload($f, ['offered_fantasy_team_player_id' => $f['requested']->id]))->assertConflict()->assertJsonPath('code', 'player_not_owned');
        $other = $this->fixture();
        $this->postJson($this->url($f['league']), $this->payload($f, ['receiving_fantasy_team_id' => $other['to']->id]))->assertConflict()->assertJsonPath('code', 'invalid_teams');
    }

    public function test_receiver_accepts_and_market_directory_reflects_current_owners(): void
    {
        $f = $this->fixture();
        $trade = $this->trade($f);
        Sanctum::actingAs($f['toUser']);
        $this->postJson($this->url($f['league']) . "/{$trade->id}/accept")
            ->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.accepted_at', fn($value) => is_string($value));

        $players = $this->getJson("/api/v1/leagues/{$f['league']->id}/market/players")->assertOk()->json('data');
        $byId = collect($players)->keyBy('id');
        $this->assertSame($f['to']->id, $byId[$f['offered']->player_id]['fantasy_team']['id']);
        $this->assertSame($f['from']->id, $byId[$f['requested']->player_id]['fantasy_team']['id']);
    }

    public function test_reject_cancel_authorization_terminal_conflicts_and_capabilities(): void
    {
        $f = $this->fixture();
        $trade = $this->trade($f);
        Sanctum::actingAs($f['fromUser']);
        $this->postJson($this->url($f['league']) . "/{$trade->id}/reject")->assertForbidden();
        Sanctum::actingAs($f['toUser']);
        $this->postJson($this->url($f['league']) . "/{$trade->id}/cancel")->assertForbidden();
        $this->postJson($this->url($f['league']) . "/{$trade->id}/reject")->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.capabilities.can_accept', false)->assertJsonPath('data.rejected_at', fn($value) => is_string($value));
        $this->postJson($this->url($f['league']) . "/{$trade->id}/accept")->assertConflict()->assertJsonPath('code', 'trade_not_pending');

        $cancel = $this->trade($f);
        Sanctum::actingAs($f['fromUser']);
        $this->postJson($this->url($f['league']) . "/{$cancel->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancelled_at', fn($value) => is_string($value));
        $this->postJson($this->url($f['league']) . "/{$cancel->id}/cancel")->assertConflict()->assertJsonPath('code', 'trade_not_pending');
        $this->assertCount(2, FantasyTeamPlayer::query()->active()->get());
    }

    public function test_transition_routes_return_404_for_a_trade_in_another_league(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $trade = $this->trade($other);
        Sanctum::actingAs($f['fromUser']);
        foreach (['accept', 'reject', 'cancel'] as $action) $this->postJson($this->url($f['league']) . "/{$trade->id}/{$action}")->assertNotFound();
    }

    public function test_market_closing_between_proposal_and_acceptance_preserves_pending_trade(): void
    {
        $f = $this->fixture();
        $trade = $this->trade($f);
        CarbonImmutable::setTestNow('2026-08-21 15:00:00 UTC');
        Sanctum::actingAs($f['toUser']);
        $this->postJson($this->url($f['league']) . "/{$trade->id}/accept")->assertConflict()->assertJsonPath('code', 'market_closed');
        $this->assertSame(TradeProposalStatus::Pending, $trade->refresh()->status);
        $this->assertNull($f['offered']->refresh()->released_at);
        $this->getJson($this->url($f['league']))->assertOk()->assertJsonPath('data.0.id', $trade->id);
    }

    private function fixture(): array
    {
        $league = League::factory()->create();
        $fromUser = $this->member($league, 'participant');
        $toUser = $this->member($league, 'participant');
        $from = FantasyTeam::factory()->forLeagueAndUser($league, $fromUser)->create(['budget' => 100, 'remaining_budget' => 100]);
        $to = FantasyTeam::factory()->forLeagueAndUser($league, $toUser)->create(['budget' => 50, 'remaining_budget' => 50]);
        foreach ([LeagueSetting::TRADE_MARKET_ENABLED => LeagueSetting::booleanPayload(true), LeagueSetting::TRADE_MARKET_OPENS_AT => LeagueSetting::stringPayload('2026-08-21T10:00:00Z'), LeagueSetting::TRADE_MARKET_CLOSES_AT => LeagueSetting::stringPayload('2026-08-21T14:00:00Z'), LeagueSetting::TRADE_CASH_ADJUSTMENT_ENABLED => LeagueSetting::booleanPayload(true), LeagueSetting::MAX_ROSTER_PLAYERS => LeagueSetting::integerPayload(LeagueSetting::MAX_ROSTER_PLAYERS, 25), LeagueSetting::ROSTER_ROLE_LIMITS => LeagueSetting::roleLimitsPayload(LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS)] as $key => $value) $league->settings()->create(compact('key', 'value'));
        $offered = $this->assignment($league, $from, $fromUser, 'defender');
        $requested = $this->assignment($league, $to, $toUser, 'midfielder');
        return compact('league', 'fromUser', 'toUser', 'from', 'to', 'offered', 'requested');
    }

    private function member(League $league, string $role): User
    {
        $user = User::factory()->create();
        $league->users()->attach($user, ['league_role_id' => LeagueRole::where('key', $role)->firstOrFail()->id, 'joined_at' => now()]);
        return $user;
    }
    private function assignment(League $league, FantasyTeam $team, User $user, string $role): FantasyTeamPlayer
    {
        $player = Player::factory()->create();
        $club = SeasonClub::factory()->create(['season_id' => $league->season_id]);
        PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => $club->id, 'player_role_id' => PlayerRole::where('key', $role)->firstOrFail()->id]);
        return FantasyTeamPlayer::factory()->create(['league_id' => $league->id, 'fantasy_team_id' => $team->id, 'player_id' => $player->id, 'assigned_by_user_id' => $user->id]);
    }
    private function payload(array $f, array $overrides = []): array
    {
        return array_merge(['receiving_fantasy_team_id' => $f['to']->id, 'offered_fantasy_team_player_id' => $f['offered']->id, 'requested_fantasy_team_player_id' => $f['requested']->id, 'cash_amount' => 0, 'cash_from_fantasy_team_id' => null], $overrides);
    }
    private function trade(array $f, array $overrides = []): TradeProposal
    {
        return TradeProposal::factory()->create(array_merge(['league_id' => $f['league']->id, 'from_team_id' => $f['from']->id, 'to_team_id' => $f['to']->id, 'offered_fantasy_team_player_id' => $f['offered']->id, 'requested_fantasy_team_player_id' => $f['requested']->id, 'status' => TradeProposalStatus::Pending], $overrides));
    }
    private function url(League $league): string
    {
        return "/api/v1/leagues/{$league->id}/market/trades";
    }
}
