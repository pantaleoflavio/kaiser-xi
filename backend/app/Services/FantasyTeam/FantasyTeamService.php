<?php

namespace App\Services\FantasyTeam;

use App\Exceptions\DuplicateFantasyTeamOwnershipException;
use App\Exceptions\LeagueScheduleAlreadyInitializedException;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FantasyTeamService
{
    public function create(League $league, User $user, string $name): FantasyTeam
    {
        return DB::transaction(function () use ($league, $user, $name): FantasyTeam {
            $lockedLeague = League::query()->whereKey($league->id)->lockForUpdate()->firstOrFail();
            if ($lockedLeague->hasStartedFantasyCompetition()) {
                throw new LeagueScheduleAlreadyInitializedException;
            }
            if ($lockedLeague->fantasyTeams()->where('user_id', $user->id)->exists()) {
                throw new DuplicateFantasyTeamOwnershipException;
            }
            return FantasyTeam::query()->create([
                'league_id' => $lockedLeague->id,
                'user_id' => $user->id,
                'name' => $name,
                'slug' => $this->generateUniqueSlug($lockedLeague, $name),
                'logo_path' => null,
                'budget' => $lockedLeague->initialFantasyBudget(),
                'remaining_budget' => $lockedLeague->initialFantasyBudget(),
            ]);
        });
    }

    private function generateUniqueSlug(League $league, string $name, ?FantasyTeam $ignoreTeam = null): string
    {
        $baseSlug = Str::slug($name) ?: 'team';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($league, $slug, $ignoreTeam)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    public function update(FantasyTeam $fantasyTeam, string $name): FantasyTeam
    {
        $fantasyTeam->update([
            'name' => $name,
            'slug' => $this->generateUniqueSlug($fantasyTeam->league, $name, $fantasyTeam),
        ]);

        return $fantasyTeam;
    }

    private function slugExists(League $league, string $slug, ?FantasyTeam $ignoreTeam): bool
    {
        return $league->fantasyTeams()
            ->where('slug', $slug)
            ->when($ignoreTeam, fn($query) => $query->whereKeyNot($ignoreTeam->id))
            ->exists();
    }
}
