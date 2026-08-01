import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';
import { useAuth } from '../auth/useAuth';
import { leagueMemberKeys, useLeagueMembers } from '../hooks/useLeagueMembers';
import { useTranslation } from '../i18n';
import type { League, LeagueMember, ManageableLeagueRole } from '../types/league';

type PendingAction =
  | { kind: 'remove'; member: LeagueMember }
  | { kind: 'role'; member: LeagueMember; role: ManageableLeagueRole }
  | null;

function memberError(error: unknown, action: 'remove' | 'role', t: (key: string) => string) {
  const prefix = `leagueDetail.members.management.errors.${action}`;
  if (error instanceof ApiError) {
    if (error.status === 403) return t(`${prefix}.forbidden`);
    if (error.status === 404) return t(`${prefix}.notFound`);
    if (error.status === 409) return t(`${prefix}.conflict`);
  }
  return t(`${prefix}.unexpected`);
}

function roleLabel(member: LeagueMember, t: (key: string) => string) {
  const knownRoles = ['commissioner', 'co_commissioner', 'participant'];
  return knownRoles.includes(member.role.key)
    ? t(`leagueDetail.members.management.roles.${member.role.key}`)
    : member.role.label;
}

export function LeagueMemberList({ league }: { league: League }) {
  const { user } = useAuth();
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const membersQuery = useLeagueMembers(String(league.id));
  const [pending, setPending] = useState<PendingAction>(null);
  const [feedback, setFeedback] = useState<{ type: 'error' | 'success'; message: string } | null>(
    null,
  );

  const refreshMembers = async () => {
    await queryClient.invalidateQueries({ queryKey: leagueMemberKeys.league(league.id) });
  };

  const removeMutation = useMutation({
    mutationFn: (member: LeagueMember) => leaguesApi.removeMember(league.id, member.id),
    onSuccess: async (_, member) => {
      queryClient.setQueryData(leagueMemberKeys.league(league.id), (current: typeof membersQuery.data) =>
        current ? { ...current, data: current.data.filter((item) => item.id !== member.id) } : current,
      );
      setPending(null);
      setFeedback({
        type: 'success',
        message: t('leagueDetail.members.management.remove.success', { name: member.name }),
      });
      await refreshMembers();
    },
    onError: (error) => {
      setPending(null);
      setFeedback({ type: 'error', message: memberError(error, 'remove', t) });
    },
  });

  const roleMutation = useMutation({
    mutationFn: ({ member, role }: { member: LeagueMember; role: ManageableLeagueRole }) =>
      leaguesApi.updateMemberRole(league.id, member.id, role),
    onSuccess: async (response, variables) => {
      queryClient.setQueryData(leagueMemberKeys.league(league.id), (current: typeof membersQuery.data) =>
        current
          ? {
              ...current,
              data: current.data.map((item) =>
                item.id === response.data.id ? response.data : item,
              ),
            }
          : current,
      );
      setPending(null);
      setFeedback({
        type: 'success',
        message: t(`leagueDetail.members.management.role.${variables.role}.success`, {
          name: variables.member.name,
        }),
      });
      await refreshMembers();
    },
    onError: (error) => {
      setPending(null);
      setFeedback({ type: 'error', message: memberError(error, 'role', t) });
    },
  });

  const members = membersQuery.data?.data ?? [];
  const isSubmitting = removeMutation.isPending || roleMutation.isPending;
  const canRemove = (member: LeagueMember) => {
    if (member.id === user?.id || member.role.key === 'commissioner') return false;
    if (league.my_role === 'commissioner') return member.role.key !== 'commissioner';
    return league.my_role === 'co_commissioner' && member.role.key === 'participant';
  };
  const canChangeRole = (member: LeagueMember) =>
    league.my_role === 'commissioner' &&
    member.id !== user?.id &&
    (member.role.key === 'participant' || member.role.key === 'co_commissioner');

  if (membersQuery.isLoading) {
    return <p className="mt-4 text-sm text-slate-300">{t('leagueDetail.members.loading')}</p>;
  }

  if (membersQuery.isError) {
    return (
      <div className="mt-4 rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100">
        <p className="font-semibold">{t('leagueDetail.members.errorTitle')}</p>
        <p className="mt-1 text-red-100/80">{t('leagueDetail.members.error')}</p>
      </div>
    );
  }

  return (
    <>
      {feedback ? (
        <p
          className={`mt-4 rounded-lg border p-3 text-sm ${
            feedback.type === 'success'
              ? 'border-emerald-500/30 bg-emerald-950/40 text-emerald-100'
              : 'border-red-500/30 bg-red-950/40 text-red-100'
          }`}
          role={feedback.type === 'success' ? 'status' : 'alert'}
        >
          {feedback.message}
        </p>
      ) : null}

      {members.length === 0 ? (
        <div className="mt-4 rounded-xl border border-slate-800 bg-slate-950/60 p-5 text-center">
          <h3 className="font-semibold text-white">{t('leagueDetail.members.emptyTitle')}</h3>
          <p className="mt-2 text-sm text-slate-300">
            {t('leagueDetail.members.emptyDescription')}
          </p>
        </div>
      ) : (
        <div className="mt-4 grid gap-3 md:grid-cols-2">
          {members.map((member) => (
            <div className="rounded-xl border border-slate-800 bg-slate-950/60 p-4" key={member.id}>
              <p className="font-semibold text-white">{member.name}</p>
              <p className="mt-1 text-sm text-slate-400">{roleLabel(member, t)}</p>
              {canRemove(member) || canChangeRole(member) ? (
                <div className="mt-4 flex flex-wrap gap-2">
                  {canChangeRole(member) ? (
                    <button
                      className="rounded-lg border border-cyan-500/50 px-3 py-2 text-sm font-semibold text-cyan-100 hover:bg-cyan-950/60"
                      onClick={() => {
                        setFeedback(null);
                        setPending({
                          kind: 'role',
                          member,
                          role:
                            member.role.key === 'participant' ? 'co_commissioner' : 'participant',
                        });
                      }}
                      type="button"
                    >
                      {member.role.key === 'participant'
                        ? t('leagueDetail.members.management.promote')
                        : t('leagueDetail.members.management.revoke')}
                    </button>
                  ) : null}
                  {canRemove(member) ? (
                    <button
                      className="rounded-lg border border-red-500/50 px-3 py-2 text-sm font-semibold text-red-100 hover:bg-red-950/60"
                      onClick={() => {
                        setFeedback(null);
                        setPending({ kind: 'remove', member });
                      }}
                      type="button"
                    >
                      {t('leagueDetail.members.management.remove.action')}
                    </button>
                  ) : null}
                </div>
              ) : null}
            </div>
          ))}
        </div>
      )}

      {pending ? (
        <div
          aria-labelledby="member-action-title"
          aria-modal="true"
          className="fixed inset-0 z-50 grid place-items-center bg-slate-950/80 p-4"
          role="dialog"
        >
          <div className="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
            <h3 className="text-xl font-semibold text-white" id="member-action-title">
              {pending.kind === 'remove'
                ? t('leagueDetail.members.management.remove.title')
                : t(`leagueDetail.members.management.role.${pending.role}.title`)}
            </h3>
            <p className="mt-3 text-slate-200">
              {pending.kind === 'remove'
                ? t('leagueDetail.members.management.remove.confirmation', {
                    name: pending.member.name,
                    role: roleLabel(pending.member, t),
                    league: league.name,
                  })
                : t(`leagueDetail.members.management.role.${pending.role}.confirmation`, {
                    name: pending.member.name,
                    role: roleLabel(pending.member, t),
                    league: league.name,
                  })}
            </p>
            {pending.kind === 'remove' ? (
              <p className="mt-3 text-sm text-slate-400">
                {t('leagueDetail.members.management.remove.preservation')}
              </p>
            ) : null}
            <div className="mt-6 flex justify-end gap-3">
              <button
                className="rounded-lg border border-slate-600 px-4 py-2 font-semibold text-slate-200"
                disabled={isSubmitting}
                onClick={() => setPending(null)}
                type="button"
              >
                {t('common.cancel')}
              </button>
              <button
                className={`rounded-lg px-4 py-2 font-semibold text-white disabled:opacity-60 ${
                  pending.kind === 'remove' ? 'bg-red-600' : 'bg-cyan-700'
                }`}
                disabled={isSubmitting}
                onClick={() => {
                  if (pending.kind === 'remove') removeMutation.mutate(pending.member);
                  else roleMutation.mutate(pending);
                }}
                type="button"
              >
                {isSubmitting
                  ? t('leagueDetail.members.management.saving')
                  : pending.kind === 'remove'
                    ? t('leagueDetail.members.management.remove.confirm')
                    : t(`leagueDetail.members.management.role.${pending.role}.confirm`)}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}