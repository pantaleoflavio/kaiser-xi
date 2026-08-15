<?php

namespace App\Http\Resources\League;

use App\Models\FormationModule;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $formationNames = $this->allowedFormationModuleNames();
        $formations = FormationModule::query()
            ->whereIn('name', $formationNames)
            ->with(['requirements.playerRole'])
            ->get()
            ->sortBy('name')
            ->values()
            ->map(function (FormationModule $module): array {
                $requirements = collect(LeagueSetting::PLAYER_ROLE_KEYS)
                    ->mapWithKeys(function (string $role) use ($module): array {
                        $requirement = $module->requirements->first(
                            fn($requirement): bool => $requirement->playerRole?->key === $role
                        );

                        return [$role => (int) ($requirement?->required_count ?? 0)];
                    })
                    ->all();

                return [
                    'id' => $module->id,
                    'name' => $module->name,
                    'label' => $module->label,
                    'required_players_count' => $module->requiredPlayersCount(),
                    'requirements' => $requirements,
                ];
            })
            ->all();

        return [
            'initial_budget' => $this->initialFantasyBudget(),
            'release_refund_percentage' => $this->releaseRefundPercentage(),
            'max_roster_players' => $this->maxRosterPlayers(),
            'roster_role_limits' => $this->rosterRoleLimits(),
            LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES => $formationNames,
            'allowed_formation_modules' => $formations,
            LeagueSetting::BENCH_SIZE => $this->benchSize(),
            LeagueSetting::BENCH_ROLE_LIMITS => $this->benchRoleLimits(),
            LeagueSetting::MAX_SUBSTITUTIONS => $this->maxSubstitutions(),
            LeagueSetting::SUBSTITUTION_ORDER_MODE => $this->substitutionOrderMode(),
            LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION => $this->allowsFormationChangeOnSubstitution(),
            LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED => $this->realCaptainBonusEnabled(),
            LeagueSetting::REAL_CAPTAIN_BONUS_POINTS => $this->realCaptainBonusPoints(),
            LeagueSetting::DEFENSE_MODIFIER_ENABLED => $this->defenseModifierEnabled(),
            LeagueSetting::FIRST_GOAL_THRESHOLD => $this->firstGoalThreshold(),
            LeagueSetting::GOAL_INTERVAL => $this->goalInterval(),
            'status' => $this->statusKey(),
            'can_update_settings' => $request->user()?->can('manageSettings', $this->resource)
                && ! in_array($this->statusKey(), [LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED], true),
            'locked_rule_groups' => $this->lockedRuleGroups(),
        ];
    }

    /** @return list<string> */
    private function lockedRuleGroups(): array
    {
        if (in_array($this->statusKey(), [LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED], true)) {
            return ['budget', 'roster_size', 'roster_role_limits'];
        }

        return [];
    }
}
