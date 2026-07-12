import { FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';
import { LoadingState } from '../components/LoadingState';
import { useTranslation } from '../i18n';
import type { FantasyTeam, League, LeagueInvitation, LeagueMember } from '../types/league';

type LoadableError = string | null;

type ErrorPanelProps = {
  message: string;
  title: string;
  details?: string[];
};

function ErrorPanel({ details = [], message, title }: ErrorPanelProps) {
  return (
    <div className="rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100">
      <p className="font-semibold">{title}</p>
      <p className="mt-1 text-red-100/80">{message}</p>
      {details.length > 0 ? (
        <ul className="mt-3 list-disc space-y-1 pl-5 text-red-100/80">
          {details.map((detail) => (
            <li key={detail}>{detail}</li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}

function EmptyPanel({ message, title }: { message: string; title: string }) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-950/60 p-5 text-center">
      <h3 className="font-semibold text-white">{title}</h3>
      <p className="mt-2 text-sm text-slate-300">{message}</p>
    </div>
  );
}

function formatDate(value: string | null, fallback: string, locale: string) {
  if (!value) return fallback;
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(value),
  );
}

function validationDetails(error: unknown) {
  if (!(error instanceof ApiError) || !error.errors) return [];
  return Object.values(error.errors).flat();
}

export function LeagueDetailPage() {
  const { leagueId } = useParams();
  const { language, t } = useTranslation();
  const [league, setLeague] = useState<League | null>(null);
  const [members, setMembers] = useState<LeagueMember[]>([]);
  const [invitations, setInvitations] = useState<LeagueInvitation[]>([]);
  const [fantasyTeams, setFantasyTeams] = useState<FantasyTeam[]>([]);
  const [detailError, setDetailError] = useState<LoadableError>(null);
  const [membersError, setMembersError] = useState<LoadableError>(null);
  const [invitationsError, setInvitationsError] = useState<LoadableError>(null);
  const [fantasyTeamsError, setFantasyTeamsError] = useState<LoadableError>(null);
  const [createTeamError, setCreateTeamError] = useState<LoadableError>(null);
  const [createTeamErrorDetails, setCreateTeamErrorDetails] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isCreatingInvitation, setIsCreatingInvitation] = useState(false);
  const [isCreatingTeam, setIsCreatingTeam] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [maxUses, setMaxUses] = useState('');
  const [expiresAt, setExpiresAt] = useState('');
  const [teamName, setTeamName] = useState('');

  const ownedFantasyTeam = fantasyTeams.find((team) => team.is_owned_by_current_user) ?? null;

  async function loadInvitations(currentLeagueId: string) {
    try {
      setInvitationsError(null);
      const response = await leaguesApi.invitations(currentLeagueId);
      setInvitations(response.data);
    } catch (err) {
      setInvitationsError(err instanceof Error ? err.message : t('leagueDetail.invitations.error'));
    }
  }

  async function loadFantasyTeams(currentLeagueId: string) {
    try {
      setFantasyTeamsError(null);
      const response = await leaguesApi.fantasyTeams(currentLeagueId);
      setFantasyTeams(response.data);
    } catch (err) {
      setFantasyTeamsError(err instanceof Error ? err.message : t('fantasyTeams.errors.list'));
    }
  }

  useEffect(() => {
    if (!leagueId) return;
    const currentLeagueId: string = leagueId;
    let isMounted = true;

    async function loadLeagueDetail() {
      try {
        setIsLoading(true);
        setDetailError(null);
        setMembersError(null);
        setInvitationsError(null);
        setFantasyTeamsError(null);
        setCreateTeamError(null);
        setCreateTeamErrorDetails([]);
        const [leagueResponse, membersResponse, invitationsResponse, fantasyTeamsResponse] =
          await Promise.allSettled([
            leaguesApi.show(currentLeagueId),
            leaguesApi.members(currentLeagueId),
            leaguesApi.invitations(currentLeagueId),
            leaguesApi.fantasyTeams(currentLeagueId),
          ]);
        if (!isMounted) return;

        if (leagueResponse.status === 'fulfilled') setLeague(leagueResponse.value.data);
        else {
          setDetailError(
            leagueResponse.reason instanceof Error
              ? leagueResponse.reason.message
              : t('leagueDetail.error'),
          );
        }

        if (membersResponse.status === 'fulfilled') setMembers(membersResponse.value.data);
        else {
          setMembersError(
            membersResponse.reason instanceof Error
              ? membersResponse.reason.message
              : t('leagueDetail.members.error'),
          );
        }

        if (invitationsResponse.status === 'fulfilled') {
          setInvitations(invitationsResponse.value.data);
        } else {
          setInvitationsError(
            invitationsResponse.reason instanceof Error
              ? invitationsResponse.reason.message
              : t('leagueDetail.invitations.error'),
          );
        }

        if (fantasyTeamsResponse.status === 'fulfilled') {
          setFantasyTeams(fantasyTeamsResponse.value.data);
        } else {
          setFantasyTeamsError(
            fantasyTeamsResponse.reason instanceof Error
              ? fantasyTeamsResponse.reason.message
              : t('fantasyTeams.errors.list'),
          );
        }
      } finally {
        if (isMounted) setIsLoading(false);
      }
    }

    void loadLeagueDetail();
    return () => {
      isMounted = false;
    };
  }, [language, leagueId]);

  async function handleCreateInvitation(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!leagueId) return;
    try {
      setIsCreatingInvitation(true);
      setInvitationsError(null);
      await leaguesApi.createInvitation(leagueId, {
        max_uses: maxUses ? Number(maxUses) : null,
        expires_at: expiresAt ? new Date(expiresAt).toISOString() : null,
      });
      setMaxUses('');
      setExpiresAt('');
      await loadInvitations(leagueId);
    } catch (err) {
      setInvitationsError(
        err instanceof Error ? err.message : t('leagueDetail.invitations.createError'),
      );
    } finally {
      setIsCreatingInvitation(false);
    }
  }

  async function handleDeleteInvitation(invitationId: number) {
    if (!leagueId) return;
    try {
      setDeletingId(invitationId);
      setInvitationsError(null);
      await leaguesApi.deleteInvitation(leagueId, invitationId);
      setInvitations((current) => current.filter((invitation) => invitation.id !== invitationId));
    } catch (err) {
      setInvitationsError(
        err instanceof Error ? err.message : t('leagueDetail.invitations.deleteError'),
      );
    } finally {
      setDeletingId(null);
    }
  }

  async function handleCreateFantasyTeam(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!leagueId) return;

    const trimmedName = teamName.trim();
    try {
      setIsCreatingTeam(true);
      setCreateTeamError(null);
      setCreateTeamErrorDetails([]);
      const response = await leaguesApi.createFantasyTeam(leagueId, { name: trimmedName });
      setTeamName('');
      setFantasyTeams((current) => {
        const withoutCreatedTeam = current.filter((team) => team.id !== response.data.id);
        return [...withoutCreatedTeam, response.data].sort((first, second) =>
          first.name.localeCompare(second.name),
        );
      });
    } catch (err) {
      setCreateTeamError(err instanceof Error ? err.message : t('fantasyTeams.errors.create'));
      setCreateTeamErrorDetails(validationDetails(err));
      if (err instanceof ApiError && err.status === 409) void loadFantasyTeams(leagueId);
    } finally {
      setIsCreatingTeam(false);
    }
  }

  if (isLoading) return <LoadingState message={t('leagueDetail.loading')} />;

  return (
    <section className="space-y-6">
      <Link className="text-sm font-semibold text-emerald-300 hover:text-emerald-200" to="/leagues">
        {t('leagueDetail.backToLeagues')}
      </Link>

      {detailError ? <ErrorPanel message={detailError} title={t('leagueDetail.errorTitle')} /> : null}

      {league ? (
        <div className="space-y-6">
          <header className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
              {t('leagueDetail.eyebrow')}
            </p>
            <h1 className="mt-2 text-4xl font-bold text-white">{league.name}</h1>
            <p className="mt-3 max-w-2xl text-slate-300">
              {league.description || t('leagues.noDescription')}
            </p>
            <dl className="mt-6 grid gap-3 text-sm text-slate-300 md:grid-cols-5">
              <div>
                <dt className="text-slate-500">{t('leagues.fields.competition')}</dt>
                <dd>{league.season.competition.name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('leagues.fields.season')}</dt>
                <dd>{league.season.name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('leagues.fields.type')}</dt>
                <dd>{league.type?.label ?? t('leagueDetail.notAvailable')}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('leagues.fields.status')}</dt>
                <dd>{league.status?.label ?? t('leagueDetail.notAvailable')}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('leagueDetail.fields.maxParticipants')}</dt>
                <dd>{league.max_participants ?? t('leagueDetail.unlimited')}</dd>
              </div>
            </dl>
          </header>

          <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h2 className="text-2xl font-semibold text-white">{t('fantasyTeams.list.title')}</h2>
                <p className="mt-1 text-sm text-slate-300">{t('fantasyTeams.list.description')}</p>
              </div>
              {ownedFantasyTeam ? (
                <span className="rounded-full border border-emerald-400/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-200">
                  {t('fantasyTeams.list.youOwnTeam')}
                </span>
              ) : null}
            </div>

            {fantasyTeamsError ? (
              <div className="mt-4">
                <ErrorPanel message={fantasyTeamsError} title={t('fantasyTeams.errors.listTitle')} />
              </div>
            ) : null}

            {!fantasyTeamsError && fantasyTeams.length === 0 ? (
              <div className="mt-4">
                <EmptyPanel
                  message={t('fantasyTeams.list.emptyDescription')}
                  title={t('fantasyTeams.list.emptyTitle')}
                />
              </div>
            ) : null}

            {!fantasyTeamsError && fantasyTeams.length > 0 ? (
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                {fantasyTeams.map((team) => (
                  <Link
                    className="rounded-xl border border-slate-800 bg-slate-950/60 p-4 transition hover:border-emerald-400/40"
                    key={team.id}
                    to={`/leagues/${league.id}/fantasy-teams/${team.id}`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <h3 className="font-semibold text-white">{team.name}</h3>
                        <p className="mt-1 text-sm text-slate-400">
                          {t('fantasyTeams.list.owner', { name: team.owner.name })}
                        </p>
                      </div>
                      {team.is_owned_by_current_user ? (
                        <span className="rounded-full border border-emerald-400/30 px-2 py-1 text-xs font-semibold text-emerald-200">
                          {t('fantasyTeams.list.ownedByYou')}
                        </span>
                      ) : null}
                    </div>
                  </Link>
                ))}
              </div>
            ) : null}

            {!ownedFantasyTeam ? (
              <form className="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4" onSubmit={handleCreateFantasyTeam}>
                <h3 className="text-lg font-semibold text-white">{t('fantasyTeams.create.title')}</h3>
                <p className="mt-1 text-sm text-slate-300">{t('fantasyTeams.create.description')}</p>
                {createTeamError ? (
                  <div className="mt-4">
                    <ErrorPanel
                      details={createTeamErrorDetails}
                      message={createTeamError}
                      title={t('fantasyTeams.errors.createTitle')}
                    />
                  </div>
                ) : null}
                <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
                  <label className="text-sm text-slate-300">
                    {t('fantasyTeams.create.name')}
                    <input
                      className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
                      maxLength={100}
                      onChange={(event) => setTeamName(event.target.value)}
                      placeholder={t('fantasyTeams.create.namePlaceholder')}
                      type="text"
                      value={teamName}
                    />
                  </label>
                  <button
                    className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
                    disabled={isCreatingTeam}
                    type="submit"
                  >
                    {isCreatingTeam ? t('fantasyTeams.create.submitting') : t('fantasyTeams.create.submit')}
                  </button>
                </div>
              </form>
            ) : null}
          </section>

          <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h2 className="text-2xl font-semibold text-white">{t('leagueDetail.members.title')}</h2>
            {membersError ? (
              <div className="mt-4">
                <ErrorPanel message={membersError} title={t('leagueDetail.members.errorTitle')} />
              </div>
            ) : null}
            {!membersError && members.length === 0 ? (
              <div className="mt-4">
                <EmptyPanel
                  message={t('leagueDetail.members.emptyDescription')}
                  title={t('leagueDetail.members.emptyTitle')}
                />
              </div>
            ) : null}
            {!membersError && members.length > 0 ? (
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                {members.map((member) => (
                  <div className="rounded-xl border border-slate-800 bg-slate-950/60 p-4" key={member.id}>
                    <p className="font-semibold text-white">{member.name}</p>
                    <p className="mt-1 text-sm text-slate-400">{member.role.label}</p>
                  </div>
                ))}
              </div>
            ) : null}
          </section>

          <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h2 className="text-2xl font-semibold text-white">{t('leagueDetail.invitations.title')}</h2>
            <form className="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]" onSubmit={handleCreateInvitation}>
              <label className="text-sm text-slate-300">
                {t('leagueDetail.invitations.maxUses')}
                <input
                  className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
                  max="100"
                  min="1"
                  onChange={(event) => setMaxUses(event.target.value)}
                  placeholder={t('leagueDetail.invitations.unlimitedUses')}
                  type="number"
                  value={maxUses}
                />
              </label>
              <label className="text-sm text-slate-300">
                {t('leagueDetail.invitations.expiresAt')}
                <input
                  className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
                  onChange={(event) => setExpiresAt(event.target.value)}
                  type="datetime-local"
                  value={expiresAt}
                />
              </label>
              <button
                className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
                disabled={isCreatingInvitation}
                type="submit"
              >
                {isCreatingInvitation
                  ? t('leagueDetail.invitations.creating')
                  : t('leagueDetail.invitations.create')}
              </button>
            </form>
            {invitationsError ? (
              <div className="mt-4">
                <ErrorPanel message={invitationsError} title={t('leagueDetail.invitations.errorTitle')} />
              </div>
            ) : null}
            {!invitationsError && invitations.length === 0 ? (
              <div className="mt-4">
                <EmptyPanel
                  message={t('leagueDetail.invitations.emptyDescription')}
                  title={t('leagueDetail.invitations.emptyTitle')}
                />
              </div>
            ) : null}
            {!invitationsError && invitations.length > 0 ? (
              <div className="mt-4 grid gap-3">
                {invitations.map((invitation) => (
                  <div className="rounded-xl border border-slate-800 bg-slate-950/60 p-4" key={invitation.id}>
                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                      <div>
                        <p className="font-mono text-lg font-semibold text-white">{invitation.code}</p>
                        <p className="mt-1 text-sm text-slate-400">
                          {t('leagueDetail.invitations.status')}: {invitation.status}
                        </p>
                      </div>
                      <button
                        className="rounded-lg border border-red-400/40 px-3 py-2 text-sm font-semibold text-red-100 disabled:opacity-60"
                        disabled={deletingId === invitation.id}
                        onClick={() => void handleDeleteInvitation(invitation.id)}
                        type="button"
                      >
                        {deletingId === invitation.id
                          ? t('leagueDetail.invitations.deleting')
                          : t('leagueDetail.invitations.delete')}
                      </button>
                    </div>
                    <dl className="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-4">
                      <div>
                        <dt className="text-slate-500">{t('leagueDetail.invitations.used')}</dt>
                        <dd>
                          {invitation.used_count} / {invitation.max_uses ?? t('leagueDetail.unlimited')}
                        </dd>
                      </div>
                      <div>
                        <dt className="text-slate-500">{t('leagueDetail.invitations.remaining')}</dt>
                        <dd>{invitation.remaining_uses ?? t('leagueDetail.unlimited')}</dd>
                      </div>
                      <div>
                        <dt className="text-slate-500">{t('leagueDetail.invitations.expires')}</dt>
                        <dd>{formatDate(invitation.expires_at, t('leagueDetail.never'), language)}</dd>
                      </div>
                      <div>
                        <dt className="text-slate-500">{t('leagueDetail.invitations.createdBy')}</dt>
                        <dd>{invitation.creator?.name ?? t('leagueDetail.notAvailable')}</dd>
                      </div>
                    </dl>
                  </div>
                ))}
              </div>
            ) : null}
          </section>
        </div>
      ) : null}
    </section>
  );
}