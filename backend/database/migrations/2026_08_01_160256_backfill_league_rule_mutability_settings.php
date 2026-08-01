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
            foreach ([
                LeagueSetting::BUDGET_RULES_MUTABLE,
                LeagueSetting::ROSTER_SIZE_MUTABLE,
                LeagueSetting::ROSTER_ROLE_LIMITS_MUTABLE,
            ] as $key) {
                DB::table('league_settings')->insertOrIgnore([
                    'league_id' => $league->id,
                    'key' => $key,
                    'value' => json_encode(LeagueSetting::booleanPayload(false), JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('league_settings')->whereIn('key', [
            LeagueSetting::BUDGET_RULES_MUTABLE,
            LeagueSetting::ROSTER_SIZE_MUTABLE,
            LeagueSetting::ROSTER_ROLE_LIMITS_MUTABLE,
        ])->delete();
    }
};
