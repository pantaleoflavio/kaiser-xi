import { Link } from 'react-router-dom';
import type { ChampionshipMatchdayTeamResult } from '../../types/results';
import { useTranslation } from '../../i18n';

export function ClassicTeamResultCard({
  entry,
  leagueId,
  matchdayId,
}: {
  entry: ChampionshipMatchdayTeamResult;
  leagueId: string;
  matchdayId: number;
}) {
  const { t } = useTranslation();
  const calculated = entry.result_status === 'calculated';
  const missing = entry.result_status === 'missing_formation';

  return (
    <article className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-theme-border bg-theme-surface/70 p-5">
      <div>
        <h3 className="font-semibold text-theme-text">{entry.fantasy_team.name}</h3>
        <p className={`mt-1 text-sm ${calculated ? 'text-theme-accent' : 'text-amber-200'}`}>
          {calculated
            ? t('results.finalScore')
            : missing
              ? t('results.missingFormation')
              : entry.formation_submitted
                ? t('results.submittedPending')
                : t('results.notSubmitted')}
        </p>
      </div>
      <div className="text-right">
        <p className="text-3xl font-bold text-theme-text">{entry.points ?? '—'}</p>
        {entry.formation_submitted ? (
          <Link
            className="mt-2 inline-block text-sm font-semibold text-theme-accent hover:text-theme-accent"
            to={`/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${entry.fantasy_team.id}/formation`}
          >
            {calculated ? t('results.viewTeamResult') : t('results.viewFormation')}
          </Link>
        ) : null}
      </div>
    </article>
  );
}
