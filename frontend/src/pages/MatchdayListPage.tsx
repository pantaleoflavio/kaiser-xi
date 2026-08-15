import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import { formationsApi } from '../api/formations';
import { leaguesApi } from '../api/leagues';
import { headToHeadScheduleApi } from '../api/headToHeadSchedule';
import { formationKeys, leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { useTranslation } from '../i18n';
import type { Matchday } from '../types/formation';
import type { FantasyTeam } from '../types/league';
import { formatDate } from '../utils/formatters';

function MatchdayRow({
  item,
  leagueId,
  state,
  myTeam,
  opponent,
}: {
  item: Matchday;
  leagueId: string;
  state: 'past' | 'current' | 'upcoming';
  myTeam?: FantasyTeam;
  opponent?: string;
}) {
  const { language, t } = useTranslation();
  const formation = useQuery({
    queryKey: formationKeys.detail(leagueId, item.id, myTeam?.id ?? ''),
    queryFn: () => formationsApi.show(leagueId, item.id, String(myTeam!.id)),
    enabled: state === 'current' && Boolean(myTeam),
    retry: false,
  });
  const action = formation.data?.data.submitted
    ? 'viewFormation'
    : formation.data
      ? 'editFormation'
      : 'createFormation';
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
          {state !== 'past' && opponent ? (
            <p className="mt-2 text-sm font-semibold text-emerald-200">
              {t('h2h.opponent')}: {t('h2h.vsTeam', { team: opponent })}
            </p>
          ) : null}
        </div>
        <div className="flex flex-wrap gap-2">
          {state !== 'upcoming' ? (
            <Link
              className="rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-white"
              to={`/leagues/${leagueId}/matchdays/${item.id}`}
            >
              {t('matchdays.open')}
            </Link>
          ) : null}
          {state === 'current' &&
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
        </div>
      </div>
    </article>
  );
}

export function MatchdayListPage() {
  const { leagueId = '' } = useParams();
  const { t } = useTranslation();
  const league = useQuery({
    queryKey: leagueKeys.detail(leagueId),
    queryFn: () => leaguesApi.show(leagueId),
  });
  const teams = useQuery({
    queryKey: leagueKeys.fantasyTeams(leagueId),
    queryFn: () => leaguesApi.fantasyTeams(leagueId),
  });
  const matchdays = useQuery({
    queryKey: formationKeys.matchdays(leagueId),
    queryFn: () => formationsApi.matchdays(leagueId),
  });
  const schedule = useQuery({
    queryKey: leagueKeys.headToHeadSchedule(leagueId),
    queryFn: () => headToHeadScheduleApi.getSchedule(leagueId),
    enabled: league.data?.data.type.key === 'head_to_head',
    retry: false,
  });
  if (league.isLoading || teams.isLoading || matchdays.isLoading || schedule.isLoading)
    return <LoadingState message={t('common.loading')} />;
  if (league.error || matchdays.error)
    return (
      <ContentErrorPanel
        message={(league.error ?? matchdays.error)?.message ?? t('formation.errors.matchdays')}
        title={t('formation.errors.matchdays')}
      />
    );
  const items = matchdays.data?.data ?? [];
  const now = Date.now();
  const current = items.find((item) => new Date(item.deadline).getTime() > now);
  const stateFor = (item: Matchday) =>
    new Date(item.deadline).getTime() <= now
      ? ('past' as const)
      : item.id === current?.id
        ? ('current' as const)
        : ('upcoming' as const);
  const myTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);
  const opponentFor = (matchdayId: number) => {
    if (!myTeam || !schedule.data?.data.initialized) return undefined;
    const fixtures =
      schedule.data.data.matchdays.find((group) => group.matchday.id === matchdayId)?.fixtures ??
      [];
    const fixture = fixtures.find(
      (item) => item.home_fantasy_team.id === myTeam.id || item.away_fantasy_team.id === myTeam.id,
    );
    if (!fixture) return undefined;
    return fixture.home_fantasy_team.id === myTeam.id
      ? fixture.away_fantasy_team.name
      : fixture.home_fantasy_team.name;
  };
  return (
    <section className="space-y-6">
      <LeagueNavigation
        leagueId={leagueId}
        myTeamId={myTeam?.id}
        showSchedule={league.data?.data.type.key === 'head_to_head'}
      />
      <div>
        <h1 className="text-3xl font-bold text-white">{t('matchdays.title')}</h1>
        <p className="mt-2 text-slate-300">{t('matchdays.description')}</p>
      </div>
      {items.length ? (
        <div className="space-y-3">
          {items.map((item) => (
            <MatchdayRow
              item={item}
              key={item.id}
              leagueId={leagueId}
              myTeam={myTeam}
              opponent={opponentFor(item.id)}
              state={stateFor(item)}
            />
          ))}
        </div>
      ) : (
        <p className="rounded-xl bg-slate-900/70 p-5 text-slate-300">
          {t('formation.noMatchdays')}
        </p>
      )}
    </section>
  );
}
