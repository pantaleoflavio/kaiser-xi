<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_matchday_calculations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matchday_id')->constrained()->cascadeOnDelete();
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['league_id', 'matchday_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_matchday_calculations');
    }
};
