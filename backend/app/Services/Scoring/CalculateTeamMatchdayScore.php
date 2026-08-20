<?php

namespace App\Services\Scoring;

use App\Exceptions\SubmittedFormationNotFound;
use App\Models\FantasyTeam;
use App\Models\Formation;
use App\Models\Matchday;
use App\Models\PlayerScore;
use App\Models\TeamMatchdayScore;
use App\Services\Formation\ResolveFormationSubstitutions;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CalculateTeamMatchdayScore
{
    public function __construct(private ResolveFormationSubstitutions $substitutionResolver) {}

    public function calculate(FantasyTeam $fantasyTeam, Matchday $matchday): TeamMatchdayScore
    {
        return DB::transaction(function () use ($fantasyTeam, $matchday): TeamMatchdayScore {
            $formation = Formation::query()
                ->where('fantasy_team_id', $fantasyTeam->getKey())
                ->where('matchday_id', $matchday->getKey())
                ->where('league_id', $fantasyTeam->league_id)
                ->where('is_confirmed', true)
                ->whereNotNull('submitted_at')
                ->lockForUpdate()
                ->first();

            if (! $formation instanceof Formation) {
                throw SubmittedFormationNotFound::forTeamAndMatchday($fantasyTeam->getKey(), $matchday->getKey());
            }

            $resolved = $this->substitutionResolver->resolve($formation);
            $scores = $this->scoresByPlayer($formation);
            $contributingIds = $resolved->effectiveStarters->pluck('player_id')->map(fn($id): int => (int) $id)->all();
            $contributing = array_fill_keys($contributingIds, true);
            $captainBonusCents = $formation->league->realCaptainBonusEnabled()
                ? $this->cents($formation->league->realCaptainBonusPoints())
                : 0;
            $cleanSheetBonusCents = $formation->league->goalkeeperCleanSheetBonusEnabled()
                ? $this->cents($formation->league->goalkeeperCleanSheetBonusPoints())
                : 0;
            $roles = $formation->players->mapWithKeys(
                fn($player): array => [(int) $player->player_id => $player->playerRole?->key],
            );
            $goalkeeperBonusCents = 0;
            $defenseModifierCents = 0;
            $baseCents = 0;
            $bonusCents = 0;

            foreach ($contributingIds as $playerId) {
                $score = $scores->get($playerId);
                if (! $score?->isPlayable()) {
                    continue;
                }

                $baseCents += $this->cents($score->{PlayerScore::FANTASY_SCORE_INPUT_FIELD});
                if ($score->is_captain) {
                    $bonusCents += $captainBonusCents;
                }
                if ($roles->get($playerId) === 'goalkeeper' && $score->clean_sheet) {
                    $goalkeeperBonusCents += $cleanSheetBonusCents;
                }
            }

            if ($formation->league->defenseModifierEnabled()) {
                $effective = $resolved->effectiveStarters->filter(
                    fn($player): bool => $scores->get($player->player_id)?->isPlayable() ?? false,
                );
                $defenders = $effective->filter(fn($player): bool => $player->playerRole?->key === 'defender');
                $goalkeeper = $effective->first(fn($player): bool => $player->playerRole?->key === 'goalkeeper');
                if ($defenders->count() >= 4 && $goalkeeper !== null) {
                    $votes = $defenders->map(fn($player): float => (float) $scores->get($player->player_id)->base_rating)
                        ->sortDesc()->take(3)->values();
                    $votes->push((float) $scores->get($goalkeeper->player_id)->base_rating);
                    $average = $votes->sum() / 4;
                    foreach ($formation->league->defenseModifierThresholds() as $threshold) {
                        if ($average >= $threshold['threshold']) {
                            $defenseModifierCents = $this->cents($threshold['bonus']);
                        }
                    }
                }
            }

            $aggregate = TeamMatchdayScore::query()
                ->where('fantasy_team_id', $fantasyTeam->getKey())
                ->where('matchday_id', $matchday->getKey())
                ->lockForUpdate()
                ->first() ?? new TeamMatchdayScore;

            $aggregate->fill([
                'league_id' => $formation->league_id,
                'fantasy_team_id' => $fantasyTeam->getKey(),
                'matchday_id' => $matchday->getKey(),
                'formation_id' => $formation->getKey(),
                'base_points' => $this->decimal($baseCents),
                'points' => $this->decimal($baseCents + $bonusCents + $goalkeeperBonusCents + $defenseModifierCents),
                'substitution_points' => '0.00',
                'defense_modifier_points' => $this->decimal($defenseModifierCents),
                'goalkeeper_clean_sheet_bonus_points' => $this->decimal($goalkeeperBonusCents),
                'status' => 'calculated',
                'calculated_at' => now(),
            ])->save();

            $aggregate->details()->delete();
            $substitutionsByIncoming = $resolved->substitutions->keyBy(fn($substitution): int => $substitution->incoming->id);

            foreach ($this->orderedSubmittedPlayers($formation) as $formationPlayer) {
                $score = $scores->get($formationPlayer->player_id);
                $isContributing = isset($contributing[$formationPlayer->player_id]) && $score?->isPlayable();
                $pointsCents = $isContributing ? $this->cents($score->{PlayerScore::FANTASY_SCORE_INPUT_FIELD}) : 0;
                if ($isContributing && $score->is_captain) {
                    $pointsCents += $captainBonusCents;
                }
                if ($isContributing && $formationPlayer->playerRole?->key === 'goalkeeper' && $score->clean_sheet) {
                    $pointsCents += $cleanSheetBonusCents;
                }
                $substitution = $substitutionsByIncoming->get($formationPlayer->id);

                $aggregate->details()->create([
                    'player_id' => $formationPlayer->player_id,
                    'player_score_id' => $score?->getKey(),
                    'replaced_player_id' => $substitution?->outgoing->player_id,
                    'points' => $this->decimal($pointsCents),
                    'was_starter' => $formationPlayer->slot_type === 'starter',
                    'was_bench' => $formationPlayer->slot_type === 'bench',
                    'was_used_as_substitute' => $substitution !== null,
                ]);
            }

            return $aggregate->refresh()->load('details');
        });
    }

    /** @return EloquentCollection<int, PlayerScore> */
    private function scoresByPlayer(Formation $formation): EloquentCollection
    {
        $scores = PlayerScore::query()
            ->select('player_scores.*', 'player_season_registrations.player_id as registration_player_id')
            ->join('player_season_registrations', 'player_season_registrations.id', '=', 'player_scores.player_season_registration_id')
            ->join('season_clubs', 'season_clubs.id', '=', 'player_season_registrations.season_club_id')
            ->where('player_scores.matchday_id', $formation->matchday_id)
            ->where('season_clubs.season_id', $formation->matchday->season_id)
            ->whereIn('player_season_registrations.player_id', $formation->players->pluck('player_id')->unique()->all())
            ->orderBy('player_scores.id')
            ->get();

        $duplicates = $scores->groupBy('registration_player_id')->first(fn($group): bool => $group->count() > 1);
        if ($duplicates !== null) {
            throw new LogicException('Multiple PlayerScores exist for a submitted player on this matchday.');
        }

        return $scores->keyBy(fn(PlayerScore $score): int => (int) $score->registration_player_id);
    }

    /** @return Collection<int, \App\Models\FormationPlayer> */
    private function orderedSubmittedPlayers(Formation $formation): Collection
    {
        return $formation->players->sortBy([
            ['slot_type', 'desc'],
            ['position_index', 'asc'],
            ['id', 'asc'],
        ])->values();
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
