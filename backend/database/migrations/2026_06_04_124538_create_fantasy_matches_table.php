<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fantasy_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matchday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_fantasy_team_id')->constrained('fantasy_teams')->restrictOnDelete();
            $table->foreignId('away_fantasy_team_id')->constrained('fantasy_teams')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['league_id', 'matchday_id', 'home_fantasy_team_id', 'away_fantasy_team_id'],
                'fantasy_matches_exact_fixture_unique'
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE fantasy_matches ADD CONSTRAINT fantasy_matches_distinct_teams_check '
                    . 'CHECK (home_fantasy_team_id <> away_fantasy_team_id)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fantasy_matches');
    }
};
