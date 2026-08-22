<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fantasy_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('logo_path')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->decimal('remaining_budget', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['league_id', 'user_id'], 'fantasy_teams_league_id_user_id_unique');
            $table->unique(['league_id', 'slug'], 'fantasy_teams_league_id_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fantasy_teams');
    }
};
