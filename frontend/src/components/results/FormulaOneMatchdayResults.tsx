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
            className="grid grid-cols-[auto_1fr_auto] items-center gap-4 rounded-xl border border-theme-border bg-theme-surface/70 p-5"
            key={entry.fantasy_team.id}
          >
            <div className="w-10 text-center text-xl font-bold text-theme-accent">
              {counted && entry.finishing_position !== null ? entry.finishing_position : '—'}
            </div>
            <div>
              <h3 className="font-semibold text-theme-text">{entry.fantasy_team.name}</h3>
              <p className={`mt-1 text-sm ${calculated ? 'text-theme-accent' : 'text-amber-200'}`}>
                {status}
              </p>
              {entry.formation_submitted ? (
                <Link
                  className="mt-2 inline-block text-sm font-semibold text-theme-accent hover:text-theme-accent"
                  to={`/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${entry.fantasy_team.id}/formation`}
                >
                  {calculated ? t('results.viewTeamResult') : t('results.viewFormation')}
                </Link>
              ) : null}
            </div>
            <div className="text-right">
              <p className="text-2xl font-bold text-theme-text">
                {counted ? (entry.points ?? '—') : '—'}
              </p>
              {counted && entry.championship_points !== null ? (
                <p className="text-sm font-semibold text-theme-accent">
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
