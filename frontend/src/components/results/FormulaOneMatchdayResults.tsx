import { Link } from 'react-router-dom';
import { useTranslation } from '../../i18n';
import type { ChampionshipMatchdayTeamResult } from '../../types/results';

export function FormulaOneMatchdayResults({
  teams,
  counted,
  leagueId,
  matchdayId,
}: {
  teams: ChampionshipMatchdayTeamResult[];
  counted: boolean;
  leagueId: string;
  matchdayId: number;
}) {
  const { t } = useTranslation();
  return (
    <div className="space-y-3">
      {teams.map((entry) => {
        const calculated = entry.result_status === 'calculated';
        const status = calculated
          ? t('results.finalScore')
          : entry.result_status === 'missing_formation'
            ? t('results.missingFormation')
            : entry.formation_submitted
              ? t('results.submittedPending')
              : t('results.notSubmitted');
        return (
          <article
            className="grid grid-cols-[auto_1fr_auto] items-center gap-4 rounded-xl border border-slate-800 bg-slate-900/70 p-5"
            key={entry.fantasy_team.id}
          >
            <div className="w-10 text-center text-xl font-bold text-emerald-300">
              {counted && entry.finishing_position !== null ? entry.finishing_position : '—'}
            </div>
            <div>
              <h3 className="font-semibold text-white">{entry.fantasy_team.name}</h3>
              <p className={`mt-1 text-sm ${calculated ? 'text-emerald-200' : 'text-amber-200'}`}>
                {status}
              </p>
              {entry.formation_submitted ? (
                <Link
                  className="mt-2 inline-block text-sm font-semibold text-emerald-300 hover:text-emerald-200"
                  to={`/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${entry.fantasy_team.id}/formation`}
                >
                  {calculated ? t('results.viewTeamResult') : t('results.viewFormation')}
                </Link>
              ) : null}
            </div>
            <div className="text-right">
              <p className="text-2xl font-bold text-white">
                {counted ? (entry.points ?? '—') : '—'}
              </p>
              {counted && entry.championship_points !== null ? (
                <p className="text-sm font-semibold text-emerald-300">
                  +{entry.championship_points} {t('standings.championshipPointsShort')}
                </p>
              ) : null}
            </div>
          </article>
        );
      })}
    </div>
  );
}
