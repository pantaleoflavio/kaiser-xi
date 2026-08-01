<?php

use App\Models\LeagueSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         DB::table('leagues')->orderBy('id')->each(function (object $league): void {
            $now = now();
            foreach ([
                LeagueSetting::MAX_ROSTER_PLAYERS => LeagueSetting::integerPayload(
                    LeagueSetting::MAX_ROSTER_PLAYERS,
                    LeagueSetting::DEFAULT_MAX_ROSTER_PLAYERS,
                ),
                LeagueSetting::ROSTER_ROLE_LIMITS => LeagueSetting::roleLimitsPayload(
                    LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS,
                ),
            ] as $key => $value) {
                DB::table('league_settings')->insertOrIgnore([
                    'league_id' => $league->id,
                    'key' => $key,
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('league_settings')
            ->whereIn('key', [LeagueSetting::MAX_ROSTER_PLAYERS, LeagueSetting::ROSTER_ROLE_LIMITS])
            ->delete();
    }
};
