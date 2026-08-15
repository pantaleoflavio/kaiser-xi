import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { formationsApi } from '../../api/formations';
import { formationKeys } from '../../api/queryKeys';
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
  const homeFormation = useQuery({
    queryKey: formationKeys.detail(leagueId, matchdayId, String(fixture.home_fantasy_team.id)),
    queryFn: () => formationsApi.show(leagueId, matchdayId, String(fixture.home_fantasy_team.id)),
    retry: false,
  });
  const awayFormation = useQuery({
    queryKey: formationKeys.detail(leagueId, matchdayId, String(fixture.away_fantasy_team.id)),
    queryFn: () => formationsApi.show(leagueId, matchdayId, String(fixture.away_fantasy_team.id)),
    retry: false,
  });
  const formationVisible = (side: 'home' | 'away') =>
    team(side).id === currentTeamId ||
    Boolean(side === 'home' ? homeFormation.data : awayFormation.data);
  return (
    <article
      className={`rounded-2xl border p-5 ${isCurrent ? 'border-emerald-400/50 bg-emerald-950/20' : 'border-slate-800 bg-slate-900/70'}`}
    >
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
          {t('results.matchResult')}
        </h2>
        <span
          className={`text-xs font-semibold ${calculated ? 'text-emerald-200' : 'text-amber-200'}`}
        >
          {calculated ? t('results.finalScore') : t('results.pendingResult')}
        </span>
      </div>
      <div className="mt-5 grid grid-cols-[1fr_auto_1fr] items-start gap-3 text-center">
        {(['home', 'away'] as const).map((side, index) => (
          <div className={index === 1 ? 'col-start-3' : ''} key={side}>
            <h3 className="min-h-12 font-semibold text-white">{team(side).name}</h3>
            <p className="mt-2 text-4xl font-bold text-white">
              {calculated
                ? side === 'home'
                  ? fixture.result?.home_goals
                  : fixture.result?.away_goals
                : '—'}
            </p>
            {calculated ? (
              <p className="mt-2 text-sm text-slate-300">
                {t('results.fantasyPoints')}: {points(side)}
              </p>
            ) : null}
            {formationVisible(side) ? (
              <Link
                className="mt-4 inline-block text-sm font-semibold text-emerald-300 hover:text-emerald-200"
                to={`/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${team(side).id}/formation`}
                aria-label={t('results.viewTeamFormation', { team: team(side).name })}
              >
                {t('results.viewFormation')}
              </Link>
            ) : null}
          </div>
        ))}
        <span className="col-start-2 row-start-1 mt-16 text-xl text-slate-400" aria-hidden="true">
          –
        </span>
      </div>
      {!calculated ? (
        <p className="mt-4 text-center text-sm text-slate-300">{t('results.noCalculatedResult')}</p>
      ) : null}
    </article>
  );
}
