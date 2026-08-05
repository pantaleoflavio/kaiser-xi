<?php

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
                    'budget_rules_mutable',
                    'roster_size_mutable',
                    'roster_role_limits_mutable',
            ] as $key) {
                DB::table('league_settings')->insertOrIgnore([
                    'league_id' => $league->id,
                    'key' => $key,
                    'value' => json_encode(['enabled' => false], JSON_THROW_ON_ERROR),
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
            'budget_rules_mutable',
            'roster_size_mutable',
            'roster_role_limits_mutable',
        ])->delete();
    }
};
