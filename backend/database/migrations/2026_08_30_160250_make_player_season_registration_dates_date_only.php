<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_season_registrations', function (Blueprint $table): void {
            $table->renameColumn('registered_at', 'registered_on');
            $table->renameColumn('released_at', 'released_on');
        });

        DB::statement('ALTER TABLE player_season_registrations ALTER COLUMN registered_on TYPE DATE USING registered_on::date');
        DB::statement('ALTER TABLE player_season_registrations ALTER COLUMN released_on TYPE DATE USING released_on::date');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE player_season_registrations ALTER COLUMN registered_on TYPE TIMESTAMP WITHOUT TIME ZONE USING registered_on::timestamp');
        DB::statement('ALTER TABLE player_season_registrations ALTER COLUMN released_on TYPE TIMESTAMP WITHOUT TIME ZONE USING released_on::timestamp');

        Schema::table('player_season_registrations', function (Blueprint $table): void {
            $table->renameColumn('registered_on', 'registered_at');
            $table->renameColumn('released_on', 'released_at');
        });
    }
};
