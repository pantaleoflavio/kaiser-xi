<?php

namespace Tests\Feature\Domain;

use App\Models\Player;
use App\Models\PlayerSeasonRegistration;
use App\Models\Season;
use App\Models\SeasonClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalendarDateSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_and_season_calendar_dates_serialize_without_times(): void
    {
        $player = Player::factory()->create(['birth_date' => '2000-01-02']);
        $season = Season::factory()->create(['starts_at' => '2026-08-31', 'ends_at' => '2027-05-30']);

        $this->assertSame('2000-01-02', $player->toArray()['birth_date']);
        $this->assertSame('2026-08-31', $season->toArray()['starts_at']);
        $this->assertSame('2027-05-30', $season->toArray()['ends_at']);
        $this->assertSame('2000-01-02', DB::table('players')->where('id', $player->id)->value('birth_date'));
        $this->assertSame('2026-08-31', DB::table('seasons')->where('id', $season->id)->value('starts_at'));
    }

    public function test_release_and_registration_can_share_the_same_calendar_date(): void
    {
        $season = Season::factory()->create();
        $player = Player::factory()->create();
        $oldClub = SeasonClub::factory()->create(['season_id' => $season->id]);
        $newClub = SeasonClub::factory()->create(['season_id' => $season->id]);
        $old = PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => $oldClub->id,
            'registered_on' => '2026-08-01',
            'released_on' => '2026-08-31',
            'is_active' => false,
        ]);
        $new = PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => $newClub->id,
            'registered_on' => '2026-08-31',
            'released_on' => null,
            'is_active' => true,
        ]);

        $this->assertSame('2026-08-31', $old->released_on->format('Y-m-d'));
        $this->assertSame('2026-08-31', $new->registered_on->format('Y-m-d'));
        $this->assertSame('2026-08-31', DB::table('player_season_registrations')->where('id', $old->id)->value('released_on'));
        $this->assertSame('2026-08-31', DB::table('player_season_registrations')->where('id', $new->id)->value('registered_on'));
    }
}
