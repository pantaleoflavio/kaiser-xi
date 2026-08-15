import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { formationsApi } from '../api/formations';
import { leaguesApi } from '../api/leagues';
import { headToHeadScheduleApi } from '../api/headToHeadSchedule';
import { formationKeys, leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { HeadToHeadMatchCard } from '../components/results/HeadToHeadMatchCard';
import { useTranslation } from '../i18n';
import { formatDate } from '../utils/formatters';

export function MatchdayDetailPage() {
  const { leagueId = '', matchdayId = '' } = useParams();
  const { language, t } = useTranslation();
  const numericId = Number(matchdayId);
  const matchdays = useQuery({
    queryKey: formationKeys.matchdays(leagueId),
    queryFn: () => formationsApi.matchdays(leagueId),
    enabled: Number.isInteger(numericId),
  });
  const teams = useQuery({
    queryKey: leagueKeys.fantasyTeams(leagueId),
    queryFn: () => leaguesApi.fantasyTeams(leagueId),
  });
  const league = useQuery({
    queryKey: leagueKeys.detail(leagueId),
    queryFn: () => leaguesApi.show(leagueId),
  });
  const schedule = useQuery({
    queryKey: leagueKeys.headToHeadSchedule(leagueId),
    queryFn: () => headToHeadScheduleApi.getSchedule(leagueId),
    enabled: league.data?.data.type.key === 'head_to_head',
  });
  if (matchdays.isLoading || teams.isLoading || league.isLoading || schedule.isLoading)
    return <LoadingState message={t('common.loading')} />;
  const matchday = matchdays.data?.data.find((item) => item.id === numericId);
  if (!matchday || matchdays.error)
    return (
      <ContentErrorPanel message={t('common.errors.notFound')} title={t('matchdays.notFound')} />
    );
  const myTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);
  const open = Date.now() < new Date(matchday.deadline).getTime();
  const scheduled =
    league.data?.data.type.key !== 'head_to_head' ||
    Boolean(
      schedule.data?.data.initialized &&
      schedule.data.data.matchdays.some((group) => group.matchday.id === numericId),
    );
  return (
    <section className="space-y-6">
      <LeagueNavigation
        leagueId={leagueId}
        myTeamId={myTeam?.id}
        showSchedule={league.data?.data.type.key === 'head_to_head'}
        showStandings={league.data?.data.type.key === 'head_to_head'}
      />
      <article className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
        <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
          {open ? t('matchdays.current') : t('matchdays.past')}
        </p>
        <h1 className="mt-2 text-3xl font-bold text-white">
          {matchday.name || t('formation.matchdayNumber', { number: matchday.number })}
        </h1>
        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-sm text-slate-400">{t('formation.deadline')}</dt>
            <dd className="mt-1 text-white">
              {formatDate(matchday.deadline, t('leagueDetail.notAvailable'), language)}
            </dd>
          </div>
        </dl>
        {open && myTeam && scheduled ? (
          <Link
            className="mt-6 inline-block rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950"
            to={`/leagues/${leagueId}/matchdays/${matchday.id}/fantasy-teams/${myTeam.id}/formation`}
          >
            {t('matchdays.openFormation')}
          </Link>
        ) : null}
      </article>
      {league.data?.data.type.key === 'head_to_head' ? (
        <section className="space-y-4" aria-labelledby="fixtures-title">
          <h2 className="text-2xl font-semibold text-white" id="fixtures-title">
            {t('results.matchResult')}
          </h2>
          {(
            schedule.data?.data.matchdays.find((group) => group.matchday.id === numericId)
              ?.fixtures ?? []
          ).map((fixture) => (
            <HeadToHeadMatchCard
              fixture={fixture}
              leagueId={leagueId}
              matchdayId={numericId}
              currentTeamId={myTeam?.id}
              key={fixture.id}
            />
          ))}
        </section>
      ) : null}
    </section>
  );
}
