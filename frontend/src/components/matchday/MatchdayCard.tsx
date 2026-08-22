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
    enabled: formationAllowed && state !== 'past' && Boolean(myTeam),
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
    enabled: formationAllowed && state === 'current' && Boolean(opponentTeam),
    retry: false,
  });

  return (
    <article
      className={`rounded-xl border p-5 ${state === 'current' ? 'border-emerald-400/60 bg-emerald-950/20' : 'border-slate-800 bg-slate-900/70'}`}
    >
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-emerald-300">
            {t(`matchdays.${state}`)}
          </p>
          <h2 className="mt-1 text-xl font-semibold text-white">
            {item.name || t('formation.matchdayNumber', { number: item.number })}
          </h2>
          <p className="mt-2 text-sm text-slate-300">
            {t('formation.deadline')}:{' '}
            {formatDate(item.deadline, t('leagueDetail.notAvailable'), language)}
          </p>
          {state !== 'past' && opponentTeam ? (
            <p className="mt-2 text-sm font-semibold text-emerald-200">
              {t('h2h.opponent')}: {t('h2h.vsTeam', { team: opponentTeam.name })}
            </p>
          ) : null}
          {state !== 'past' && scheduleInitialized && !fixture ? (
            <p className="mt-2 text-sm font-semibold text-slate-300">{t('h2h.restDay')}</p>
          ) : null}
          {state === 'past' && fixture ? (
            fixture.result?.status === 'calculated' ? (
              <p className="mt-3 font-semibold text-white">
                {fixture.home_fantasy_team.name} {fixture.result.home_goals}–
                {fixture.result.away_goals} {fixture.away_fantasy_team.name}
              </p>
            ) : (
              <p className="mt-3 text-sm font-semibold text-amber-200">
                {t('results.pendingResult')}
              </p>
            )
          ) : null}
        </div>
        <div className="flex flex-wrap gap-2">
          <Link
            className="rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-white"
            to={`/leagues/${leagueId}/matchdays/${item.id}`}
          >
            {t('matchdays.open')}
          </Link>
          {state !== 'past' &&
          formationAllowed &&
          myTeam &&
          !formation.isLoading &&
          (!formation.error ||
            (formation.error instanceof ApiError && formation.error.status === 404)) ? (
            <Link
              className="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-semibold text-slate-950"
              to={`/leagues/${leagueId}/matchdays/${item.id}/fantasy-teams/${myTeam.id}/formation`}
            >
              {t(`matchdays.${action}`)}
            </Link>
          ) : null}
          {state === 'current' && opponentTeam && opponentFormation.data ? (
            <Link
              className="rounded-lg border border-emerald-500 px-3 py-2 text-sm font-semibold text-emerald-200"
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
