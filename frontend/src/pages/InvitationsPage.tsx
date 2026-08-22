import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ContentEmptyPanel } from '../components/feedback/ContentEmptyPanel';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LoadingState } from '../components/LoadingState';
import {
  useAcceptInvitation,
  useInvitationInbox,
  useRejectInvitation,
} from '../hooks/useInvitations';
import { useTranslation } from '../i18n';

export function InvitationsPage() {
  const { language, t } = useTranslation();
  const inbox = useInvitationInbox();
  const accept = useAcceptInvitation();
  const reject = useRejectInvitation();
  const [message, setMessage] = useState('');

  if (inbox.isLoading) return <LoadingState message={t('invitations.loading')} />;
  if (inbox.isError)
    return (
      <ContentErrorPanel title={t('invitations.errorTitle')} message={t('invitations.error')} />
    );
  const invitations = inbox.data?.data ?? [];

  return (
    <section className="space-y-5">
      <div>
        <p className="text-sm font-semibold uppercase text-emerald-300">
          {t('invitations.eyebrow')}
        </p>
        <h1 className="text-3xl font-bold text-white">{t('invitations.title')}</h1>
      </div>
      {message ? (
        <p className="rounded-lg border border-emerald-500/30 p-3 text-emerald-200" role="status">
          {message}
        </p>
      ) : null}
      {invitations.length === 0 ? (
        <ContentEmptyPanel title={t('invitations.emptyTitle')} message={t('invitations.empty')} />
      ) : null}
      <div className="grid gap-4">
        {invitations.map((invitation) => (
          <article
            className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"
            key={invitation.id}
          >
            <h2 className="text-xl font-semibold text-white">{invitation.league?.name}</h2>
            <dl className="my-4 grid gap-2 text-sm text-slate-300 sm:grid-cols-3">
              <div>
                <dt className="text-slate-500">{t('invitations.invitedRole')}</dt>
                <dd>{t(`leagues.roles.${invitation.role.key}`)}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('invitations.inviter')}</dt>
                <dd>{invitation.creator?.name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('invitations.expiration')}</dt>
                <dd>
                  {invitation.expires_at
                    ? new Intl.DateTimeFormat(language, { dateStyle: 'medium' }).format(
                        new Date(invitation.expires_at),
                      )
                    : t('leagueDetail.never')}
                </dd>
              </div>
            </dl>
            <div className="flex gap-3">
              <button
                className="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-50"
                disabled={accept.isPending || reject.isPending}
                onClick={() =>
                  accept.mutate(invitation.id, {
                    onSuccess: () => setMessage(t('invitations.accepted')),
                  })
                }
              >
                {t('invitations.accept')}
              </button>
              <button
                className="rounded-lg border border-red-400/40 px-4 py-2 font-semibold text-red-100 disabled:opacity-50"
                disabled={accept.isPending || reject.isPending}
                onClick={() => {
                  if (window.confirm(t('invitations.rejectConfirmation')))
                    reject.mutate(invitation.id, {
                      onSuccess: () => setMessage(t('invitations.rejected')),
                    });
                }}
              >
                {t('invitations.reject')}
              </button>
              {invitation.league ? (
                <Link
                  className="px-4 py-2 text-emerald-300"
                  to={`/leagues/${invitation.league.id}`}
                >
                  {t('invitations.viewLeague')}
                </Link>
              ) : null}
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}