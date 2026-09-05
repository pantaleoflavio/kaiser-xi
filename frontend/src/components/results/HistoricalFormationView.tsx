import type { TeamMatchdayResult } from '../../types/results';
import { useTranslation } from '../../i18n';
import { PlayerScoreBreakdownRow } from './PlayerScoreBreakdownRow';
import { FormationPitch, type PitchPlayer } from '../formation/FormationPitch';

export function HistoricalFormationView({
  data,
  leagueId,
}: {
  data: TeamMatchdayResult;
  leagueId: string;
}) {
  const { t } = useTranslation();
  const starters = data.formation.players.filter((player) => player.submitted_slot === 'starter');
  const bench = data.formation.players.filter((player) => player.submitted_slot === 'bench');
  const toPitchPlayer = (player: (typeof data.formation.players)[number]): PitchPlayer => ({
    id: player.player.id,
    name: player.player.name,
    role: player.player.role,
    order: player.submitted_order,
    detail: player.effective_contribution ?? player.player_score?.base_rating ?? undefined,
    status: player.used_as_substitute
      ? t('results.enteredFromBench')
      : player.replaced_by_player
        ? `${t('results.replacedBy')}: ${player.replaced_by_player.name}`
        : undefined,
  });
  return (
    <article className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-sm uppercase tracking-wide text-theme-accent">
            {t('results.submittedFormation')}
          </p>
          <h2 className="mt-1 text-2xl font-semibold text-theme-text">{data.fantasy_team.name}</h2>
          <p className="mt-1 text-theme-muted">
            {t('formation.title')}: {data.formation.module}
          </p>
          <p className="mt-2 text-sm text-theme-accent" role="status">
            {t('formation.submitted')}
          </p>
        </div>
        <div className="text-right">
          <p className="text-sm text-theme-muted">{t('results.teamTotal')}</p>
          <p className="text-3xl font-bold text-theme-text">
            {data.result ? data.result.points : t('results.pendingResult')}
          </p>
          {data.result ? (
            <>
              <p className="text-sm font-semibold text-theme-accent">{t('results.finalScore')}</p>
              <p className="text-sm text-theme-muted">
                {t('results.basePoints')}: {data.result.base_points}
              </p>
            </>
          ) : null}
        </div>
      </div>
      <div className="mt-6">
        <FormationPitch
          ariaLabel={`${t('results.submittedFormation')} · ${data.formation.module}`}
          bench={bench.map(toPitchPlayer)}
          benchLabel={t('results.bench')}
          mode="readonly"
          players={starters.map(toPitchPlayer)}
        />
      </div>
      <h3 className="mt-6 text-xl font-semibold text-theme-text">{t('results.effectiveLineup')}</h3>
      <section className="mt-6">
        <h4 className="mb-3 text-lg font-semibold text-theme-text">{t('results.starter')}</h4>
        <ul className="grid gap-3 lg:grid-cols-2">
          {starters.map((player) => (
            <PlayerScoreBreakdownRow
              data={data}
              item={player}
              key={player.player.id}
              leagueId={leagueId}
            />
          ))}
        </ul>
      </section>
      <section className="mt-6">
        <h3 className="mb-3 text-lg font-semibold text-theme-text">{t('results.bench')}</h3>
        <ul className="grid gap-3 lg:grid-cols-2">
          {bench.map((player) => (
            <PlayerScoreBreakdownRow
              data={data}
              item={player}
              key={player.player.id}
              leagueId={leagueId}
            />
          ))}
        </ul>
      </section>
    </article>
  );
}
