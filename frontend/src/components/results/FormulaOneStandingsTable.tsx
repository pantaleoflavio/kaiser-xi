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
              className={`rounded-xl border p-4 ${current ? 'border-theme-primary/50 bg-emerald-950/20' : 'border-theme-border bg-theme-surface/70'}`}
              key={standing.fantasy_team.id}
            >
              <div className="flex items-center gap-3">
                <span className="text-xl font-bold text-theme-accent">{standing.position}</span>
                <h2 className="font-semibold text-theme-text">{standing.fantasy_team.name}</h2>
                {current ? (
                  <span className="text-xs text-theme-accent">{t('standings.you')}</span>
                ) : null}
              </div>
              <dl className="mt-3 grid grid-cols-2 gap-3 text-center sm:grid-cols-3">
                {columns.map((column) => (
                  <div key={column.label}>
                    <dt className="text-xs text-theme-muted">
                      {t(`standings.${column.label}Short`)}
                    </dt>
                    <dd className="font-semibold text-theme-text">{column.value(standing)}</dd>
                  </div>
                ))}
              </dl>
            </article>
          );
        })}
      </div>
      <div className="hidden overflow-x-auto rounded-xl border border-theme-border md:block">
        <table className="w-full border-collapse text-sm">
          <thead className="bg-theme-muted-surface/80 text-theme-muted">
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
                  className={`border-t border-theme-border ${current ? 'bg-emerald-950/30' : 'bg-theme-surface/70'}`}
                  key={standing.fantasy_team.id}
                >
                  <td className="px-3 py-3 font-semibold text-theme-accent">{standing.position}</td>
                  <th className="px-3 py-3 text-left font-semibold text-theme-text" scope="row">
                    {standing.fantasy_team.name}
                    {current ? (
                      <span className="ml-2 text-xs font-normal text-theme-accent">
                        ({t('standings.you')})
                      </span>
                    ) : null}
                  </th>
                  {columns.map((column) => (
                    <td className="px-2 py-3 text-center text-theme-text" key={column.label}>
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
