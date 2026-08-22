<?php

namespace Tests\Feature\Domain;

use App\Models\Player;
use App\Models\PlayerExternalIdentity;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClub;
use App\Models\RealClubExternalIdentity;
use App\Models\SeasonClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExternalIdentityFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_player_supports_zero_multiple_and_same_provider_external_identities(): void
    {
        $player = Player::factory()->create();

        $this->assertCount(0, $player->externalIdentities);

        PlayerExternalIdentity::factory()->create(['player_id' => $player->id, 'provider' => 'provider-a', 'external_id' => 'one']);
        PlayerExternalIdentity::factory()->create(['player_id' => $player->id, 'provider' => 'provider-b', 'external_id' => 'two']);
        PlayerExternalIdentity::factory()->create(['player_id' => $player->id, 'provider' => 'provider-a', 'external_id' => 'three']);

        $this->assertCount(3, $player->refresh()->externalIdentities);
    }

    public function test_player_identity_normalizes_provider_preserves_external_id_and_rejects_duplicates(): void
    {
        $identity = PlayerExternalIdentity::factory()->create([
            'provider' => ' Transfermarkt.API ',
            'external_id' => 'ABC-001',
        ]);

        $this->assertSame('transfermarkt.api', $identity->provider);
        $this->assertSame('ABC-001', $identity->external_id);

        $this->expectException(ValidationException::class);
        PlayerExternalIdentity::factory()->create([
            'player_id' => Player::factory(),
            'provider' => 'TRANSFERMARKT.API',
            'external_id' => 'ABC-001',
        ]);
    }

    public function test_deleting_player_cascades_external_identities(): void
    {
        $identity = PlayerExternalIdentity::factory()->create();

        $identity->player->delete();

        $this->assertDatabaseMissing('player_external_identities', ['id' => $identity->id]);
    }

    public function test_real_club_supports_zero_multiple_and_same_provider_external_identities(): void
    {
        $club = RealClub::factory()->create();

        $this->assertCount(0, $club->externalIdentities);

        RealClubExternalIdentity::factory()->create(['real_club_id' => $club->id, 'provider' => 'provider-a', 'external_id' => 'one']);
        RealClubExternalIdentity::factory()->create(['real_club_id' => $club->id, 'provider' => 'provider-b', 'external_id' => 'two']);
        RealClubExternalIdentity::factory()->create(['real_club_id' => $club->id, 'provider' => 'provider-a', 'external_id' => 'three']);

        $this->assertCount(3, $club->refresh()->externalIdentities);
    }

    public function test_real_club_identity_normalizes_provider_preserves_external_id_and_rejects_duplicates(): void
    {
        $identity = RealClubExternalIdentity::factory()->create([
            'provider' => ' Provider.X ',
            'external_id' => 'Club-ABC_001',
        ]);

        $this->assertSame('provider.x', $identity->provider);
        $this->assertSame('Club-ABC_001', $identity->external_id);

        $this->expectException(ValidationException::class);
        RealClubExternalIdentity::factory()->create([
            'real_club_id' => RealClub::factory(),
            'provider' => 'PROVIDER.X',
            'external_id' => 'Club-ABC_001',
        ]);
    }

    public function test_deleting_real_club_cascades_external_identities(): void
    {
        $identity = RealClubExternalIdentity::factory()->create();

        $identity->realClub->delete();

        $this->assertDatabaseMissing('real_club_external_identities', ['id' => $identity->id]);
    }

    public function test_season_club_accepts_complete_or_empty_external_identity_and_normalizes_provider(): void
    {
        $empty = SeasonClub::factory()->create();
        $complete = SeasonClub::factory()->create([
            'external_provider' => ' Provider-X ',
            'external_id' => 'ABC-001',
        ]);

        $this->assertNull($empty->external_provider);
        $this->assertNull($empty->external_id);
        $this->assertSame('provider-x', $complete->external_provider);
        $this->assertSame('ABC-001', $complete->external_id);
    }

    public function test_season_club_rejects_incomplete_and_duplicate_external_identities(): void
    {
        foreach (
            [
                ['external_provider' => 'provider-x', 'external_id' => null],
                ['external_provider' => null, 'external_id' => '123'],
            ] as $state
        ) {
            try {
                SeasonClub::factory()->create($state);
                $this->fail('An incomplete SeasonClub external identity was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        SeasonClub::factory()->create(['external_provider' => 'provider-x', 'external_id' => '123']);

        $this->expectException(ValidationException::class);
        SeasonClub::factory()->create(['external_provider' => ' PROVIDER-X ', 'external_id' => '123']);
    }

    public function test_registration_accepts_complete_or_empty_external_identity_and_normalizes_provider(): void
    {
        $empty = PlayerSeasonRegistration::factory()->create();
        $complete = PlayerSeasonRegistration::factory()->create([
            'external_provider' => ' Provider-X ',
            'external_id' => 'ABC-001',
        ]);

        $this->assertNull($empty->external_provider);
        $this->assertNull($empty->external_id);
        $this->assertSame('provider-x', $complete->external_provider);
        $this->assertSame('ABC-001', $complete->external_id);
    }

    public function test_registration_rejects_incomplete_and_duplicate_external_identities(): void
    {
        foreach (
            [
                ['external_provider' => 'provider-x', 'external_id' => null],
                ['external_provider' => null, 'external_id' => '123'],
            ] as $state
        ) {
            try {
                PlayerSeasonRegistration::factory()->create($state);
                $this->fail('An incomplete registration external identity was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        PlayerSeasonRegistration::factory()->create(['external_provider' => 'provider-x', 'external_id' => '123']);

        $this->expectException(ValidationException::class);
        PlayerSeasonRegistration::factory()->create(['external_provider' => ' PROVIDER-X ', 'external_id' => '123']);
    }
}
