<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->foreignId('league_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('league_status_id')->constrained()->restrictOnDelete();
            $table->foreignId('commissioner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('max_participants')->default(10);
            $table->foreignId('h2h_start_matchday_id')->nullable()->constrained('matchdays')->restrictOnDelete();
            $table->timestamp('h2h_schedule_generated_at')->nullable();
            $table->foreignId('championship_start_matchday_id')->nullable()->constrained('matchdays')->restrictOnDelete();
            $table->timestamp('championship_started_at')->nullable();
            $table->timestamps();
            $table->unique(['season_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
