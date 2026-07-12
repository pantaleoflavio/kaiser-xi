<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            Schema::table('fantasy_team_players', function (Blueprint $table): void {
            $table->dropUnique(
                'fantasy_team_players_fantasy_team_id_player_id_unique'
            );
        });

        DB::statement(
            'CREATE UNIQUE INDEX fantasy_team_players_active_league_player_unique
             ON fantasy_team_players (league_id, player_id)
             WHERE released_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS fantasy_team_players_active_league_player_unique'
        );

        Schema::table('fantasy_team_players', function (Blueprint $table): void {
            $table->unique(
                ['fantasy_team_id', 'player_id'],
                'fantasy_team_players_fantasy_team_id_player_id_unique'
            );
        });
    }
};
