<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fantasy_team_players', function (Blueprint $table) {
            $table->id();

            $table->foreignId('league_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('fantasy_team_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('player_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('assigned_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('released_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('purchase_price', 10, 2)->default(0);

            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();

            $table->timestamps();
        });

        DB::statement(
            'CREATE UNIQUE INDEX fantasy_team_players_active_league_player_unique
             ON fantasy_team_players (league_id, player_id)
             WHERE released_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('fantasy_team_players');
    }
};
