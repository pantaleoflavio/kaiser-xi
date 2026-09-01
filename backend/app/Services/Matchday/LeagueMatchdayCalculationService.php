<?php

namespace App\Services\Matchday;

use App\Enums\CalculationStatus;
use App\Jobs\CalculateLeagueMatchdayJob;
use App\Models\FantasyMatch;
use App\Models\FantasyMatchResult;
use App\Models\League;
use App\Models\LeagueMatchdayCalculation;
use App\Models\Matchday;
use App\Models\TeamMatchdayScore;
use App\Services\League\ChampionshipMatchdays;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class LeagueMatchdayCalculationService
{
    public function __construct(
        private readonly FinalizeMatchday $calculator,
        private readonly ChampionshipMatchdays $championshipMatchdays,
    ) {}

    public function reserve(League $league, Matchday $matchday): LeagueMatchdayCalculation
    {
        return DB::transaction(function () use ($league, $matchday): LeagueMatchdayCalculation {
            $lockedLeague = League::query()->with('type')->lockForUpdate()->findOrFail($league->id);
            $lockedMatchday = Matchday::query()->findOrFail($matchday->id);
            $this->assertSameSeasonAndEligible($lockedLeague, $lockedMatchday);

            $calculation = LeagueMatchdayCalculation::query()->whereBelongsTo($lockedLeague)
                ->where('matchday_id', $lockedMatchday->id)->lockForUpdate()->first();
            $successful = $this->wasSuccessfullyCalculated($lockedLeague, $lockedMatchday, $calculation);
            if (! $successful && (now()->lt($lockedMatchday->ends_at) || $lockedMatchday->calculation_unlocked_at === null)) {
                throw new DomainException($lockedMatchday->calculation_unlocked_at === null
                    ? 'Calculation has not been unlocked for this matchday.' : 'The matchday has not ended yet.');
            }

            if ($calculation && $this->isActive($calculation) && ! $this->isStale($calculation)) {
                return $calculation;
            }

            $token = (string) Str::uuid();
            $calculation ??= new LeagueMatchdayCalculation(['league_id' => $lockedLeague->id, 'matchday_id' => $lockedMatchday->id]);
            $calculation->fill([
                'status' => CalculationStatus::Queued,
                'execution_token' => $token,
                'queued_at' => now(),
                'started_at' => null,
                'failed_at' => null,
                'failure_message' => null,
            ])->save();
            DB::afterCommit(fn() => CalculateLeagueMatchdayJob::dispatch($lockedLeague->id, $lockedMatchday->id, $token));

            return $calculation;
        });
    }

    public function execute(int $leagueId, int $matchdayId, string $token): void
    {
        $claimed = DB::transaction(function () use ($leagueId, $matchdayId, $token): bool {
            $league = League::query()->with('type')->lockForUpdate()->findOrFail($leagueId);
            $matchday = Matchday::query()->findOrFail($matchdayId);
            $row = LeagueMatchdayCalculation::query()->where('league_id', $leagueId)->where('matchday_id', $matchdayId)->lockForUpdate()->first();
            if (! $row || ! hash_equals((string) $row->execution_token, $token)) return false;
            $recoverable = $row->status === CalculationStatus::Calculating && $this->isStale($row);
            if ($row->status !== CalculationStatus::Queued && ! $recoverable) return false;

            try {
                $this->assertSameSeasonAndEligible($league, $matchday);
                if ($row->calculated_at === null && (now()->lt($matchday->ends_at) || $matchday->calculation_unlocked_at === null)) {
                    throw new DomainException('This first calculation is no longer eligible.');
                }
            } catch (DomainException $exception) {
                $row->update(['status' => CalculationStatus::Failed, 'failed_at' => now(), 'failure_message' => $exception->getMessage()]);
                return false;
            }
            $row->update(['status' => CalculationStatus::Calculating, 'started_at' => now()]);
            return true;
        });
        if (! $claimed) return;

        DB::transaction(function () use ($leagueId, $matchdayId, $token): void {
            $this->calculator->calculate(League::findOrFail($leagueId), Matchday::findOrFail($matchdayId));
            LeagueMatchdayCalculation::query()->where('league_id', $leagueId)->where('matchday_id', $matchdayId)
                ->where('execution_token', $token)->update(['status' => CalculationStatus::Completed->value, 'calculated_at' => now(), 'failed_at' => null, 'failure_message' => null]);
        });
    }

    public function fail(int $leagueId, int $matchdayId, string $token, ?Throwable $exception): void
    {
        LeagueMatchdayCalculation::query()->where('league_id', $leagueId)->where('matchday_id', $matchdayId)
            ->where('execution_token', $token)->whereIn('status', [CalculationStatus::Queued->value, CalculationStatus::Calculating->value])
            ->update(['status' => CalculationStatus::Failed->value, 'failed_at' => now(), 'failure_message' => mb_substr($exception?->getMessage() ?? 'Calculation failed.', 0, 2000)]);
    }

    public function capabilities(League $league, Matchday $matchday, bool $authorized): array
    {
        $row = LeagueMatchdayCalculation::query()->where('league_id', $league->id)->where('matchday_id', $matchday->id)->first();
        $successful = $this->wasSuccessfullyCalculated($league, $matchday, $row);
        $active = $row && $this->isActive($row) && ! $this->isStale($row);
        $eligible = $this->isEligible($league, $matchday);
        return [
            'is_calculated' => $successful,
            'calculation_status' => $row?->status?->value ?? ($successful ? CalculationStatus::Completed->value : null),
            'can_calculate' => $authorized && $eligible && now()->gte($matchday->ends_at) && $matchday->calculation_unlocked_at !== null && ! $successful && ! $active,
            'can_recalculate' => $authorized && $eligible && $successful && ! $active,
        ];
    }

    public function isEligible(League $league, Matchday $matchday): bool
    {
        return (int) $league->season_id === (int) $matchday->season_id
            && $this->isInitialized($league, $matchday);
    }

    private function assertSameSeasonAndEligible(League $league, Matchday $matchday): void
    {
        if ((int) $league->season_id !== (int) $matchday->season_id) throw new DomainException('The matchday does not belong to this league.');
        if (! $this->isInitialized($league, $matchday)) throw new DomainException('The matchday is not part of an initialized league competition.');
    }

    private function isInitialized(League $league, Matchday $matchday): bool
    {
        if ($league->isNonHeadToHeadChampionship()) return $league->hasInitializedChampionship() && $this->championshipMatchdays->contains($league, $matchday);
        return $league->type?->key === 'head_to_head' && $league->hasInitializedHeadToHeadSchedule()
            && FantasyMatch::query()->where('league_id', $league->id)->where('matchday_id', $matchday->id)->exists();
    }

    private function wasSuccessfullyCalculated(League $league, Matchday $matchday, ?LeagueMatchdayCalculation $row): bool
    {
        return $row?->calculated_at !== null
            || TeamMatchdayScore::query()->where('league_id', $league->id)->where('matchday_id', $matchday->id)->exists()
            || FantasyMatchResult::query()->whereHas('fantasyMatch', fn($q) => $q->where('league_id', $league->id)->where('matchday_id', $matchday->id))->exists();
    }

    private function isActive(LeagueMatchdayCalculation $row): bool
    {
        return in_array($row->status, [CalculationStatus::Queued, CalculationStatus::Calculating], true);
    }

    private function isStale(LeagueMatchdayCalculation $row): bool
    {
        $at = $row->status === CalculationStatus::Calculating ? $row->started_at : $row->queued_at;
        return $at !== null && $at->lte(now()->subSeconds((int) config('queue.calculations.stale_after')));
    }
}
