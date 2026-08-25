<?php

namespace App\Services\Auth;

use App\Models\LeagueStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeleteAccountService
{
    public function execute(User $user): void
    {
        $ownsUnfinishedLeague = $user->commissionedLeagues()
            ->whereHas('status', fn($query) => $query->whereNotIn('key', [LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED]))
            ->exists();

        if ($ownsUnfinishedLeague) {
            throw ValidationException::withMessages([
                'account' => ['Transfer or finish every active league you commission before deleting your account.'],
            ]);
        }

        DB::transaction(function () use ($user): void {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->tokens()->delete();
            $user->roles()->detach();

            $user->forceFill([
                'name' => 'Deleted user',
                'email' => 'deleted-' . Str::uuid() . '@deleted.invalid',
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'theme' => null,
            ])->save();
        });
    }
}
