import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { StandingsTable } from '../components/results/StandingsTable';
import { useTranslation } from '../i18n';

export function LeagueStandingsPage() {
  const { leagueId = '' } = useParams();
  const { t } = useTranslation();
  const league = useQuery({
    queryKey: leagueKeys.detail(leagueId),
    queryFn: () => leaguesApi.show(leagueId),
  });
  const leagueType = league.data?.data.type.key;
  const teams = useQuery({
    queryKey: leagueKeys.fantasyTeams(leagueId),
    queryFn: () => leaguesApi.fantasyTeams(leagueId),
  });
  const standings = useQuery({
    queryKey: leagueKeys.standings(leagueId),
    queryFn: () => leaguesApi.standings(leagueId),
    enabled: ['head_to_head', 'classic', 'formula_one'].includes(league.data?.data.type.key ?? ''),
  });
  if (league.isLoading || teams.isLoading || standings.isLoading)
    return <LoadingState message={t('common.loading')} />;
  if (
    league.error ||
    standings.error ||
    !['head_to_head', 'classic', 'formula_one'].includes(leagueType ?? '')
  )
    return (
      <ContentErrorPanel
        title={t('standings.errorTitle')}
        message={standings.error?.message ?? t('h2h.notAvailable')}
      />
    );
  const myTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);
  return (
    <section className="space-y-6">
      <LeagueNavigation
        leagueId={leagueId}
        myTeamId={myTeam?.id}
        showSchedule={leagueType === 'head_to_head'}
        showStandings
      />
      <div>
        <h1 className="text-3xl font-bold text-white">{t('standings.title')}</h1>
        <p className="mt-2 text-slate-300">{t('standings.description')}</p>
      </div>
      {standings.data?.data.length ? (
        <StandingsTable
          standings={standings.data.data}
          currentTeamId={myTeam?.id}
          classic={leagueType === 'classic'}
          formulaOne={leagueType === 'formula_one'}
        />
      ) : (
        <p className="rounded-xl bg-slate-900/70 p-5 text-slate-300">{t('standings.empty')}</p>
      )}
    </section>
  );
}
