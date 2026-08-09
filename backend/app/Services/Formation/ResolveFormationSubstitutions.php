<?php

namespace App\Services\Formation;

use App\Data\Formation\ResolvedFormation;
use App\Data\Formation\ResolvedSubstitution;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\FormationPlayer;
use App\Models\LeagueSetting;
use App\Models\PlayerScore;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use LogicException;

final class ResolveFormationSubstitutions
{
    public function __construct(private SubstitutionCandidateOrder $candidateOrder) {}

    public function resolve(Formation $formation): ResolvedFormation
    {
        if (! $formation->is_confirmed || $formation->submitted_at === null) {
            throw new LogicException('Only submitted formations can have substitutions resolved.');
        }

        $formation->loadMissing([
            'players.fantasyTeamPlayer.player',
            'players.playerRole',
            'formationModule.requirements.playerRole',
            'matchday',
            'league',
        ]);

        $starters = $this->ordered($formation->players->where('slot_type', 'starter'));
        $bench = $this->ordered($formation->players->where('slot_type', 'bench'));
        $playablePlayerIds = $this->playablePlayerIds($formation, $formation->players);
        $playableBench = $bench->filter(
            fn (FormationPlayer $player): bool => isset($playablePlayerIds[$player->player_id])
        )->values();

        $originalModule = $formation->formationModule;
        $allowFormationChange = $formation->league->allowsFormationChangeOnSubstitution();
        $modules = $this->candidateModules($formation, $allowFormationChange);
        $effectiveModule = $originalModule;
        $effectiveStarters = $starters;
        $usedBenchIds = [];
        $substitutions = collect();
        $unresolved = collect();
        $maximum = max(0, $formation->league->maxSubstitutions());

        foreach ($starters as $outgoing) {
            if (isset($playablePlayerIds[$outgoing->player_id])) {
                continue;
            }

            if ($substitutions->count() >= $maximum) {
                $unresolved->push($outgoing);

                continue;
            }

            $availableBench = $playableBench->reject(
                fn (FormationPlayer $candidate): bool => isset($usedBenchIds[$candidate->id])
            )->values();
            $candidates = $formation->league->substitutionOrderMode() === LeagueSetting::SUBSTITUTION_ORDER_ROLE_PRIORITY
                ? $this->candidateOrder->rolePriority($availableBench, $outgoing->player_role_id, $allowFormationChange)
                : $availableBench;

            $replacement = null;
            $replacementModule = null;
            foreach ($candidates as $candidate) {
                $proposed = $effectiveStarters
                    ->map(fn (FormationPlayer $player): FormationPlayer => $player->id === $outgoing->id ? $candidate : $player)
                    ->values();
                $matchingModule = $this->matchingModule($proposed, $modules, $originalModule);
                if ($matchingModule !== null) {
                    $replacement = $candidate;
                    $replacementModule = $matchingModule;

                    break;
                }
            }

            if ($replacement === null || $replacementModule === null) {
                $unresolved->push($outgoing);

                continue;
            }

            $effectiveStarters = $effectiveStarters
                ->map(fn (FormationPlayer $player): FormationPlayer => $player->id === $outgoing->id ? $replacement : $player)
                ->values();
            $effectiveModule = $replacementModule;
            $usedBenchIds[$replacement->id] = true;
            $substitutions->push(new ResolvedSubstitution(
                $outgoing,
                $replacement,
                $replacement->position_index,
                $effectiveModule,
            ));
        }

        return new ResolvedFormation(
            $originalModule,
            $effectiveModule,
            $effectiveStarters,
            $bench->reject(fn (FormationPlayer $player): bool => isset($usedBenchIds[$player->id]))->values(),
            $substitutions,
            $unresolved,
        );
    }

    /**
     * Resolve all relevant performances in one query, constrained through the matchday season.
     *
     * @param  Collection<int, FormationPlayer>  $formationPlayers
     * @return array<int, true>
     */
    private function playablePlayerIds(Formation $formation, Collection $formationPlayers): array
    {
        $scores = PlayerScore::query()
            ->select('player_scores.*', 'player_season_registrations.player_id as registration_player_id')
            ->join('player_season_registrations', 'player_season_registrations.id', '=', 'player_scores.player_season_registration_id')
            ->join('season_clubs', 'season_clubs.id', '=', 'player_season_registrations.season_club_id')
            ->where('player_scores.matchday_id', $formation->matchday_id)
            ->where('season_clubs.season_id', $formation->matchday->season_id)
            ->whereIn('player_season_registrations.player_id', $formationPlayers->pluck('player_id')->unique()->all())
            ->orderBy('player_scores.id')
            ->get();

        $byPlayer = $scores->groupBy('registration_player_id');
        $playable = [];
        foreach ($byPlayer as $playerId => $playerScores) {
            if ($playerScores->count() > 1) {
                throw new LogicException("Multiple PlayerScores exist for player {$playerId} on formation matchday {$formation->matchday_id}.");
            }
            if ($playerScores->first()?->isPlayable()) {
                $playable[(int) $playerId] = true;
            }
        }

        return $playable;
    }

    /** @return Collection<int, FormationPlayer> */
    private function ordered(Collection $players): Collection
    {
        return $players->sortBy([
            ['position_index', 'asc'],
            ['id', 'asc'],
        ])->values();
    }

    /** @return EloquentCollection<int, FormationModule> */
    private function candidateModules(Formation $formation, bool $allowFormationChange): EloquentCollection
    {
        if (! $allowFormationChange) {
            return new EloquentCollection([$formation->formationModule]);
        }

        $modules = FormationModule::query()
            ->with('requirements.playerRole')
            ->whereIn('name', $formation->league->allowedFormationModuleNames())
            ->orderBy('id')
            ->orderBy('name')
            ->get();

        if (! $modules->contains('id', $formation->formation_module_id)) {
            $modules->prepend($formation->formationModule);
        }

        return $modules;
    }

    /**
     * @param  Collection<int, FormationPlayer>  $starters
     * @param  Collection<int, FormationModule>  $modules
     */
    private function matchingModule(Collection $starters, Collection $modules, FormationModule $original): ?FormationModule
    {
        $counts = $starters->countBy('player_role_id')->map(fn (int $count): int => $count)->all();
        ksort($counts);

        return $modules
            ->sortBy(fn (FormationModule $module): array => [$module->id === $original->id ? 0 : 1, $module->id, $module->name])
            ->first(function (FormationModule $module) use ($counts): bool {
                $required = $module->requirements
                    ->mapWithKeys(fn ($requirement): array => [(int) $requirement->player_role_id => (int) $requirement->required_count])
                    ->all();
                ksort($required);

                return $counts === $required;
            });
    }
}
