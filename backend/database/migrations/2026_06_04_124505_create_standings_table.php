<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fantasy_team_id')->constrained()->cascadeOnDelete();
            $table->integer('points_total')->default(0);
            $table->decimal('fantasy_points_total', 10, 2)->default(0);
            $table->decimal('average_points', 10, 4)->default(0);
            $table->decimal('best_matchday_score', 10, 2)->default(0);
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('draws')->default(0);
            $table->unsignedSmallInteger('losses')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
            $table->unsignedSmallInteger('position')->default(1);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['league_id', 'fantasy_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standings');
    }
};
