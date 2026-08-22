<?php

namespace App\Services\PlayerScore;

use App\Enums\PlayerScoreStatus;
use App\Models\PlayerScore;
use App\Rules\PlayerRegistrationMatchesMatchdaySeason;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlayerScoreService
{
    public const EVENT_FIELDS = [
        'goals',
        'assists',
        'yellow_cards',
        'red_cards',
        'own_goals',
        'penalties_scored',
        'penalties_missed',
        'penalties_saved',
        'goals_conceded',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data, ?PlayerScore $existing = null): array
    {
        $identity = [
            'player_season_registration_id' => $data['player_season_registration_id'] ?? $existing?->player_season_registration_id,
            'matchday_id' => $data['matchday_id'] ?? $existing?->matchday_id,
        ];

        $performance = $this->preparePerformance($data, $existing);

        $matchdayId = $identity['matchday_id'];

        $validatedIdentity = Validator::make($identity, [
            'player_season_registration_id' => [
                'required',
                'integer',
                Rule::exists('player_season_registrations', 'id'),
                Rule::unique('player_scores', 'player_season_registration_id')
                    ->where(fn($query) => $query->where('matchday_id', $matchdayId))
                    ->ignore($existing?->getKey()),
                new PlayerRegistrationMatchesMatchdaySeason($matchdayId),
            ],
            'matchday_id' => [
                'required',
                'integer',
                Rule::exists('matchdays', 'id'),
            ],
        ], [
            'player_season_registration_id.unique' => __('admin.validation.player_scores.duplicate'),
        ])->validate();

        return $validatedIdentity + $performance;
    }

    /**
     * Normalize and validate score fields after callers have resolved the identity.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preparePerformance(array $data, ?PlayerScore $existing = null): array
    {
        if ($existing) {
            $data = array_merge(
                $existing->only($existing->getFillable()),
                $data,
            );
        }

        foreach (self::EVENT_FIELDS as $field) {
            $data[$field] ??= 0;
        }

        $data['clean_sheet'] ??= false;
        $data['is_captain'] ??= false;

        if (
            ($data['status'] ?? null) === PlayerScoreStatus::DidNotPlay->value
            || ($data['status'] ?? null) === PlayerScoreStatus::DidNotPlay
        ) {
            $data['base_rating'] = null;
            $data['final_score'] = null;
            $data['clean_sheet'] = false;
            $data['is_captain'] = false;

            foreach (self::EVENT_FIELDS as $field) {
                $data[$field] = 0;
            }
        }

        $rules = [
            'base_rating' => [
                Rule::requiredIf(
                    ($data['status'] ?? null) === PlayerScoreStatus::Confirmed->value
                        || ($data['status'] ?? null) === PlayerScoreStatus::Confirmed
                ),
                'nullable',
                'numeric',
                'decimal:0,2',
                'between:-99.99,99.99',
            ],
            'final_score' => [
                'nullable',
                'numeric',
                'decimal:0,2',
                'between:-999.99,999.99',
            ],
            'clean_sheet' => ['required', 'boolean'],
            'is_captain' => ['required', 'boolean'],
            'status' => ['required', Rule::enum(PlayerScoreStatus::class)],
        ];

        foreach (self::EVENT_FIELDS as $field) {
            $rules[$field] = ['required', 'integer', 'min:0'];
        }

        return Validator::make($data, $rules, [
            'base_rating.required' => __('admin.validation.player_scores.confirmed_base_rating_required'),
        ])->validate();
    }
}
