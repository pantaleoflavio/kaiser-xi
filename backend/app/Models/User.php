<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class)
            ->using(LeagueMembership::class)
            ->withPivot('league_role_id', 'joined_at')
            ->withTimestamps();
    }

    public function leagueMemberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    public function fantasyTeams(): HasMany
    {
        return $this->hasMany(FantasyTeam::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasGlobalRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function highestGlobalRoleLevel(): int
    {
        return (int) $this->roles()->max('level');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasGlobalRole('super_admin');
    }

    public function isGlobalAdmin(): bool
    {
        return $this->hasGlobalRole('global_admin');
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->isSuperAdmin() || $this->isGlobalAdmin();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->canAccessAdminPanel();
    }
}
