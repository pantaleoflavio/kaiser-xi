import { useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';
import { LoadingState } from '../components/LoadingState';
import { LeagueMemberList } from '../components/LeagueMemberList';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { FantasyTeamsPanel } from '../components/league/FantasyTeamsPanel';
import { InvitationManagementPanel } from '../components/league/InvitationManagementPanel';
import { LeagueSettingsPanel } from '../components/league/LeagueSettingsPanel';
import { LeagueSummary } from '../components/league/LeagueSummary';
import { useTranslation } from '../i18n';
import type { FantasyTeam, League, LeagueInvitation, LeagueSettings } from '../types/league';

type DetailData = {
  league: League | null;
  settings: LeagueSettings | null;
  invitations: LeagueInvitation[];
  teams: FantasyTeam[];
  detailError: string | null;
  settingsError: string | null;
  invitationsError: string | null;
  teamsError: string | null;
};

function settingsLoadError(error: unknown, fallback: string, t: (key: string) => string) {
  if (error instanceof ApiError) {
    if (error.status === 403) return t('common.errors.forbidden');
    if (error.status === 404) return t('common.errors.notFound');
    if (error.status === 422) return t('common.errors.validation');
    return error.message;
  }
  return error instanceof Error ? error.message : fallback;
}

const initialData: DetailData = {
  league: null,
  settings: null,
  invitations: [],
  teams: [],
  detailError: null,
  settingsError: null,
  invitationsError: null,
  teamsError: null,
};

export function LeagueDetailPage() {
  const { leagueId } = useParams();
  const location = useLocation();
  const navigationState = location.state as { success?: string } | null;
  const { language, t } = useTranslation();
  const [data, setData] = useState<DetailData>(initialData);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (!leagueId) return;
    let mounted = true;
    async function load() {
      setIsLoading(true);
      const [league, settings, invitations, teams] = await Promise.allSettled([
        leaguesApi.show(leagueId!),
        leaguesApi.settings(leagueId!),
        leaguesApi.invitations(leagueId!),
        leaguesApi.fantasyTeams(leagueId!),
      ]);
      if (!mounted) return;
      setData({
        league: league.status === 'fulfilled' ? league.value.data : null,
        settings: settings.status === 'fulfilled' ? settings.value.data : null,
        invitations: invitations.status === 'fulfilled' ? invitations.value.data : [],
        teams: teams.status === 'fulfilled' ? teams.value.data : [],
        detailError:
          league.status === 'rejected'
            ? league.reason instanceof Error
              ? league.reason.message
              : t('leagueDetail.error')
            : null,
        settingsError:
          settings.status === 'rejected'
            ? settingsLoadError(settings.reason, t('leagueSettings.errors.load'), t)
            : null,
        invitationsError:
          invitations.status === 'rejected'
            ? invitations.reason instanceof Error
              ? invitations.reason.message
              : t('leagueDetail.invitations.error')
            : null,
        teamsError:
          teams.status === 'rejected'
            ? teams.reason instanceof Error
              ? teams.reason.message
              : t('fantasyTeams.errors.list')
            : null,
      });
      setIsLoading(false);
    }

    void load();
    return () => {
      mounted = false;
    };
  }, [language, leagueId]);

  if (isLoading) return <LoadingState message={t('leagueDetail.loading')} />;

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

      {data.detailError ? (
        <ContentErrorPanel message={data.detailError} title={t('leagueDetail.errorTitle')} />
      ) : null}

      {data.league ? (
        <div className="space-y-6">
          <LeagueSummary league={data.league} />
          <div id="league-settings">
            <LeagueSettingsPanel
              key={`${data.league.id}-${data.league.status.key}-${data.settings?.status ?? 'none'}`}
              initialError={data.settingsError}
              initialSettings={data.settings}
              league={data.league}
            />
          </div>
          <FantasyTeamsPanel
            initialError={data.teamsError}
            initialTeams={data.teams}
            league={data.league}
          />
          
          <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h2 className="text-2xl font-semibold text-white">{t('leagueDetail.members.title')}</h2>
            <p className="mt-1 text-sm text-slate-300">{t('leagueDetail.members.description')}</p>
            <LeagueMemberList league={data.league} />
          </section>
          {['commissioner', 'co_commissioner'].includes(data.league.my_role ?? '') ? (
            <InvitationManagementPanel
              initialError={data.invitationsError}
              initialInvitations={data.invitations}
              leagueId={data.league.id}
            />
          ) : null}
        </div>
      ) : null}
    </section>
  );
}