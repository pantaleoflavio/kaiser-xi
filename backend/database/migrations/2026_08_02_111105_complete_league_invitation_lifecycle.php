<?php

use App\Models\LeagueRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE league_invitations DROP CONSTRAINT league_invitations_status_check');
        }

        Schema::table('league_invitations', function (Blueprint $table): void {
            $table->foreignId('invited_user_id')->nullable()->after('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('league_role_id')->nullable()->after('invited_user_id')->constrained('league_roles')->restrictOnDelete();
        });

        DB::table('league_invitations')->where('status', 'active')->update(['status' => 'pending']);
        DB::table('league_invitations')->where('status', 'cancelled')->update(['status' => 'revoked']);
        DB::table('league_invitations')->whereNull('league_role_id')->update([
            'league_role_id' => LeagueRole::query()->where('key', 'participant')->value('id'),
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE league_invitations ADD CONSTRAINT league_invitations_status_check CHECK (status IN ('pending', 'accepted', 'rejected', 'revoked'))");
            DB::statement("CREATE UNIQUE INDEX league_invitations_active_recipient_unique ON league_invitations (league_id, invited_user_id) WHERE status = 'pending'");
        } else {
            Schema::table('league_invitations', function (Blueprint $table): void {
                $table->index(['league_id', 'invited_user_id', 'status'], 'league_invitations_recipient_status_index');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS league_invitations_active_recipient_unique');
            DB::statement('ALTER TABLE league_invitations DROP CONSTRAINT league_invitations_status_check');
        }
        DB::table('league_invitations')->where('status', 'pending')->update(['status' => 'active']);
        DB::table('league_invitations')->whereIn('status', ['accepted', 'rejected', 'revoked'])->update(['status' => 'cancelled']);
        Schema::table('league_invitations', function (Blueprint $table): void {
            $table->dropForeign(['invited_user_id']);
            $table->dropForeign(['league_role_id']);
            $table->dropColumn(['invited_user_id', 'league_role_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE league_invitations ADD CONSTRAINT league_invitations_status_check CHECK (status IN ('active', 'cancelled'))");
        }
    }
};