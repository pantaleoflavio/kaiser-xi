import type { TeamMatchdayResult } from '../../types/results';
import { useTranslation } from '../../i18n';
import { PlayerScoreBreakdownRow } from './PlayerScoreBreakdownRow';

export function HistoricalFormationView({ data }: { data: TeamMatchdayResult }) {
  const { t } = useTranslation();
  const starters = data.formation.players.filter((player) => player.submitted_slot === 'starter');
  const bench = data.formation.players.filter((player) => player.submitted_slot === 'bench');
  return (
    <article className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-sm uppercase tracking-wide text-emerald-300">
            {t('results.submittedFormation')}
          </p>
          <h2 className="mt-1 text-2xl font-semibold text-white">{data.fantasy_team.name}</h2>
          <p className="mt-1 text-slate-300">
            {t('formation.title')}: {data.formation.module}
          </p>
        </div>
        <div className="text-right">
          <p className="text-sm text-slate-400">{t('results.teamTotal')}</p>
          <p className="text-3xl font-bold text-white">
            {data.result ? data.result.points : t('results.pendingResult')}
          </p>
          {data.result ? (
            <p className="text-sm text-slate-400">
              {t('results.basePoints')}: {data.result.base_points}
            </p>
          ) : null}
        </div>
      </div>
      <section className="mt-6">
        <h3 className="mb-3 text-lg font-semibold text-white">{t('results.starter')}</h3>
        <ul className="grid gap-3 lg:grid-cols-2">
          {starters.map((player) => (
            <PlayerScoreBreakdownRow item={player} key={player.player.id} />
          ))}
        </ul>
      </section>
      <section className="mt-6">
        <h3 className="mb-3 text-lg font-semibold text-white">{t('results.bench')}</h3>
        <ul className="grid gap-3 lg:grid-cols-2">
          {bench.map((player) => (
            <PlayerScoreBreakdownRow item={player} key={player.player.id} />
          ))}
        </ul>
      </section>
    </article>
  );
}
