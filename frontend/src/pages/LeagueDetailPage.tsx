import { useQuery } from '@tanstack/react-query';
import { Link, useLocation, useParams } from 'react-router-dom';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { LeagueMemberList } from '../components/LeagueMemberList';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { LeagueSummary } from '../components/league/LeagueSummary';
import { useTranslation } from '../i18n';
import type { FantasyTeam, League, LeagueInvitation, LeagueSettings } from '../types/league';

export function LeagueDetailPage() {
  const { leagueId = '' } = useParams();
  const location = useLocation();
  const navigationState = location.state as { success?: string } | null;
  const { t } = useTranslation();
  const leagueQuery = useQuery({
    queryKey: leagueKeys.detail(leagueId),
    queryFn: () => leaguesApi.show(leagueId),
    enabled: Boolean(leagueId),
  });
  const teamsQuery = useQuery({
    queryKey: leagueKeys.fantasyTeams(leagueId),
    queryFn: () => leaguesApi.fantasyTeams(leagueId),
    enabled: Boolean(leagueId),
  });
  const league = leagueQuery.data?.data;
  const myTeam = teamsQuery.data?.data.find((team) => team.is_owned_by_current_user);

  if (leagueQuery.isLoading) return <LoadingState message={t('leagueDetail.loading')} />;

  return (
    <section className="space-y-6">
      <Link className="text-sm font-semibold text-emerald-300 hover:text-emerald-200" to="/leagues">
        {t('leagueDetail.backToLeagues')}
      </Link>
      {navigationState?.success ? (
        <div
          className="rounded-xl border border-emerald-400/30 bg-emerald-950/30 p-4 text-sm text-emerald-100"
          role="status"
        >
          {navigationState.success}
        </div>
      ) : null}

      {leagueQuery.error ? (
        <ContentErrorPanel
          message={leagueQuery.error.message}
          title={t('leagueDetail.errorTitle')}
        />
      ) : null}
      {league ? (
        <div className="space-y-6">
          <LeagueNavigation leagueId={leagueId} myTeamId={myTeam?.id} />
          <LeagueSummary league={league} />
          <div className="grid gap-4 md:grid-cols-2">
            <Link
              className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 transition hover:border-emerald-400/40"
              to={`/leagues/${leagueId}/matchdays`}
            >
              <h2 className="text-xl font-semibold text-white">
                {t('leagueNavigation.matchdays')}
              </h2>
              <p className="mt-2 text-sm text-slate-300">
                {t('leagueNavigation.matchdaysDescription')}
              </p>
            </Link>
            <Link
              className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 transition hover:border-emerald-400/40"
              to={`/leagues/${leagueId}/rules`}
            >
              <h2 className="text-xl font-semibold text-white">{t('leagueNavigation.rules')}</h2>
              <p className="mt-2 text-sm text-slate-300">
                {t('leagueNavigation.rulesDescription')}
              </p>
            </Link>
            <Link
              className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 transition hover:border-emerald-400/40"
              to={
                myTeam
                  ? `/leagues/${leagueId}/fantasy-teams/${myTeam.id}`
                  : `/leagues/${leagueId}/fantasy-teams`
              }
            >
              <h2 className="text-xl font-semibold text-white">
                {myTeam ? t('leagueNavigation.myTeam') : t('leagueNavigation.fantasyTeams')}
              </h2>
              <p className="mt-2 text-sm text-slate-300">
                {myTeam ? myTeam.name : t('leagueNavigation.teamsDescription')}
              </p>
            </Link>
          </div>
          <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h2 className="text-2xl font-semibold text-white">{t('leagueDetail.members.title')}</h2>
            <p className="mt-1 text-sm text-slate-300">{t('leagueDetail.members.description')}</p>

            <LeagueMemberList league={league} />
          </section>
        </div>
      ) : null}
    </section>
  );
}
