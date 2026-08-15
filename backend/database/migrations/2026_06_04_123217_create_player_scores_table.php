<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_season_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matchday_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_rating', 4, 2)->nullable();
            $table->integer('goals')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('yellow_cards')->default(0);
            $table->integer('red_cards')->default(0);
            $table->integer('own_goals')->default(0);
            $table->integer('penalties_scored')->default(0);
            $table->integer('penalties_missed')->default(0);
            $table->integer('penalties_saved')->default(0);
            $table->integer('goals_conceded')->default(0);
            $table->boolean('clean_sheet')->default(false);
            // Whether the player captained his real club in this matchday performance.
            $table->boolean('is_captain')->default(false);
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['matchday_id', 'player_season_registration_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE player_scores ADD CONSTRAINT player_scores_status_check CHECK (status IN ('pending', 'confirmed', 'did_not_play'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_scores');
    }
};
