<?php

namespace App\Http\Resources\Scoring;

use App\Models\Formation;
use App\Models\FormationPlayer;
use App\Models\TeamMatchdayScore;
use App\Models\TeamMatchdayScoreDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMatchdayResultResource extends JsonResource
{
    public function __construct(Formation $formation, private readonly ?TeamMatchdayScore $score)
    {
        parent::__construct($formation);
    }

    public function toArray(Request $request): array
    {
        /** @var Formation $formation */
        $formation = $this->resource;
        $details = $this->score?->details->keyBy('player_id');

        return [
            'fantasy_team' => [
                'id' => $formation->fantasyTeam->id,
                'name' => $formation->fantasyTeam->name,
                'slug' => $formation->fantasyTeam->slug,
            ],
            'matchday' => [
                'id' => $formation->matchday->id,
                'number' => $formation->matchday->number,
                'name' => $formation->matchday->name,
                'deadline' => $formation->matchday->starts_at,
            ],
            'formation' => [
                'id' => $formation->id,
                'module' => $formation->formationModule->name,
                'submitted_at' => $formation->submitted_at,
                'players' => $formation->players->sortBy([
                    ['slot_type', 'desc'],
                    ['position_index', 'asc'],
                ])->values()->map(fn(FormationPlayer $player): array => $this->player($player, $details?->get($player->player_id), $details))->all(),
            ],
            'result' => $this->score === null ? null : [
                'status' => $this->score->status,
                'points' => $this->score->points,
                'base_points' => $this->score->base_points,
                'substitution_points' => $this->score->substitution_points,
                'defense_modifier_points' => $this->score->defense_modifier_points,
                'goalkeeper_clean_sheet_bonus_points' => $this->score->goalkeeper_clean_sheet_bonus_points,
                'calculated_at' => $this->score->calculated_at,
            ],
        ];
    }

    private function player(FormationPlayer $formationPlayer, ?TeamMatchdayScoreDetail $detail, $details): array
    {
        $score = $detail?->playerScore;
        $incoming = $details?->first(fn(TeamMatchdayScoreDetail $candidate): bool => $candidate->replaced_player_id === $formationPlayer->player_id);

        return [
            'player' => [
                'id' => $formationPlayer->player->id,
                'name' => $formationPlayer->player->display_name,
                'role' => $formationPlayer->playerRole->key,
            ],
            'submitted_slot' => $formationPlayer->slot_type,
            'submitted_order' => $formationPlayer->position_index,
            'used_as_substitute' => $detail?->was_used_as_substitute ?? false,
            'replaced_player' => $detail?->replacedPlayer === null ? null : [
                'id' => $detail->replacedPlayer->id,
                'name' => $detail->replacedPlayer->display_name,
            ],
            'replaced_by_player' => $incoming === null ? null : [
                'id' => $incoming->player->id,
                'name' => $incoming->player->display_name,
            ],
            'effective_contribution' => $detail?->points,
            'player_score' => $score === null ? null : [
                'status' => $score->status->value,
                'final_score' => $score->final_score,
                'base_rating' => $score->base_rating,
                'goals' => $score->goals,
                'assists' => $score->assists,
                'yellow_cards' => $score->yellow_cards,
                'red_cards' => $score->red_cards,
                'own_goals' => $score->own_goals,
                'penalties_scored' => $score->penalties_scored,
                'penalties_missed' => $score->penalties_missed,
                'penalties_saved' => $score->penalties_saved,
                'goals_conceded' => $score->goals_conceded,
                'clean_sheet' => $score->clean_sheet,
                'is_real_captain' => $score->is_captain,
            ],
        ];
    }
}
