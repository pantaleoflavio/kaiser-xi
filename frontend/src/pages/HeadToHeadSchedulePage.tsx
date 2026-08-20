import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import { formationsApi } from '../api/formations';
import { headToHeadScheduleApi } from '../api/headToHeadSchedule';
import { leaguesApi } from '../api/leagues';
import { formationKeys, leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { HeadToHeadScheduleSetup } from '../components/league/HeadToHeadScheduleSetup';
import { HeadToHeadScheduleSummary } from '../components/league/HeadToHeadScheduleSummary';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { useTranslation } from '../i18n';
import { championshipInitializationError } from '../utils/championshipInitializationError';

export function HeadToHeadSchedulePage() {
  const { leagueId = '' } = useParams();
  const { t } = useTranslation();
  const queryClient = useQueryClient();
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
  const initialize = useMutation({
    mutationFn: (startMatchdayId: number) =>
      headToHeadScheduleApi.initializeSchedule(leagueId, { start_matchday_id: startMatchdayId }),
    onSuccess: (response) => {
      queryClient.setQueryData(leagueKeys.headToHeadSchedule(leagueId), response);
    },
    onError: (error) => {
      if (error instanceof ApiError && error.status === 409) {
        void queryClient.invalidateQueries({ queryKey: leagueKeys.headToHeadSchedule(leagueId) });
      }
    },
  });

  if (league.isLoading || teams.isLoading || matchdays.isLoading || schedule.isLoading) {
    return <LoadingState message={t('common.loading')} />;
  }
  if (!league.data?.data || league.data.data.type.key !== 'head_to_head') {
    return <ContentErrorPanel message={t('h2h.notAvailable')} title={t('h2h.title')} />;
  }
  if (schedule.error || matchdays.error) {
    return <ContentErrorPanel message={t('h2h.errors.load')} title={t('h2h.title')} />;
  }

  const myTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);
  const canInitialize = ['commissioner', 'co_commissioner'].includes(
    league.data.data.my_role ?? '',
  );
  const futureMatchdays = (matchdays.data?.data ?? []).filter(
    (matchday) => new Date(matchday.starts_at).getTime() > Date.now(),
  );
  const errorMessage = championshipInitializationError(initialize.error, t, {
    alreadyInitializedKey: 'h2h.errors.alreadyInitialized',
  });

  return (
    <section className="space-y-6">
      <LeagueNavigation leagueId={leagueId} myTeamId={myTeam?.id} showSchedule showStandings />
      {schedule.data?.data.initialized ? (
        <HeadToHeadScheduleSummary schedule={schedule.data.data} />
      ) : (
        <HeadToHeadScheduleSetup
          canInitialize={canInitialize}
          error={errorMessage}
          isPending={initialize.isPending}
          matchdays={futureMatchdays}
          maximumParticipants={league.data.data.max_participants}
          onConfirm={(matchdayId) => initialize.mutate(matchdayId)}
          participantCount={schedule.data?.data.participant_count ?? teams.data?.data.length ?? 0}
        />
      )}
    </section>
  );
}
