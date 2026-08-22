import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { formationsApi } from '../api/formations';
import { headToHeadScheduleApi } from '../api/headToHeadSchedule';
import { leaguesApi } from '../api/leagues';
import { formationKeys, leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { MatchdayCard } from '../components/matchday/MatchdayCard';
import { buildMatchdayListPresentation } from '../components/matchday/matchdayListPresentation';
import { useTranslation } from '../i18n';

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

  const myTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);
  const isHeadToHead = league.data?.data.type.key === 'head_to_head';
  const scheduleInitialized = Boolean(schedule.data?.data.initialized);
  const items = buildMatchdayListPresentation(
    matchdays.data?.data ?? [],
    myTeam,
    schedule.data?.data,
    Date.now(),
  );

  return (
    <section className="space-y-6">
      <LeagueNavigation
        leagueId={leagueId}
        myTeamId={myTeam?.id}
        showSchedule={isHeadToHead}
        showStandings={['head_to_head', 'classic', 'formula_one'].includes(
          league.data?.data.type.key ?? '',
        )}
      />
      <div>
        <h1 className="text-3xl font-bold text-white">{t('matchdays.title')}</h1>
        <p className="mt-2 text-slate-300">{t('matchdays.description')}</p>
      </div>
      {isHeadToHead && !scheduleInitialized ? (
        <div className="rounded-xl border border-amber-500/40 bg-amber-950/20 p-5 text-amber-100">
          <p>{t('matchdays.scheduleNotInitialized')}</p>
          <Link
            className="mt-3 inline-block font-semibold text-amber-200 underline"
            to={`/leagues/${leagueId}/head-to-head-schedule`}
          >
            {t('matchdays.openScheduleSetup')}
          </Link>
        </div>
      ) : null}
      {league.data?.data.type.key === 'formula_one' && !league.data.data.competition_initialized ? (
        <div className="rounded-xl border border-amber-500/40 bg-amber-950/20 p-5 text-amber-100">
          <p>{t('formulaOne.notInitialized')}</p>
          {['commissioner', 'co_commissioner'].includes(league.data.data.my_role ?? '') ? (
            <Link
              className="mt-3 inline-block font-semibold text-amber-200 underline"
              to={`/leagues/${leagueId}/formula-one-championship`}
            >
              {t('formulaOne.initializeAction')}
            </Link>
          ) : null}
        </div>
      ) : null}
      {items.length ? (
        <div className="space-y-3">
          {items.map((item) => (
            <MatchdayCard {...item} key={item.item.id} leagueId={leagueId} myTeam={myTeam} />
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
