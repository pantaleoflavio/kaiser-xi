<?php

use App\Models\LeagueSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leagues')->orderBy('id')->each(function (object $league): void {
            $now = now();
            $defaults = [
                LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES => LeagueSetting::stringListPayload(LeagueSetting::DEFAULT_ALLOWED_FORMATION_MODULE_NAMES),
                LeagueSetting::BENCH_SIZE => LeagueSetting::integerPayload(LeagueSetting::BENCH_SIZE, LeagueSetting::DEFAULT_BENCH_SIZE),
                LeagueSetting::BENCH_ROLE_LIMITS => LeagueSetting::roleLimitsPayload(LeagueSetting::DEFAULT_BENCH_ROLE_LIMITS),
                LeagueSetting::MAX_SUBSTITUTIONS => LeagueSetting::integerPayload(LeagueSetting::MAX_SUBSTITUTIONS, LeagueSetting::DEFAULT_MAX_SUBSTITUTIONS),
                LeagueSetting::SUBSTITUTION_ORDER_MODE => LeagueSetting::stringPayload(LeagueSetting::DEFAULT_SUBSTITUTION_ORDER_MODE),
                LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION => LeagueSetting::booleanPayload(LeagueSetting::DEFAULT_ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION),
                LeagueSetting::CAPTAIN_ENABLED => LeagueSetting::booleanPayload(LeagueSetting::DEFAULT_CAPTAIN_ENABLED),
                LeagueSetting::VICE_CAPTAIN_ENABLED => LeagueSetting::booleanPayload(LeagueSetting::DEFAULT_VICE_CAPTAIN_ENABLED),
            ];

            foreach ($defaults as $key => $value) {
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

    public function down(): void
    {
        DB::table('league_settings')->whereIn('key', [
            LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES,
            LeagueSetting::BENCH_SIZE,
            LeagueSetting::BENCH_ROLE_LIMITS,
            LeagueSetting::MAX_SUBSTITUTIONS,
            LeagueSetting::SUBSTITUTION_ORDER_MODE,
            LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION,
            LeagueSetting::CAPTAIN_ENABLED,
            LeagueSetting::VICE_CAPTAIN_ENABLED,
        ])->delete();
    }
};