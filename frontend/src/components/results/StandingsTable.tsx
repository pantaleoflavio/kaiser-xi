import type { Standing } from '../../types/league';
import { useTranslation } from '../../i18n';

export function StandingsTable({
  standings,
  currentTeamId,
  classic = false,
  formulaOne = false,
}: {
  standings: Standing[];
  currentTeamId?: number;
  classic?: boolean;
  formulaOne?: boolean;
}) {
  const { t } = useTranslation();
  const value = (standing: Standing, field: string): string | number => {
    if ('championship_points' in standing) {
      if (field === 'championship_points') return standing.championship_points;
      if (field === 'wins') return standing.wins;
      if (field === 'podiums') return standing.podiums;
      if (field === 'best_finish') return standing.best_finish ?? '—';
      if (field === 'fantasy_points_total') return standing.fantasy_points_total;
      if (field === 'average_fantasy_points') return standing.average_fantasy_points;
      return standing.played;
    }
    if ('total_points' in standing) {
      if (field === 'total_points') return standing.total_points;
      if (field === 'average_points') return standing.average_points;
      if (field === 'best_matchday_score') return standing.best_matchday_score;
      return standing.played;
    }
    if (field === 'wins') return standing.wins;
    if (field === 'draws') return standing.draws;
    if (field === 'losses') return standing.losses;
    if (field === 'goals_for') return standing.goals_for;
    if (field === 'goals_against') return standing.goals_against;
    if (field === 'goal_difference') return standing.goal_difference;
    if (field === 'points') return standing.points;
    return standing.played;
  };
  const labels = (
    formulaOne
      ? [
          ['played', 'played'],
          ['championshipPoints', 'championship_points'],
          ['wins', 'wins'],
          ['podiums', 'podiums'],
          ['bestFinish', 'best_finish'],
          ['fantasyPointsTotal', 'fantasy_points_total'],
          ['averageFantasyPoints', 'average_fantasy_points'],
        ]
      : classic
        ? [
            ['played', 'played'],
            ['totalPoints', 'total_points'],
            ['averagePoints', 'average_points'],
            ['bestMatchday', 'best_matchday_score'],
          ]
        : [
            ['played', 'played'],
            ['wins', 'wins'],
            ['draws', 'draws'],
            ['losses', 'losses'],
            ['goalsFor', 'goals_for'],
            ['goalsAgainst', 'goals_against'],
            ['goalDifference', 'goal_difference'],
            ['points', 'points'],
          ]
  ) as ReadonlyArray<readonly [string, string]>;
  return (
    <>
      <div className="space-y-3 md:hidden">
        {standings.map((standing) => {
          const current = standing.fantasy_team.id === currentTeamId;
          return (
            <article
              className={`rounded-xl border p-4 ${current ? 'border-emerald-400/50 bg-emerald-950/20' : 'border-slate-800 bg-slate-900/70'}`}
              key={standing.fantasy_team.id}
              aria-label={
                current
                  ? t('standings.currentTeam', { team: standing.fantasy_team.name })
                  : standing.fantasy_team.name
              }
            >
              <div className="flex items-center gap-3">
                <span className="text-xl font-bold text-emerald-300">{standing.position}</span>
                <h2 className="font-semibold text-white">{standing.fantasy_team.name}</h2>
                {current ? (
                  <span className="text-xs text-emerald-200">{t('standings.you')}</span>
                ) : null}
              </div>
              <dl className="mt-3 grid grid-cols-3 gap-3 text-center">
                {labels.map(([label, field]) => (
                  <div key={field}>
                    <dt className="text-xs text-slate-400">{t(`standings.${label}Short`)}</dt>
                    <dd className="font-semibold text-white">{value(standing, field)}</dd>
                  </div>
                ))}
              </dl>
            </article>
          );
        })}
      </div>
      <div className="hidden overflow-hidden rounded-xl border border-slate-800 md:block">
        <table className="w-full border-collapse text-sm">
          <thead className="bg-slate-800/80 text-slate-300">
            <tr>
              <th className="px-3 py-3 text-left" scope="col">
                {t('standings.positionShort')}
              </th>
              <th className="px-3 py-3 text-left" scope="col">
                {t('standings.team')}
              </th>
              {labels.map(([label, field]) => (
                <th
                  className="px-2 py-3 text-center"
                  key={field}
                  scope="col"
                  title={t(`standings.${label}`)}
                >
                  {t(`standings.${label}Short`)}
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
                  {labels.map(([, field]) => (
                    <td
                      className={`px-2 py-3 text-center ${field === 'points' ? 'font-bold text-white' : 'text-slate-200'}`}
                      key={field}
                    >
                      {value(standing, field)}
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
