<?php

namespace App\Http\Resources\League;

use App\Models\LeagueStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'initial_budget' => $this->initialFantasyBudget(),
            'release_refund_percentage' => $this->releaseRefundPercentage(),
            'max_roster_players' => $this->maxRosterPlayers(),
            'roster_role_limits' => $this->rosterRoleLimits(),
            'budget_rules_mutable' => $this->budgetRulesMutable(),
            'roster_size_mutable' => $this->rosterSizeMutable(),
            'roster_role_limits_mutable' => $this->rosterRoleLimitsMutable(),
            'status' => $this->statusKey(),
            'can_update_settings' => $request->user()?->can('manageSettings', $this->resource)
                && ! in_array($this->statusKey(), [LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED], true),
            'can_activate' => $request->user()?->can('activate', $this->resource) && $this->isPreActivation(),
            'locked_rule_groups' => $this->lockedRuleGroups(),
        ];
    }

    /** @return list<string> */
    private function lockedRuleGroups(): array
    {
        if ($this->isPreActivation()) {
            return [];
        }

        if (in_array($this->statusKey(), [LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED], true)) {
            return ['budget', 'roster_size', 'roster_role_limits'];
        }

        return array_values(array_filter([
            $this->budgetRulesMutable() ? null : 'budget',
            $this->rosterSizeMutable() ? null : 'roster_size',
            $this->rosterRoleLimitsMutable() ? null : 'roster_role_limits',
        ]));
    }
}
