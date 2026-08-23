import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { StandingsTable } from '../components/results/StandingsTable';
import { FormulaOneStandingsTable } from '../components/results/FormulaOneStandingsTable';
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
    enabled: ['head_to_head', 'classic'].includes(league.data?.data.type.key ?? ''),
  });
  const formulaOneStandings = useQuery({
    queryKey: leagueKeys.standings(leagueId),
    queryFn: () => leaguesApi.formulaOneStandings(leagueId),
    enabled: leagueType === 'formula_one' && Boolean(league.data?.data.competition_initialized),
  });
  if (league.isLoading || teams.isLoading || standings.isLoading || formulaOneStandings.isLoading)
    return <LoadingState message={t('common.loading')} />;
  if (
    league.error ||
    standings.error ||
    formulaOneStandings.error ||
    !['head_to_head', 'classic', 'formula_one'].includes(leagueType ?? '')
  )
    return (
      <ContentErrorPanel
        title={t('standings.errorTitle')}
        message={(standings.error ?? formulaOneStandings.error)?.message ?? t('h2h.notAvailable')}
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
        <h1 className="text-3xl font-bold text-theme-text">
          {leagueType === 'formula_one' ? t('formulaOne.standingsTitle') : t('standings.title')}
        </h1>
        <p className="mt-2 text-theme-muted">
          {leagueType === 'formula_one'
            ? t('formulaOne.standingsDescription')
            : t('standings.description')}
        </p>
      </div>
      {leagueType === 'formula_one' && !league.data?.data.competition_initialized ? (
        <p className="rounded-xl border border-amber-500/40 bg-amber-950/20 p-5 text-amber-100">
          {t('formulaOne.notInitialized')}
        </p>
      ) : leagueType === 'formula_one' && formulaOneStandings.data?.data.length ? (
        <FormulaOneStandingsTable
          standings={formulaOneStandings.data.data}
          currentTeamId={myTeam?.id}
        />
      ) : standings.data?.data.length ? (
        <StandingsTable
          standings={standings.data.data}
          currentTeamId={myTeam?.id}
          classic={leagueType === 'classic'}
        />
      ) : (
        <p className="rounded-xl bg-theme-surface/70 p-5 text-theme-muted">
          {t('standings.empty')}
        </p>
      )}
    </section>
  );
}
