<?php

namespace Tests\Feature\Api\V1;

use App\Http\Resources\Formation\MatchdayResource;
use App\Models\Matchday;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MatchdayTimezoneSerializationTest extends TestCase
{
    #[DataProvider('berlinLocalTimes')]
    public function test_matchday_admin_local_time_is_serialized_as_the_corresponding_utc_instant(
        string $localDateTime,
        string $expectedUtc,
    ): void {
        $instant = CarbonImmutable::parse($localDateTime, config('app.display_timezone'))->utc();
        $matchday = new Matchday([
            'id' => 1,
            'number' => 1,
            'name' => 'Matchday 1',
            'starts_at' => $instant,
            'ends_at' => $instant->addHours(2),
        ]);

        $payload = json_decode((new MatchdayResource($matchday))->response()->getContent(), true);

        $this->assertSame($expectedUtc, $payload['data']['starts_at']);
    }

    public static function berlinLocalTimes(): array
    {
        return [
            'CEST uses UTC plus two' => ['2026-09-01 13:00:00', '2026-09-01T11:00:00.000000Z'],
            'CET uses UTC plus one' => ['2026-01-15 13:00:00', '2026-01-15T12:00:00.000000Z'],
        ];
    }
}
