<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('championship_participants', function (Blueprint $table): void {
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fantasy_team_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['league_id', 'fantasy_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('championship_participants');
    }
};
