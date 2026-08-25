<?php

namespace App\Services\Player;

use App\Models\Player;
use App\Models\Season;
use App\Models\Matchday;
use App\Models\PlayerScore;
use App\Enums\PlayerScoreStatus;
use App\Models\PlayerSeasonRegistration;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BuildPlayerProfile
{
    /** @return array<string, mixed> */
    public function execute(Player $player, Season $season): array
    {
        $registrations = PlayerSeasonRegistration::query()
            ->where('player_id', $player->id)
            ->whereHas('seasonClub', fn(Builder $query) => $query->where('season_id', $season->id))
            ->with(['seasonClub.realClub:id,name,short_name', 'playerRole:id,key,label'])
            ->get();

        if ($registrations->isEmpty()) {
            abort(404, 'The player is not registered for the selected season.');
        }

        if ($registrations->count() !== 1) {
            throw new ConflictHttpException('The player has multiple registrations for the selected season; choose an unambiguous registration.');
        }

        $registration = $registrations->sole();
        $statistics = PlayerScore::query()
            ->where('player_season_registration_id', $registration->id)
            ->where('status', PlayerScoreStatus::Confirmed->value)
            ->selectRaw("SUM(CASE WHEN status = ? AND base_rating IS NOT NULL THEN 1 ELSE 0 END) AS appearances", [PlayerScoreStatus::Confirmed->value])
            ->selectRaw("AVG(CASE WHEN status = ? AND base_rating IS NOT NULL THEN base_rating END) AS average_rating", [PlayerScoreStatus::Confirmed->value])
            ->selectRaw('COALESCE(SUM(goals), 0) AS goals, COALESCE(SUM(assists), 0) AS assists, COALESCE(SUM(yellow_cards), 0) AS yellow_cards, COALESCE(SUM(red_cards), 0) AS red_cards')
            ->selectRaw('COALESCE(SUM(own_goals), 0) AS own_goals, COALESCE(SUM(penalties_scored), 0) AS penalties_scored, COALESCE(SUM(penalties_missed), 0) AS penalties_missed')
            ->selectRaw('COALESCE(SUM(penalties_saved), 0) AS penalties_saved, COALESCE(SUM(goals_conceded), 0) AS goals_conceded')
            ->selectRaw('COALESCE(SUM(CASE WHEN clean_sheet IS TRUE THEN 1 ELSE 0 END), 0) AS clean_sheets, COALESCE(SUM(CASE WHEN is_captain IS TRUE THEN 1 ELSE 0 END), 0) AS captain_appearances')
            ->firstOrFail();

        $matchdays = Matchday::query()
            ->where('season_id', $season->id)
            ->with([
                'playerScores' => fn($query) => $query->where('player_season_registration_id', $registration->id),
                'realMatches.homeSeasonClub.realClub:id,name,short_name',
                'realMatches.awaySeasonClub.realClub:id,name,short_name',
            ])
            ->orderBy('number')->orderBy('id')->get();

        return [
            'player' => [
                'id' => $player->id,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'display_name' => $player->display_name,
            ],
            'season' => ['id' => $season->id, 'name' => $season->name],
            'registration' => [
                'id' => $registration->id,
                'club' => [
                    'id' => $registration->seasonClub->id,
                    'name' => $registration->seasonClub->display_name ?? $registration->seasonClub->realClub?->name,
                ],
                'role' => [
                    'key' => $registration->playerRole->key,
                    'label' => $registration->playerRole->label,
                ],
                'shirt_number' => $registration->shirt_number,
            ],
            'statistics' => [
                'appearances' => (int) $statistics->appearances,
                'average_rating' => $statistics->average_rating === null ? null : number_format((float) $statistics->average_rating, 2, '.', ''),
                ...collect(['goals', 'assists', 'yellow_cards', 'red_cards', 'own_goals', 'penalties_scored', 'penalties_missed', 'penalties_saved', 'goals_conceded', 'clean_sheets', 'captain_appearances'])
                    ->mapWithKeys(fn(string $field) => [$field => (int) $statistics->{$field}])->all(),
            ],
            'matchdays' => $matchdays->map(fn(Matchday $matchday) => $this->matchday($matchday, $registration->season_club_id))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function matchday(Matchday $matchday, int $clubId): array
    {
        $score = $matchday->playerScores->first();
        $matches = $matchday->realMatches->filter(fn($match) => in_array($clubId, [$match->home_season_club_id, $match->away_season_club_id], true));
        $realMatch = $matches->count() === 1 ? $matches->first() : null;
        $isHome = $realMatch?->home_season_club_id === $clubId;
        $opponent = $realMatch ? ($isHome ? $realMatch->awaySeasonClub : $realMatch->homeSeasonClub) : null;
        $status = $score === null ? 'no_data' : ($score->isPlayable() ? 'played' : $score->status->value);

        return [
            'id' => $matchday->id,
            'number' => $matchday->number,
            'name' => $matchday->name,
            'starts_at' => $matchday->starts_at?->toISOString(),
            'status' => $status,
            'opponent' => $opponent ? ['id' => $opponent->id, 'name' => $opponent->display_name ?? $opponent->realClub?->name] : null,
            'venue' => $realMatch ? ($isHome ? 'home' : 'away') : null,
            'base_rating' => $score?->isPlayable() ? $score->base_rating : null,
            ...collect(['goals', 'assists', 'yellow_cards', 'red_cards', 'own_goals', 'penalties_scored', 'penalties_missed', 'penalties_saved', 'goals_conceded'])
                ->mapWithKeys(fn(string $field) => [$field => $score ? (int) $score->{$field} : null])->all(),
            'clean_sheet' => $score?->clean_sheet,
            'is_captain' => $score?->is_captain,
        ];
    }
}
