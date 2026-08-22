<?php

namespace App\Http\Resources\League;


use App\Models\FantasyTeam;
use App\Models\Matchday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HeadToHeadScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fixtures = $this->fantasyMatches
            ->groupBy('matchday_id')
            ->map(fn($matches): array => [
                'matchday' => $this->matchdayData($matches->first()->matchday),
                'fixtures' => $matches->map(fn($match): array => [
                    'id' => $match->id,
                    'home_fantasy_team' => $this->teamData($match->homeFantasyTeam),
                    'away_fantasy_team' => $this->teamData($match->awayFantasyTeam),
                    'result' => $match->result === null ? null : [
                        'id' => $match->result->id,
                        'home_points' => $match->result->home_points,
                        'away_points' => $match->result->away_points,
                        'home_goals' => $match->result->home_goals,
                        'away_goals' => $match->result->away_goals,
                        'status' => $match->result->result_status,
                        'calculated_at' => $match->result->calculated_at,
                    ],
                ])->values()->all(),
            ])->values();

        $participantCount = $this->hasInitializedHeadToHeadSchedule()
            ? $this->fantasyMatches->flatMap(fn($match): array => [
                $match->home_fantasy_team_id,
                $match->away_fantasy_team_id,
            ])->unique()->count()
            : $this->fantasy_teams_count;

        return [
            'initialized' => $this->hasInitializedHeadToHeadSchedule(),
            'generated_at' => $this->h2h_schedule_generated_at,
            'start_matchday' => $this->h2hStartMatchday === null
                ? null
                : $this->matchdayData($this->h2hStartMatchday),
            'participant_count' => $participantCount,
            'matchdays' => $fixtures,
        ];
    }

    private function matchdayData(Matchday $matchday): array
    {
        return [
            'id' => $matchday->id,
            'number' => $matchday->number,
            'name' => $matchday->name,
            'starts_at' => $matchday->starts_at,
        ];
    }

    private function teamData(FantasyTeam $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
        ];
    }
}
