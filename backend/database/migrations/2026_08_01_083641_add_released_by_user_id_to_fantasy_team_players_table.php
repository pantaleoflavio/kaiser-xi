<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fantasy_team_players', function (Blueprint $table) {
            $table->foreignId('released_by_user_id')
                ->nullable()
                ->after('assigned_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fantasy_team_players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by_user_id');
        });
    }
};
