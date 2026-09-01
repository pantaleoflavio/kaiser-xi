<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchdays', function (Blueprint $table): void {
            $table->timestamp('calculation_unlocked_at')->nullable()->after('ends_at');
            $table->foreignId('calculation_unlocked_by_user_id')->nullable()->after('calculation_unlocked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matchdays', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('calculation_unlocked_by_user_id');
            $table->dropColumn('calculation_unlocked_at');
        });
    }
};
