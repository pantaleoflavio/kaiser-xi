import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { LeaguePlayersSection } from '../components/league/LeaguePlayersSection';
import { LoadingState } from '../components/LoadingState';
import { useTranslation } from '../i18n';

export function LeaguePlayersPage() {
  const { leagueId = '' } = useParams();
  const { t } = useTranslation();
  const league = useQuery({
    queryKey: leagueKeys.detail(leagueId),
    queryFn: () => leaguesApi.show(leagueId),
    enabled: Boolean(leagueId),
  });
  const teams = useQuery({
    queryKey: leagueKeys.fantasyTeams(leagueId),
    queryFn: () => leaguesApi.fantasyTeams(leagueId),
    enabled: Boolean(leagueId),
  });

  if (league.isLoading) return <LoadingState message={t('common.loading')} />;
  if (!league.data?.data) {
    return (
      <ContentErrorPanel
        message={league.error?.message ?? t('leagueDetail.error')}
        title={t('leagueDetail.errorTitle')}
      />
    );
  }

  const data = league.data.data;
  const myTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);

  return (
    <section className="space-y-6">
      <LeagueNavigation
        leagueId={leagueId}
        myTeamId={myTeam?.id}
        showSchedule={data.type.key === 'head_to_head'}
        showStandings={['head_to_head', 'classic', 'formula_one'].includes(data.type.key)}
      />
      <LeaguePlayersSection leagueId={leagueId} seasonId={data.season.id} />
    </section>
  );
}
