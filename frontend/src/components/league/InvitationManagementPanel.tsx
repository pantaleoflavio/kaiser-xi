import { FormEvent, useState } from 'react';
import { leaguesApi } from '../../api/leagues';
import { useTranslation } from '../../i18n';
import type { LeagueInvitation } from '../../types/league';
import { ContentEmptyPanel } from '../feedback/ContentEmptyPanel';
import { ContentErrorPanel } from '../feedback/ContentErrorPanel';

function formatDate(value: string | null, fallback: string, locale: string) {
  if (!value) return fallback;
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(value),
  );
}
export function InvitationManagementPanel({
  leagueId,
  initialInvitations,
  initialError,
}: {
  leagueId: number;
  initialInvitations: LeagueInvitation[];
  initialError: string | null;
}) {
  const { language, t } = useTranslation();
  const [invitations, setInvitations] = useState(initialInvitations);
  const [error, setError] = useState(initialError);
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<'participant' | 'co_commissioner'>('participant');
  const [expiresAt, setExpiresAt] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  async function reload() {
    try {
      setError(null);
      setInvitations((await leaguesApi.invitations(leagueId)).data);
    } catch (err) {
      setError(err instanceof Error ? err.message : t('leagueDetail.invitations.error'));
    }
  }
  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    try {
      setIsCreating(true);
      setError(null);
      await leaguesApi.createInvitation(leagueId, {
        email,
        role,
        expires_at: expiresAt ? new Date(expiresAt).toISOString() : null,
      });
      setEmail('');
      setExpiresAt('');
      await reload();
    } catch (err) {
      setError(err instanceof Error ? err.message : t('leagueDetail.invitations.createError'));
    } finally {
      setIsCreating(false);
    }
  }
  async function remove(id: number) {
    try {
      setDeletingId(id);
      setError(null);
      await leaguesApi.revokeInvitation(leagueId, id);
      setInvitations((current) => current.filter((item) => item.id !== id));
    } catch (err) {
      setError(err instanceof Error ? err.message : t('leagueDetail.invitations.deleteError'));
    } finally {
      setDeletingId(null);
    }
  }
  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 className="text-2xl font-semibold text-white">{t('leagueDetail.invitations.title')}</h2>
      <p className="mt-1 text-sm text-slate-300">
        {t('leagueDetail.invitations.emptyDescription')}
      </p>
      <form className="mt-5 grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]" onSubmit={create}>
        <label className="text-sm text-slate-300">
          {t('leagueDetail.invitations.email')}
          <input
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            onChange={(e) => setEmail(e.target.value)}
            required
            type="email"
            value={email}
          />
        </label>
        <label className="text-sm text-slate-300">
          {t('leagueDetail.invitations.invitedRole')}
          <select
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            value={role}
            onChange={(event) => setRole(event.target.value as 'participant' | 'co_commissioner')}
          >
            <option value="participant">{t('leagues.roles.participant')}</option>
            <option value="co_commissioner">{t('leagues.roles.co_commissioner')}</option>
          </select>
        </label>
        <label className="text-sm text-slate-300">
          {t('leagueDetail.invitations.expiresAt')}
          <input
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            onChange={(e) => setExpiresAt(e.target.value)}
            type="datetime-local"
            value={expiresAt}
          />
        </label>
        <button
          className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          disabled={isCreating}
          type="submit"
        >
          {isCreating
            ? t('leagueDetail.invitations.creating')
            : t('leagueDetail.invitations.create')}
        </button>
      </form>
      {error ? (
        <div className="mt-4">
          <ContentErrorPanel message={error} title={t('leagueDetail.invitations.errorTitle')} />
        </div>
      ) : null}
      {!error && invitations.length === 0 ? (
        <div className="mt-4">
          <ContentEmptyPanel
            message={t('leagueDetail.invitations.emptyDescription')}
            title={t('leagueDetail.invitations.emptyTitle')}
          />
        </div>
      ) : null}
      {!error && invitations.length > 0 ? (
        <div className="mt-4 grid gap-3">
          {invitations.map((invitation) => (
            <div
              className="rounded-xl border border-slate-800 bg-slate-950/60 p-4"
              key={invitation.id}
            >
              <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                  <p className="text-lg font-semibold text-white">{invitation.recipient?.name}</p>
                  <p className="mt-1 text-sm text-slate-400">
                    {t('leagueDetail.invitations.status')}: {invitation.status}
                  </p>
                </div>
                <button
                  className="rounded-lg border border-red-400/40 px-3 py-2 text-sm font-semibold text-red-100 disabled:opacity-60"
                  disabled={deletingId === invitation.id}
                  onClick={() => void remove(invitation.id)}
                  type="button"
                >
                  {deletingId === invitation.id
                    ? t('leagueDetail.invitations.deleting')
                    : t('leagueDetail.invitations.delete')}
                </button>
              </div>
              <dl className="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-3">
                <div>
                  <dt className="text-slate-500">{t('leagueDetail.invitations.invitedRole')}</dt>
                  <dd>{t(`leagues.roles.${invitation.role.key}`)}</dd>
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
  );
}