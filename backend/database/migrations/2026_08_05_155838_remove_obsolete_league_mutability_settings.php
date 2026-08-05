<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('league_settings')
            ->whereIn('key', [
                'budget_rules_mutable',
                'roster_size_mutable',
                'roster_role_limits_mutable',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Obsolete lifecycle mutability flags are intentionally not restored.
    }
};