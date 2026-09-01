import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { ApiError } from '../../api/client';
import { formationsApi } from '../../api/formations';
import { formationKeys } from '../../api/queryKeys';
import { useTranslation } from '../../i18n';
import type { FantasyTeam } from '../../types/league';
import { formatDate } from '../../utils/formatters';
import type { MatchdayListItemPresentation } from './matchdayListPresentation';

type MatchdayCardProps = MatchdayListItemPresentation & {
  leagueId: string;
  myTeam?: FantasyTeam;
};

export function MatchdayCard({
  item,
  leagueId,
  state,
  myTeam,
  fixture,
  scheduleInitialized,
  formationAllowed,
}: MatchdayCardProps) {
  const { language, t } = useTranslation();
  const formation = useQuery({
    queryKey: formationKeys.detail(leagueId, item.id, myTeam?.id ?? ''),
    queryFn: () => formationsApi.show(leagueId, item.id, String(myTeam?.id ?? '')),
    enabled: state !== 'past' && (formationAllowed || state === 'current') && Boolean(myTeam),
    retry: false,
  });
  const action = formation.data?.data.submitted
    ? 'viewFormation'
    : formation.data
      ? 'editFormation'
      : 'createFormation';
  const opponentTeam = fixture
    ? fixture.home_fantasy_team.id === myTeam?.id
      ? fixture.away_fantasy_team
      : fixture.home_fantasy_team
    : undefined;
  const opponentFormation = useQuery({
    queryKey: formationKeys.detail(leagueId, item.id, String(opponentTeam?.id ?? '')),
    queryFn: () => formationsApi.show(leagueId, item.id, String(opponentTeam?.id ?? '')),
    enabled: state === 'current' && Boolean(opponentTeam),
    retry: false,
  });

  return (
    <article
      className={`rounded-xl border p-5 ${state === 'current' ? 'border-theme-primary/60 bg-emerald-950/20' : 'border-theme-border bg-theme-surface/70'}`}
    >
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-theme-accent">
            {t(`matchdays.${state}`)}
          </p>
          <h2 className="mt-1 text-xl font-semibold text-theme-text">
            {item.name || t('formation.matchdayNumber', { number: item.number })}
          </h2>
          <p className="mt-2 text-sm text-theme-muted">
            {t('formation.deadline')}:{' '}
            {formatDate(item.deadline, t('leagueDetail.notAvailable'), language)}
          </p>
          <p className="mt-1 text-sm text-theme-muted">
            {t('matchdays.timeWindow')}:{' '}
            {formatDate(item.starts_at, t('leagueDetail.notAvailable'), language)} –{' '}
            {formatDate(item.ends_at, t('leagueDetail.notAvailable'), language)}
          </p>
          {state !== 'past' && opponentTeam ? (
            <p className="mt-2 text-sm font-semibold text-theme-accent">
              {t('h2h.opponent')}: {t('h2h.vsTeam', { team: opponentTeam.name })}
            </p>
          ) : null}
          {state !== 'past' && scheduleInitialized && !fixture ? (
            <p className="mt-2 text-sm font-semibold text-slate-300">{t('h2h.restDay')}</p>
          ) : null}
          {state === 'past' && fixture ? (
            fixture.result?.status === 'calculated' ? (
              <p className="mt-3 font-semibold text-theme-text">
                {fixture.home_fantasy_team.name} {fixture.result.home_goals}–
                {fixture.result.away_goals} {fixture.away_fantasy_team.name}
              </p>
            ) : (
              <p className="mt-3 text-sm font-semibold text-amber-200">
                {t('results.pendingResult')}
              </p>
            )
          ) : null}
          {item.is_waiting_for_calculation_unlock ? (
            <p className="mt-3 inline-flex rounded-full border border-sky-400/30 bg-sky-950/30 px-3 py-1 text-sm font-medium text-sky-200">
              {t('matchdays.waitingForCalculationUnlock')}
            </p>
          ) : null}
        </div>
        <div className="flex flex-wrap gap-2">
          <Link
            className="rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-theme-text"
            to={`/leagues/${leagueId}/matchdays/${item.id}`}
          >
            {t('matchdays.open')}
          </Link>
          {state !== 'past' &&
          (formationAllowed || state === 'current') &&
          myTeam &&
          !formation.isLoading &&
          (!formation.error ||
            (formation.error instanceof ApiError && formation.error.status === 404)) ? (
            <Link
              className="rounded-lg bg-theme-primary px-3 py-2 text-sm font-semibold text-theme-primary-foreground"
              to={`/leagues/${leagueId}/matchdays/${item.id}/fantasy-teams/${myTeam.id}/formation`}
            >
              {t(`matchdays.${action}`)}
            </Link>
          ) : null}
          {state === 'current' && opponentTeam && opponentFormation.data ? (
            <Link
              className="rounded-lg border border-theme-primary px-3 py-2 text-sm font-semibold text-theme-accent"
              to={`/leagues/${leagueId}/matchdays/${item.id}/fantasy-teams/${opponentTeam.id}/formation`}
            >
              {t('matchdays.viewOpponentFormation')}
            </Link>
          ) : null}
        </div>
      </div>
    </article>
  );
}
