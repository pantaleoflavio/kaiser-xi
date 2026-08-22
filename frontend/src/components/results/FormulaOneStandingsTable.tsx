import { useTranslation } from '../../i18n';
import type { FormulaOneStanding } from '../../types/league';

export function FormulaOneStandingsTable({
  standings,
  currentTeamId,
}: {
  standings: FormulaOneStanding[];
  currentTeamId?: number;
}) {
  const { t } = useTranslation();
  const columns: ReadonlyArray<{
    label: string;
    value: (standing: FormulaOneStanding) => string | number;
  }> = [
    { label: 'played', value: (standing) => standing.played },
    { label: 'championshipPoints', value: (standing) => standing.championship_points },
    { label: 'wins', value: (standing) => standing.wins },
    { label: 'podiums', value: (standing) => standing.podiums },
    { label: 'bestFinish', value: (standing) => standing.best_finish ?? '—' },
    { label: 'fantasyPointsTotal', value: (standing) => standing.fantasy_points_total },
    { label: 'averageFantasyPoints', value: (standing) => standing.average_fantasy_points },
  ];

  return (
    <>
      <div className="space-y-3 md:hidden">
        {standings.map((standing) => {
          const current = standing.fantasy_team.id === currentTeamId;
          return (
            <article
              aria-label={
                current
                  ? t('standings.currentTeam', { team: standing.fantasy_team.name })
                  : standing.fantasy_team.name
              }
              className={`rounded-xl border p-4 ${current ? 'border-emerald-400/50 bg-emerald-950/20' : 'border-slate-800 bg-slate-900/70'}`}
              key={standing.fantasy_team.id}
            >
              <div className="flex items-center gap-3">
                <span className="text-xl font-bold text-emerald-300">{standing.position}</span>
                <h2 className="font-semibold text-white">{standing.fantasy_team.name}</h2>
                {current ? (
                  <span className="text-xs text-emerald-200">{t('standings.you')}</span>
                ) : null}
              </div>
              <dl className="mt-3 grid grid-cols-2 gap-3 text-center sm:grid-cols-3">
                {columns.map((column) => (
                  <div key={column.label}>
                    <dt className="text-xs text-slate-400">
                      {t(`standings.${column.label}Short`)}
                    </dt>
                    <dd className="font-semibold text-white">{column.value(standing)}</dd>
                  </div>
                ))}
              </dl>
            </article>
          );
        })}
      </div>
      <div className="hidden overflow-x-auto rounded-xl border border-slate-800 md:block">
        <table className="w-full border-collapse text-sm">
          <thead className="bg-slate-800/80 text-slate-300">
            <tr>
              <th className="px-3 py-3 text-left">{t('standings.position')}</th>
              <th className="px-3 py-3 text-left">{t('standings.team')}</th>
              {columns.map((column) => (
                <th
                  className="px-2 py-3 text-center"
                  key={column.label}
                  title={t(`standings.${column.label}`)}
                >
                  {t(`standings.${column.label}Short`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {standings.map((standing) => {
              const current = standing.fantasy_team.id === currentTeamId;
              return (
                <tr
                  className={`border-t border-slate-800 ${current ? 'bg-emerald-950/30' : 'bg-slate-900/70'}`}
                  key={standing.fantasy_team.id}
                >
                  <td className="px-3 py-3 font-semibold text-emerald-300">{standing.position}</td>
                  <th className="px-3 py-3 text-left font-semibold text-white" scope="row">
                    {standing.fantasy_team.name}
                    {current ? (
                      <span className="ml-2 text-xs font-normal text-emerald-200">
                        ({t('standings.you')})
                      </span>
                    ) : null}
                  </th>
                  {columns.map((column) => (
                    <td className="px-2 py-3 text-center text-slate-200" key={column.label}>
                      {column.value(standing)}
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </>
  );
}
