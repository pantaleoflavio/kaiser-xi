<?php

namespace App\Services\Formation;

use App\Exceptions\IncompleteLeagueConfigurationException;
use App\Exceptions\LineupDeadlinePassedException;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\Matchday;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveFormationService
{
    /** @param array{formation_module_id: int, starters: list<int>, bench: list<array{fantasy_team_player_id: int, order: int}>, captain_fantasy_team_player_id: ?int} $data */
    public function save(League $league, Matchday $matchday, FantasyTeam $team, array $data): Formation
    {
        $this->assertContext($league, $matchday, $team);
        $this->assertBeforeDeadline($matchday);

        return DB::transaction(function () use ($league, $matchday, $team, $data): Formation {
            $module = FormationModule::query()->with('requirements.playerRole')->findOrFail($data['formation_module_id']);
            $this->validateModule($league, $module);

            $starterIds = array_values($data['starters']);
            $bench = collect($data['bench'])->sortBy('order')->values();
            $benchIds = $bench->pluck('fantasy_team_player_id')->all();
            $allIds = [...$starterIds, ...$benchIds];
            if (count($allIds) !== count(array_unique($allIds))) {
                throw ValidationException::withMessages(['players' => 'A roster assignment may appear only once in the formation.']);
            }

            $assignments = FantasyTeamPlayer::query()
                ->whereIn('id', $allIds)
                ->with(['player.playerSeasonRegistrations' => fn ($query) => $query->activeForSeason($league->season_id)->with('playerRole')])
                ->get()->keyBy('id');

            if ($assignments->count() !== count($allIds)) {
                throw ValidationException::withMessages(['players' => 'Every selected roster assignment must exist.']);
            }

            $roles = [];
            foreach ($allIds as $assignmentId) {
                $assignment = $assignments->get($assignmentId);
                $registration = $assignment?->player?->playerSeasonRegistrations?->first();
                if (! $assignment || $assignment->league_id !== $league->id || $assignment->fantasy_team_id !== $team->id
                    || $assignment->released_at !== null || $assignment->assigned_at->isFuture() || ! $registration) {
                    throw ValidationException::withMessages(['players' => 'Every player must have an active assignment and registration for this fantasy team and league season.']);
                }
                $roles[$assignmentId] = $registration->playerRole;
            }

            $required = $module->requirements->mapWithKeys(fn ($requirement): array => [$requirement->playerRole->key => (int) $requirement->required_count])->all();
            $actual = collect($starterIds)->countBy(fn (int $id): string => $roles[$id]->key)->all();
            ksort($required);
            ksort($actual);
            if ($actual !== $required) {
                throw ValidationException::withMessages(['starters' => 'Starter role composition must exactly match the formation module requirements.']);
            }

            if ($bench->count() > $league->benchSize()) {
                throw ValidationException::withMessages(['bench' => 'The bench exceeds the league bench size.']);
            }
            $expectedOrders = $bench->isEmpty() ? [] : range(1, $bench->count());
            if ($bench->pluck('order')->all() !== $expectedOrders) {
                throw ValidationException::withMessages(['bench' => 'Bench orders must be contiguous and start at one.']);
            }
            $benchRoleCounts = collect($benchIds)->countBy(fn (int $id): string => $roles[$id]->key);
            foreach ($benchRoleCounts as $role => $count) {
                if ($count > ($league->benchRoleLimits()[$role] ?? -1)) {
                    throw ValidationException::withMessages(['bench' => "The bench exceeds the {$role} role limit."]);
                }
            }

            $captainId = $data['captain_fantasy_team_player_id'] ?? null;
            if ($captainId !== null && ! $league->captainEnabled()) {
                throw ValidationException::withMessages(['captain_fantasy_team_player_id' => 'Captain selection is disabled for this league.']);
            }
            if ($captainId !== null && ! in_array($captainId, $starterIds, true)) {
                throw ValidationException::withMessages(['captain_fantasy_team_player_id' => 'The captain must be a selected starter.']);
            }

            $formation = Formation::query()->firstOrNew(['fantasy_team_id' => $team->id, 'matchday_id' => $matchday->id]);
            $formation->fill(['league_id' => $league->id, 'formation_module_id' => $module->id, 'is_auto_generated' => false]);
            $formation->save();
            $formation->players()->delete();
            foreach ($starterIds as $index => $assignmentId) {
                $this->createPlayer($formation, $assignments[$assignmentId], $roles[$assignmentId]->id, 'starter', $index + 1, $captainId === $assignmentId);
            }
            foreach ($bench as $slot) {
                $assignmentId = $slot['fantasy_team_player_id'];
                $this->createPlayer($formation, $assignments[$assignmentId], $roles[$assignmentId]->id, 'bench', $slot['order'], false);
            }

            return $formation->load($this->relations());
        });
    }

    public function assertBeforeDeadline(Matchday $matchday): void
    {
        if (now()->greaterThanOrEqualTo($matchday->starts_at)) {
            throw new LineupDeadlinePassedException;
        }
    }

    private function assertContext(League $league, Matchday $matchday, FantasyTeam $team): void
    {
        abort_unless($team->league_id === $league->id && $matchday->season_id === $league->season_id, 404);
    }

    private function validateModule(League $league, FormationModule $module): void
    {
        if (! $module->is_active || ! in_array($module->name, $league->allowedFormationModuleNames(), true)) {
            throw ValidationException::withMessages(['formation_module_id' => 'The formation module is not allowed in this league.']);
        }
        $keys = $module->requirements->pluck('playerRole.key')->all();
        if (count($keys) !== count(LeagueSetting::PLAYER_ROLE_KEYS) || array_diff(LeagueSetting::PLAYER_ROLE_KEYS, $keys) !== []) {
            throw new IncompleteLeagueConfigurationException('The formation module requirements are incomplete.');
        }
    }

    private function createPlayer(Formation $formation, FantasyTeamPlayer $assignment, int $roleId, string $type, int $position, bool $captain): void
    {
        $formation->players()->create(['fantasy_team_player_id' => $assignment->id, 'player_id' => $assignment->player_id, 'player_role_id' => $roleId, 'slot_type' => $type, 'position_index' => $position, 'is_captain' => $captain]);
    }

    /** @return list<string> */
    public function relations(): array
    {
        return ['formationModule.requirements.playerRole', 'players.player', 'players.playerRole', 'matchday'];
    }
}
