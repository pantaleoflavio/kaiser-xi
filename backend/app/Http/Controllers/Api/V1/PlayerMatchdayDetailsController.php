<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\League;
use App\Models\Player;
use App\Models\Matchday;
use App\Models\Formation;
use App\Models\PlayerScore;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\TeamMatchdayScoreDetail;
use App\Models\PlayerSeasonRegistration;
use App\Services\Scoring\PlayerFantasyScoreCalculator;

class PlayerMatchdayDetailsController extends Controller
{
    public function __invoke(Request $request, League $league, Player $player, Matchday $matchday, PlayerFantasyScoreCalculator $calculator): JsonResponse
    {
        abort_unless($matchday->season_id === $league->season_id, 404);

        $registration = PlayerSeasonRegistration::query()
            ->whereBelongsTo($player)
            ->whereHas('seasonClub', fn($query) => $query->where('season_id', $league->season_id))
            ->with(['seasonClub', 'playerRole'])
            ->firstOrFail();
        $score = PlayerScore::query()
            ->whereBelongsTo($registration)
            ->whereBelongsTo($matchday)
            ->first();

        $status = $score?->status->value ?? 'no_data';
        $breakdown = $score?->isPlayable() ? $calculator->breakdown($score, $league) : null;
        $formation = null;
        $bonuses = [];
        if ($request->filled('formation_id')) {
            $formation = Formation::query()->whereKey($request->integer('formation_id'))
                ->whereBelongsTo($league)->whereBelongsTo($matchday)->firstOrFail();
            $detail = TeamMatchdayScoreDetail::query()
                ->whereHas('teamMatchdayScore', fn($query) => $query->where('formation_id', $formation->id))
                ->where('player_id', $player->id)->first();
            if ($detail && $breakdown && $score) {
                $captain = $score->is_captain && $league->realCaptainBonusEnabled() ? $this->cents($league->realCaptainBonusPoints()) : 0;
                $role = $formation->players()->where('player_id', $player->id)->with('playerRole')->first()?->playerRole?->key;
                $cleanSheet = $role === 'goalkeeper' && $score->clean_sheet && $league->goalkeeperCleanSheetBonusEnabled()
                    ? $this->cents($league->goalkeeperCleanSheetBonusPoints()) : 0;
                $effective = $this->cents($detail->points);
                if ($effective === $breakdown['fantasy_score_cents'] + $captain + $cleanSheet) {
                    if ($captain !== 0) $bonuses[] = ['type' => 'captain_bonus', 'total' => $this->decimal($captain)];
                    if ($cleanSheet !== 0) $bonuses[] = ['type' => 'clean_sheet_bonus', 'total' => $this->decimal($cleanSheet)];
                }
                $formation = ['source' => 'persisted_result', 'effective_contribution' => $detail->points, 'bonuses' => $bonuses];
            } else {
                $formation = null;
            }
        }

        return response()->json(['data' => [
            'player' => ['id' => $player->id, 'name' => $player->display_name, 'club' => $registration->seasonClub->display_name, 'role' => $registration->playerRole->key],
            'matchday' => ['id' => $matchday->id, 'number' => $matchday->number, 'name' => $matchday->name],
            'status' => $status,
            'performance' => $score ? ['base_rating' => $score->base_rating] : null,
            'breakdown' => $breakdown ? [
                'base_rating' => $this->decimal($breakdown['base_rating_cents']),
                'components' => array_map(fn(array $component) => [
                    'type' => $component['type'],
                    'count' => $component['count'],
                    'coefficient' => $this->decimal($component['coefficient_cents']),
                    'total' => $this->decimal($component['total_cents']),
                ], $breakdown['components']),
                'fantasy_score' => $this->decimal($breakdown['fantasy_score_cents']),
            ] : null,
            'formation_context' => $formation,
        ]]);
    }

    private function cents(float|string $points): int
    {
        return (int) round((float) $points * 100);
    }
    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
