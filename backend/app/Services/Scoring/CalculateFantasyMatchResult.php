<?php

namespace App\Services\Scoring;

use App\Exceptions\IncompleteFantasyMatchScoreException;
use App\Models\FantasyMatch;
use App\Models\FantasyMatchResult;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\TeamMatchdayScore;
use App\Services\Scoring\ConvertTeamPointsToGoals;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CalculateFantasyMatchResult
{
    public function __construct(private readonly ConvertTeamPointsToGoals $converter) {}

    public function calculate(FantasyMatch $fantasyMatch): FantasyMatchResult
    {
        return DB::transaction(function () use ($fantasyMatch): FantasyMatchResult {
            $match = FantasyMatch::query()->whereKey($fantasyMatch->id)->lockForUpdate()->firstOrFail();
            $match->loadMissing(['league.type', 'homeFantasyTeam', 'awayFantasyTeam']);
            $league = $match->league;

            if (! $league instanceof League || $league->type?->key !== 'head_to_head') {
                throw new DomainException('Fantasy match results can only be calculated for head-to-head leagues.');
            }

            $scores = TeamMatchdayScore::query()
                ->where('matchday_id', $match->matchday_id)
                ->whereIn('fantasy_team_id', [$match->home_fantasy_team_id, $match->away_fantasy_team_id])
                ->get()
                ->keyBy('fantasy_team_id');

            $homePoints = $this->pointsFor($league, $match->homeFantasyTeam, $match->matchday_id, $scores->get($match->home_fantasy_team_id));
            $awayPoints = $this->pointsFor($league, $match->awayFantasyTeam, $match->matchday_id, $scores->get($match->away_fantasy_team_id));

            return FantasyMatchResult::query()->updateOrCreate(
                ['fantasy_match_id' => $match->id],
                [
                    'home_points' => $homePoints,
                    'away_points' => $awayPoints,
                    'home_goals' => $this->converter->convert($homePoints, $league->firstGoalThreshold(), $league->goalInterval()),
                    'away_goals' => $this->converter->convert($awayPoints, $league->firstGoalThreshold(), $league->goalInterval()),
                    'result_status' => 'calculated',
                    'calculated_at' => now(),
                ],
            );
        });
    }

    private function pointsFor(League $league, FantasyTeam $team, int $matchdayId, ?TeamMatchdayScore $score): float
    {
        $isParticipant = $league->memberships()->where('user_id', $team->user_id)->exists();
        if (! $isParticipant) {
            return 0.0;
        }

        if (! $score instanceof TeamMatchdayScore || $score->status !== 'calculated') {
            throw new IncompleteFantasyMatchScoreException($team->id, $matchdayId);
        }

        return (float) $score->points;
    }
}
