<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_team_id')->constrained('fantasy_teams')->restrictOnDelete();
            $table->foreignId('to_team_id')->constrained('fantasy_teams')->restrictOnDelete();
            $table->foreignId('offered_fantasy_team_player_id')->nullable()->constrained('fantasy_team_players')->restrictOnDelete();
            $table->foreignId('requested_fantasy_team_player_id')->nullable()->constrained('fantasy_team_players')->restrictOnDelete();
            $table->foreignId('cash_paid_by_team_id')->nullable()->constrained('fantasy_teams')->nullOnDelete();
            $table->decimal('cash_amount', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_proposals');
    }
};
