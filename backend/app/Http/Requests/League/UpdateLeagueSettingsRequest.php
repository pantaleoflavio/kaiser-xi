<?php

namespace App\Http\Requests\League;

use App\Models\FormationModule;
use App\Models\League;
use App\Models\LeagueSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLeagueSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    protected function prepareForValidation(): void
    {
        $key = LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES;
        if ($this->has($key) && is_array($this->input($key))) {
            $names = array_values(array_unique($this->input($key)));
            sort($names);
            $this->merge([$key => $names]);
        }
    }

    public function rules(): array
    {
        $roleShape = 'array:' . implode(',', LeagueSetting::PLAYER_ROLE_KEYS);
        $requiredRoles = 'required_array_keys:' . implode(',', LeagueSetting::PLAYER_ROLE_KEYS);

        return [
            LeagueSetting::INITIAL_BUDGET => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],

            LeagueSetting::RELEASE_REFUND_PERCENTAGE => [
                'sometimes',
                'required',
                'integer',
                'between:0,100',
            ],

            LeagueSetting::MAX_ROSTER_PLAYERS => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],

            LeagueSetting::ROSTER_ROLE_LIMITS => [
                'sometimes',
                'required',
                $roleShape,
                $requiredRoles,
            ],

            LeagueSetting::ROSTER_ROLE_LIMITS . '.*' => [
                'required',
                'integer',
                'min:0',
            ],

            LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES => [
                'sometimes',
                'required',
                'array',
                'min:1',
            ],

            LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES . '.*' => [
                'required',
                'string',
                'distinct',
            ],

            LeagueSetting::BENCH_SIZE => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],

            LeagueSetting::BENCH_ROLE_LIMITS => [
                'sometimes',
                'required',
                $roleShape,
                $requiredRoles,
            ],

            LeagueSetting::BENCH_ROLE_LIMITS . '.*' => [
                'required',
                'integer',
                'min:0',
            ],

            LeagueSetting::MAX_SUBSTITUTIONS => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],

            LeagueSetting::SUBSTITUTION_ORDER_MODE => [
                'sometimes',
                'required',
                'string',
                Rule::in(LeagueSetting::SUBSTITUTION_ORDER_MODES),
            ],

            LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION => [
                'sometimes',
                'required',
                'boolean',
            ],

            LeagueSetting::CAPTAIN_ENABLED => [
                'sometimes',
                'required',
                'boolean',
            ],

            LeagueSetting::VICE_CAPTAIN_ENABLED => [
                'sometimes',
                'required',
                'boolean',
            ],

            'remaining_budget' => ['prohibited'],
            'league_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $league = $this->route('league');
            if (! $league instanceof League) {
                return;
            }

            $maximum = (int) $this->input(LeagueSetting::MAX_ROSTER_PLAYERS, $league->maxRosterPlayers());
            $rosterLimits = $this->input(LeagueSetting::ROSTER_ROLE_LIMITS, $league->rosterRoleLimits());
            $benchSize = (int) $this->input(LeagueSetting::BENCH_SIZE, $league->benchSize());
            $benchLimits = $this->input(LeagueSetting::BENCH_ROLE_LIMITS, $league->benchRoleLimits());
            $maxSubstitutions = (int) $this->input(LeagueSetting::MAX_SUBSTITUTIONS, $league->maxSubstitutions());
            $captainEnabled = $this->booleanValue(LeagueSetting::CAPTAIN_ENABLED, $league->captainEnabled());
            $viceCaptainEnabled = $this->booleanValue(LeagueSetting::VICE_CAPTAIN_ENABLED, $league->viceCaptainEnabled());

            if (array_sum($rosterLimits) < $maximum) {
                $validator->errors()->add(
                    LeagueSetting::ROSTER_ROLE_LIMITS,
                    'The sum of roster role limits must be greater than or equal to the maximum roster players.'
                );
            }
            if (array_sum($benchLimits) < $benchSize) {
                $validator->errors()->add(LeagueSetting::BENCH_ROLE_LIMITS, 'The sum of bench role limits must be at least the bench size.');
            }

            foreach (LeagueSetting::PLAYER_ROLE_KEYS as $role) {
                if ($benchLimits[$role] > $rosterLimits[$role]) {
                    $validator->errors()->add(
                        LeagueSetting::BENCH_ROLE_LIMITS . '.' . $role,
                        'The bench role limit cannot exceed the corresponding roster role limit.'
                    );
                }
            }

            if ($maxSubstitutions > $benchSize) {
                $validator->errors()->add(LeagueSetting::MAX_SUBSTITUTIONS, 'The maximum substitutions cannot exceed the bench size.');
            }

            if ($viceCaptainEnabled && ! $captainEnabled) {
                $validator->errors()->add(LeagueSetting::VICE_CAPTAIN_ENABLED, 'Vice-captain selection requires captain selection to be enabled.');
            }

            $this->validateFormations($validator, $league, $rosterLimits, $maximum);
        }];
    }

    /** @param array<string, int> $rosterLimits */
    private function validateFormations(Validator $validator, League $league, array $rosterLimits, int $maximum): void
    {
        $names = $this->input(LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES, $league->allowedFormationModuleNames());
        $modules = FormationModule::query()
            ->whereIn('name', $names)
            ->where('is_active', true)
            ->with(['requirements.playerRole'])
            ->get()
            ->keyBy('name');

        if ($modules->count() !== count($names)) {
            $validator->errors()->add(
                LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES,
                'Every allowed formation module must exist and be active.'
            );

            return;
        }

        foreach ($names as $name) {
            $module = $modules->get($name);
            $requirements = $module->requirements
                ->mapWithKeys(fn($requirement): array => [$requirement->playerRole?->key => (int) $requirement->required_count])
                ->all();

            if (
                count($requirements) !== count(LeagueSetting::PLAYER_ROLE_KEYS)
                || array_diff(LeagueSetting::PLAYER_ROLE_KEYS, array_keys($requirements)) !== []
                || collect($requirements)->contains(fn(int $count): bool => $count < 1)
                || $module->requiredPlayersCount() < 1
            ) {
                $validator->errors()->add(
                    LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES,
                    "Formation module {$name} has incomplete or incoherent role requirements."
                );
                continue;
            }

            if ($module->requiredPlayersCount() > $maximum) {
                $validator->errors()->add(
                    LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES,
                    "Formation module {$name} requires more players than the roster maximum."
                );
                continue;
            }

            foreach ($requirements as $role => $requiredCount) {
                if ($requiredCount > $rosterLimits[$role]) {
                    $validator->errors()->add(
                        LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES,
                        "Formation module {$name} cannot be satisfied by the {$role} roster limit."
                    );
                    break;
                }
            }
        }
    }

    private function booleanValue(string $key, bool $default): bool
    {
        return $this->exists($key) ? $this->boolean($key) : $default;
    }
}
