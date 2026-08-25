import { Link } from 'react-router-dom';
import type { HeadToHeadFixture } from '../../types/api';
import { useTranslation } from '../../i18n';

export function HeadToHeadMatchCard({
  fixture,
  leagueId,
  matchdayId,
  currentTeamId,
}: {
  fixture: HeadToHeadFixture;
  leagueId: string;
  matchdayId: number;
  currentTeamId?: number;
}) {
  const { t } = useTranslation();
  const calculated = fixture.result?.status === 'calculated';
  const isCurrent =
    fixture.home_fantasy_team.id === currentTeamId ||
    fixture.away_fantasy_team.id === currentTeamId;
  const team = (side: 'home' | 'away') =>
    side === 'home' ? fixture.home_fantasy_team : fixture.away_fantasy_team;
  const points = (side: 'home' | 'away') =>
    side === 'home' ? fixture.result?.home_points : fixture.result?.away_points;
  return (
    <article
      className={`rounded-2xl border p-5 ${isCurrent ? 'border-theme-primary/50 bg-emerald-950/20' : 'border-theme-border bg-theme-surface/70'}`}
    >
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-theme-accent">
          {isCurrent ? t('results.yourFixture') : t('results.matchResult')}
        </h2>
        <span
          className={`text-xs font-semibold ${calculated ? 'text-theme-accent' : 'text-amber-200'}`}
        >
          {calculated ? t('results.finalScore') : t('results.pendingResult')}
        </span>
      </div>
      <div className="mt-5 grid grid-cols-[1fr_auto_1fr] items-start gap-3 text-center">
        {(['home', 'away'] as const).map((side, index) => (
          <div className={index === 1 ? 'col-start-3' : ''} key={side}>
            <h3 className="min-h-12 font-semibold text-theme-text">{team(side).name}</h3>
            <p className="mt-2 text-4xl font-bold text-theme-text">
              {calculated
                ? side === 'home'
                  ? fixture.result?.home_goals
                  : fixture.result?.away_goals
                : '—'}
            </p>
            {calculated ? (
              <p className="mt-2 text-sm text-theme-muted">
                {t('results.fantasyPoints')}: {points(side)}
              </p>
            ) : null}
            <Link
              className="mt-4 inline-block text-sm font-semibold text-theme-accent hover:text-theme-accent"
              to={`/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${team(side).id}/formation`}
              aria-label={t('results.viewTeamFormation', { team: team(side).name })}
            >
              {calculated ? t('results.viewTeamResult') : t('results.viewFormation')}
            </Link>
          </div>
        ))}
        <span className="col-start-2 row-start-1 mt-16 text-xl text-theme-muted" aria-hidden="true">
          –
        </span>
      </div>
      {!calculated ? (
        <p className="mt-4 text-center text-sm text-theme-muted">
          {t('results.noCalculatedResult')}
        </p>
      ) : null}
    </article>
  );
}
