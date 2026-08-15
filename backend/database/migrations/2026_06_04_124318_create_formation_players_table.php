<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('fantasy_team_player_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('player_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('player_role_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('slot_type');
            $table->unsignedSmallInteger('position_index');
            $table->timestamps();
            $table->unique(
                ['formation_id', 'fantasy_team_player_id']
            );
            $table->unique(
                ['formation_id', 'player_id']
            );
            $table->unique(
                ['formation_id', 'slot_type', 'position_index']
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_players');
    }
};
