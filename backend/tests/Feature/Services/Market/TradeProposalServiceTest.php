<?php

namespace Tests\Feature\Services\Market;

use App\Enums\TradeProposalStatus;
use App\Exceptions\TradeConflictException;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationPlayer;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\SeasonClub;
use App\Models\TradeProposal;
use App\Models\User;
use App\Services\Market\TradeProposalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TradeProposalServiceTest extends TestCase
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
        FantasyTeamPlayer::flushEventListeners();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_propose_persists_pending_trade_without_mutating_rosters_or_budgets(): void
    {
        $f = $this->fixture();
        $trade = $this->service()->propose($f['league'], $f['fromUser'], $this->payload($f));

        $this->assertSame(TradeProposalStatus::Pending, $trade->status);
        $this->assertSame($f['from']->id, $trade->from_team_id);
        $this->assertNull($f['offered']->refresh()->released_at);
        $this->assertNull($f['requested']->refresh()->released_at);
        $this->assertSame('100.00', $f['from']->refresh()->remaining_budget);
        $this->assertSame('50.00', $f['to']->refresh()->remaining_budget);
        $this->assertCount(2, FantasyTeamPlayer::query()->get());
    }

    public function test_proposal_validates_market_ownership_teams_registration_and_cash_configuration(): void
    {
        $cases = [
            'market_closed' => function (array $f): array {
                $f['league']->settings()->where('key', LeagueSetting::TRADE_MARKET_ENABLED)->update(['value' => LeagueSetting::booleanPayload(false)]);
                return $this->payload($f);
            },
            'same_team' => fn(array $f): array => $this->payload($f, ['receiving_fantasy_team_id' => $f['from']->id, 'requested_fantasy_team_player_id' => $f['offered']->id]),
            'player_not_owned' => fn(array $f): array => $this->payload($f, ['offered_fantasy_team_player_id' => $f['requested']->id]),
            'invalid_player_registration' => function (array $f): array {
                PlayerSeasonRegistration::where('player_id', $f['offered']->player_id)->update(['released_at' => now()]);
                return $this->payload($f);
            },
            'invalid_cash_payer' => fn(array $f): array => $this->payload($f, ['cash_amount' => 1, 'cash_from_fantasy_team_id' => 999999]),
        ];

        foreach ($cases as $code => $mutate) {
            $f = $this->fixture();
            try {
                $this->service()->propose($f['league'], $f['fromUser'], $mutate($f));
                $this->fail("Expected {$code}");
            } catch (TradeConflictException $e) {
                $this->assertSame($code, $e->errorCode);
            }
        }
    }

    public function test_accept_preserves_assignment_history_and_swaps_players_at_one_timestamp(): void
    {
        $f = $this->fixture();
        $f['offered']->update(['purchase_price' => 40]);
        $f['requested']->update(['purchase_price' => 10]);
        $trade = $this->proposal($f);
        $accepted = $this->service()->accept($f['league'], $trade, $f['toUser']);

        $this->assertSame(TradeProposalStatus::Accepted, $accepted->status);
        $this->assertNotNull($accepted->accepted_at);
        $this->assertSame($f['from']->id, $f['offered']->refresh()->fantasy_team_id);
        $this->assertSame($f['to']->id, $f['requested']->refresh()->fantasy_team_id);
        $this->assertNotNull($f['offered']->released_at);
        $this->assertNotNull($f['requested']->released_at);
        $new = FantasyTeamPlayer::query()->active()->orderBy('id')->get();
        $this->assertCount(2, $new);
        $this->assertSame($f['to']->id, $new->firstWhere('player_id', $f['offered']->player_id)->fantasy_team_id);
        $this->assertSame($f['from']->id, $new->firstWhere('player_id', $f['requested']->player_id)->fantasy_team_id);
        $this->assertSame('40.00', $f['offered']->purchase_price);
        $this->assertSame('10.00', $f['requested']->purchase_price);
        $this->assertSame('40.00', $new->firstWhere('player_id', $f['offered']->player_id)->purchase_price);
        $this->assertSame('10.00', $new->firstWhere('player_id', $f['requested']->player_id)->purchase_price);
        $this->assertTrue($new[0]->assigned_at->equalTo($new[1]->assigned_at));
    }

    public function test_historical_purchase_prices_may_exceed_initial_budget_after_trade(): void
    {
        $f = $this->fixture();
        $f['league']->settings()->updateOrCreate(
            ['key' => LeagueSetting::INITIAL_BUDGET],
            ['value' => LeagueSetting::integerPayload(LeagueSetting::INITIAL_BUDGET, 50)],
        );
        $f['requested']->update(['purchase_price' => 75]);

        $this->service()->accept($f['league'], $this->proposal($f), $f['toUser']);

        $this->assertSame('75.00', FantasyTeamPlayer::query()->active()->where('fantasy_team_id', $f['from']->id)->sole()->purchase_price);
    }

    public function test_service_rejects_decimal_cash_without_rounding(): void
    {
        $f = $this->fixture();

        $this->expectConflict('invalid_cash_amount', fn() => $this->service()->propose(
            $f['league'],
            $f['fromUser'],
            $this->payload($f, ['cash_amount' => 1.25, 'cash_from_fantasy_team_id' => $f['from']->id]),
        ));
        $this->assertDatabaseCount('trade_proposals', 0);
    }


    public function test_cash_moves_between_budgets_without_changing_player_registration_or_quotation(): void
    {
        $f = $this->fixture();
        $registration = PlayerSeasonRegistration::where('player_id', $f['offered']->player_id)->firstOrFail();
        $quotation = $registration->quotation;
        $trade = $this->proposal($f, ['cash_amount' => 25, 'cash_from_fantasy_team_id' => $f['from']->id]);

        $this->service()->accept($f['league'], $trade, $f['toUser']);

        $this->assertSame('75.00', $f['from']->refresh()->remaining_budget);
        $this->assertSame('75.00', $f['to']->refresh()->remaining_budget);
        $this->assertSame($quotation, $registration->refresh()->quotation);
        $this->assertSame($f['offered']->player_id, $registration->player_id);
    }

    public function test_insufficient_cash_rolls_back_and_leaves_proposal_pending(): void
    {
        $f = $this->fixture();
        $trade = $this->proposal($f, ['cash_amount' => 125, 'cash_from_fantasy_team_id' => $f['from']->id]);

        $this->expectConflict('insufficient_budget', fn() => $this->service()->accept($f['league'], $trade, $f['toUser']));
        $this->assertUnchanged($f, $trade);
    }

    public function test_role_limit_failure_is_atomic(): void
    {
        $f = $this->fixture('goalkeeper', 'defender');
        $f['league']->settings()->where('key', LeagueSetting::ROSTER_ROLE_LIMITS)->update(['value' => LeagueSetting::roleLimitsPayload(['goalkeeper' => 0, 'defender' => 8, 'midfielder' => 8, 'forward' => 6])]);
        $trade = $this->proposal($f);

        $this->expectConflict('invalid_roster', fn() => $this->service()->accept($f['league'], $trade, $f['toUser']));
        $this->assertUnchanged($f, $trade);
    }

    public function test_stale_and_competing_proposals_fail_cleanly(): void
    {
        $f = $this->fixture();

        $alternativeRequested = $this->assignment(
            $f['league'],
            $f['to'],
            $f['toUser'],
            'forward',
        );

        $first = $this->proposal($f);

        $second = $this->proposal($f, [
            'requested_fantasy_team_player_id' => $alternativeRequested->id,
        ]);

        $this->service()->accept(
            $f['league'],
            $first,
            $f['toUser'],
        );

        $this->expectConflict(
            'player_not_owned',
            fn() => $this->service()->accept(
                $f['league'],
                $second,
                $f['toUser'],
            ),
        );

        $this->assertSame(
            TradeProposalStatus::Pending,
            $second->refresh()->status,
        );

        $this->assertCount(
            3,
            FantasyTeamPlayer::query()->active()->get(),
        );

        $this->assertCount(
            5,
            FantasyTeamPlayer::query()->get(),
        );
    }

    public function test_double_accept_does_not_repeat_assignments_or_cash(): void
    {
        $f = $this->fixture();
        $trade = $this->proposal($f, ['cash_amount' => 10, 'cash_from_fantasy_team_id' => $f['from']->id]);
        $this->service()->accept($f['league'], $trade, $f['toUser']);

        $this->expectConflict('trade_not_pending', fn() => $this->service()->accept($f['league'], $trade, $f['toUser']));
        $this->assertCount(4, FantasyTeamPlayer::query()->get());
        $this->assertSame('90.00', $f['from']->refresh()->remaining_budget);
        $this->assertSame('60.00', $f['to']->refresh()->remaining_budget);
    }

    public function test_real_database_transaction_rolls_back_a_failure_during_replacement_creation(): void
    {
        $f = $this->fixture();
        $trade = $this->proposal($f, ['cash_amount' => 10, 'cash_from_fantasy_team_id' => $f['from']->id]);
        $creations = 0;
        FantasyTeamPlayer::creating(function () use (&$creations): void {
            if (++$creations === 2) throw new RuntimeException('forced replacement failure');
        });

        try {
            $this->service()->accept($f['league'], $trade, $f['toUser']);
            $this->fail('Exception not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('forced replacement failure', $e->getMessage());
        }
        $this->assertUnchanged($f, $trade);
    }

    public function test_acceptance_preserves_historical_formation_reference(): void
    {
        $f = $this->fixture();
        $formation = Formation::factory()->create(['league_id' => $f['league']->id, 'fantasy_team_id' => $f['from']->id]);
        $formationPlayer = FormationPlayer::factory()->create(['formation_id' => $formation->id, 'fantasy_team_player_id' => $f['offered']->id, 'player_id' => $f['offered']->player_id]);
        $snapshot = $formation->snapshot;
        $trade = $this->proposal($f);

        $this->service()->accept($f['league'], $trade, $f['toUser']);

        $this->assertSame($snapshot, $formation->refresh()->snapshot);
        $this->assertSame($f['offered']->id, $formationPlayer->refresh()->fantasy_team_player_id);
        $this->assertDatabaseHas('fantasy_team_players', ['id' => $f['offered']->id, 'fantasy_team_id' => $f['from']->id]);
        $this->assertNotNull($f['offered']->refresh()->released_at);
    }

    private function fixture(string $offeredRole = 'defender', string $requestedRole = 'midfielder'): array
    {
        $fromUser = User::factory()->create();
        $toUser = User::factory()->create();
        $league = League::factory()->create();
        $from = FantasyTeam::factory()->forLeagueAndUser($league, $fromUser)->create(['budget' => 100, 'remaining_budget' => 100]);
        $to = FantasyTeam::factory()->forLeagueAndUser($league, $toUser)->create(['budget' => 50, 'remaining_budget' => 50]);
        foreach ([LeagueSetting::TRADE_MARKET_ENABLED => LeagueSetting::booleanPayload(true), LeagueSetting::TRADE_MARKET_OPENS_AT => LeagueSetting::stringPayload('2026-08-21T10:00:00Z'), LeagueSetting::TRADE_MARKET_CLOSES_AT => LeagueSetting::stringPayload('2026-08-21T14:00:00Z'), LeagueSetting::TRADE_CASH_ADJUSTMENT_ENABLED => LeagueSetting::booleanPayload(true), LeagueSetting::MAX_ROSTER_PLAYERS => LeagueSetting::integerPayload(LeagueSetting::MAX_ROSTER_PLAYERS, 25), LeagueSetting::ROSTER_ROLE_LIMITS => LeagueSetting::roleLimitsPayload(LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS)] as $key => $value) $league->settings()->create(compact('key', 'value'));
        $offered = $this->assignment($league, $from, $fromUser, $offeredRole);
        $requested = $this->assignment($league, $to, $toUser, $requestedRole);
        return compact('league', 'fromUser', 'toUser', 'from', 'to', 'offered', 'requested');
    }

    private function assignment(League $league, FantasyTeam $team, User $user, string $role): FantasyTeamPlayer
    {
        $player = Player::factory()->create();
        $club = SeasonClub::factory()->create(['season_id' => $league->season_id]);
        PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => $club->id, 'player_role_id' => PlayerRole::where('key', $role)->firstOrFail()->id, 'quotation' => 42]);
        return FantasyTeamPlayer::factory()->create(['league_id' => $league->id, 'fantasy_team_id' => $team->id, 'player_id' => $player->id, 'assigned_by_user_id' => $user->id]);
    }

    private function payload(array $f, array $overrides = []): array
    {
        return array_merge(['receiving_fantasy_team_id' => $f['to']->id, 'offered_fantasy_team_player_id' => $f['offered']->id, 'requested_fantasy_team_player_id' => $f['requested']->id, 'cash_amount' => 0, 'cash_from_fantasy_team_id' => null], $overrides);
    }
    private function proposal(array $f, array $overrides = []): TradeProposal
    {
        return $this->service()->propose($f['league'], $f['fromUser'], $this->payload($f, $overrides));
    }
    private function service(): TradeProposalService
    {
        return app(TradeProposalService::class);
    }
    private function expectConflict(string $code, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected {$code}");
        } catch (TradeConflictException $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }
    private function assertUnchanged(array $f, TradeProposal $trade): void
    {
        $this->assertNull($f['offered']->refresh()->released_at);
        $this->assertNull($f['requested']->refresh()->released_at);
        $this->assertCount(2, FantasyTeamPlayer::query()->get());
        $this->assertSame('100.00', $f['from']->refresh()->remaining_budget);
        $this->assertSame('50.00', $f['to']->refresh()->remaining_budget);
        $this->assertSame(TradeProposalStatus::Pending, $trade->refresh()->status);
        $this->assertNull($trade->accepted_at);
    }
}
