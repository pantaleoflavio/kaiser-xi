import { useQuery } from '@tanstack/react-query';
import { Link, useLocation, useParams } from 'react-router-dom';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { headToHeadScheduleApi } from '../api/headToHeadSchedule';
import { LeagueMemberList } from '../components/LeagueMemberList';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { LeagueSummary } from '../components/league/LeagueSummary';
import { useTranslation } from '../i18n';

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
  const scheduleQuery = useQuery({
    queryKey: leagueKeys.headToHeadSchedule(leagueId),
    queryFn: () => headToHeadScheduleApi.getSchedule(leagueId),
    enabled: league?.type.key === 'head_to_head',
  });
  const myTeam = teamsQuery.data?.data.find((team) => team.is_owned_by_current_user);

  if (leagueQuery.isLoading) return <LoadingState message={t('leagueDetail.loading')} />;

  return (
    <section className="space-y-6">
      <Link
        className="text-sm font-semibold text-theme-accent hover:text-theme-accent"
        to="/leagues"
      >
        {t('leagueDetail.backToLeagues')}
      </Link>
      {navigationState?.success ? (
        <div
          className="rounded-xl border border-theme-primary/30 bg-emerald-950/30 p-4 text-sm text-emerald-100"
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
          <LeagueNavigation
            leagueId={leagueId}
            myTeamId={myTeam?.id}
            showSchedule={league.type.key === 'head_to_head'}
            showStandings={['head_to_head', 'classic', 'formula_one'].includes(league.type.key)}
          />
          <LeagueSummary league={league} />
          <div className="grid gap-4 md:grid-cols-2">
            <Link
              className="rounded-2xl border border-theme-border bg-theme-surface/70 p-5 transition hover:border-theme-primary/40"
              to={`/leagues/${leagueId}/matchdays`}
            >
              <h2 className="text-xl font-semibold text-theme-text">
                {t('leagueNavigation.matchdays')}
              </h2>
              <p className="mt-2 text-sm text-theme-muted">
                {t('leagueNavigation.matchdaysDescription')}
              </p>
            </Link>
            {['head_to_head', 'classic', 'formula_one'].includes(league.type.key) ? (
              <Link
                className="rounded-2xl border border-theme-border bg-theme-surface/70 p-5 transition hover:border-theme-primary/40"
                to={`/leagues/${leagueId}/standings`}
              >
                <h2 className="text-xl font-semibold text-theme-text">{t('standings.title')}</h2>
                <p className="mt-2 text-sm text-theme-muted">{t('standings.description')}</p>
              </Link>
            ) : null}
            {league.type.key === 'head_to_head' ? (
              <Link
                className="rounded-2xl border border-theme-border bg-theme-surface/70 p-5 transition hover:border-theme-primary/40"
                to={`/leagues/${leagueId}/head-to-head-schedule`}
              >
                <h2 className="text-xl font-semibold text-theme-text">{t('h2h.title')}</h2>
                <p className="mt-2 text-sm text-theme-muted">
                  {scheduleQuery.data?.data.initialized
                    ? t('h2h.startedFrom', {
                        matchday:
                          scheduleQuery.data.data.start_matchday?.name ||
                          t('formation.matchdayNumber', {
                            number: scheduleQuery.data.data.start_matchday?.number,
                          }),
                      })
                    : t('h2h.notStarted')}
                </p>
                <span className="mt-4 inline-block text-sm font-semibold text-theme-accent">
                  {scheduleQuery.data?.data.initialized
                    ? t('h2h.viewSchedule')
                    : t('h2h.configureStart')}
                </span>
              </Link>
            ) : null}
            {league.type.key === 'classic' ? (
              <Link
                className="rounded-2xl border border-theme-border bg-theme-surface/70 p-5"
                to={`/leagues/${leagueId}/classic-championship`}
              >
                <h2 className="text-xl font-semibold text-theme-text">{t('classic.title')}</h2>
                <p className="mt-2 text-sm text-theme-muted">
                  {league.competition_initialized ? t('classic.started') : t('classic.notStarted')}
                </p>
              </Link>
            ) : null}
            {league.type.key === 'formula_one' ? (
              <Link
                className="rounded-2xl border border-theme-border bg-theme-surface/70 p-5"
                to={`/leagues/${leagueId}/formula-one-championship`}
              >
                <h2 className="text-xl font-semibold text-theme-text">
                  {t('formulaOne.initializationTitle')}
                </h2>
                <p className="mt-2 text-sm text-theme-muted">
                  {league.competition_initialized ? t('classic.started') : t('classic.notStarted')}
                </p>
              </Link>
            ) : null}
            <Link
              className="rounded-2xl border border-theme-border bg-theme-surface/70 p-5 transition hover:border-theme-primary/40"
              to={`/leagues/${leagueId}/rules`}
            >
              <h2 className="text-xl font-semibold text-theme-text">
                {t('leagueNavigation.rules')}
              </h2>
              <p className="mt-2 text-sm text-theme-muted">
                {t('leagueNavigation.rulesDescription')}
              </p>
            </Link>
            <Link
              className="rounded-2xl border border-theme-border bg-theme-surface/70 p-5 transition hover:border-theme-primary/40"
              to={
                myTeam
                  ? `/leagues/${leagueId}/fantasy-teams/${myTeam.id}`
                  : `/leagues/${leagueId}/fantasy-teams`
              }
            >
              <h2 className="text-xl font-semibold text-theme-text">
                {myTeam ? t('leagueNavigation.myTeam') : t('leagueNavigation.fantasyTeams')}
              </h2>
              <p className="mt-2 text-sm text-theme-muted">
                {myTeam ? myTeam.name : t('leagueNavigation.teamsDescription')}
              </p>
            </Link>
          </div>
          <section className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
            <h2 className="text-2xl font-semibold text-theme-text">
              {t('leagueDetail.members.title')}
            </h2>
            <p className="mt-1 text-sm text-theme-muted">{t('leagueDetail.members.description')}</p>

            <LeagueMemberList league={league} />
          </section>
        </div>
      ) : null}
    </section>
  );
}
