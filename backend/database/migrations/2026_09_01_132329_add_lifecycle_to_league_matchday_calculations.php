<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_matchday_calculations', function (Blueprint $table): void {
            $table->string('status')->default('completed')->after('matchday_id');
            $table->uuid('execution_token')->nullable()->after('status');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('calculated_at')->nullable()->change();
        });
        DB::table('league_matchday_calculations')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('league_matchday_calculations', function (Blueprint $table): void {
            $table->dropColumn(['status', 'execution_token', 'queued_at', 'started_at', 'failed_at', 'failure_message']);
            $table->timestamp('calculated_at')->nullable(false)->change();
        });
    }
};
