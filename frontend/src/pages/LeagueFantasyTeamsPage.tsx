import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { FantasyTeamsPanel } from '../components/league/FantasyTeamsPanel';
import { InvitationManagementPanel } from '../components/league/InvitationManagementPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { useTranslation } from '../i18n';

export function LeagueFantasyTeamsPage() {
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
  const canInvite = ['commissioner', 'co_commissioner'].includes(league.data?.data.my_role ?? '');
  const invitations = useQuery({
    queryKey: leagueKeys.invitations(leagueId),
    queryFn: () => leaguesApi.invitations(leagueId),
    enabled: canInvite,
  });
  if (league.isLoading || teams.isLoading) return <LoadingState message={t('common.loading')} />;
  if (!league.data?.data)
    return (
      <ContentErrorPanel message={t('leagueDetail.error')} title={t('leagueDetail.errorTitle')} />
    );
  const myTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);
  return (
    <section className="space-y-6">
      <LeagueNavigation leagueId={leagueId} myTeamId={myTeam?.id} />
      <FantasyTeamsPanel
        league={league.data.data}
        initialTeams={teams.data?.data ?? []}
        initialError={teams.error?.message ?? null}
      />
      {canInvite ? (
        <InvitationManagementPanel
          leagueId={league.data.data.id}
          initialInvitations={invitations.data?.data ?? []}
          initialError={invitations.error?.message ?? null}
        />
      ) : null}
    </section>
  );
}
