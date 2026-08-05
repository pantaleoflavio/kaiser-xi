import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { ApiError } from '../../api/client';
import { leaguesApi } from '../../api/leagues';
import { leagueKeys, leagueMutationKeys } from '../../api/queryKeys';
import { useTranslation } from '../../i18n';
import type { League } from '../../types/league';

type Props = {
  league: League;
  onActivated: (league: League) => Promise<void>;
};

function activationError(error: unknown, league: League, t: (key: string) => string) {
  if (error instanceof ApiError) {
    if (error.status === 403) return t('leagueDetail.activation.forbidden');
    if (error.status === 409) {
      return league.status.key === 'active'
        ? t('leagueDetail.activation.alreadyActive')
        : t('leagueDetail.activation.invalidState');
    }
    if (error.status === 422) return t('leagueDetail.activation.incomplete');
  }
  return t('common.errors.unexpected');
}

export function LeagueActivationPanel({ league, onActivated }: Props) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [isConfirming, setIsConfirming] = useState(false);
  const [feedback, setFeedback] = useState<{
    type: 'error' | 'success';
    message: string;
    showSettings?: boolean;
  } | null>(null);
  const canActivate =
    league.my_role === 'commissioner' && ['draft', 'setup'].includes(league.status.key);

  const activation = useMutation({
    mutationKey: leagueMutationKeys.activate(league.id),
    mutationFn: () => leaguesApi.activateLeague(league.id),
    onSuccess: async (response) => {
      queryClient.setQueryData(leagueKeys.detail(league.id), response);
      setIsConfirming(false);
      setFeedback({ type: 'success', message: t('leagueDetail.activation.success') });
      await onActivated(response.data);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: leagueKeys.detail(league.id) }),
        queryClient.invalidateQueries({ queryKey: leagueKeys.settings(league.id) }),
        queryClient.invalidateQueries({ queryKey: leagueKeys.lists() }),
      ]);
    },
    onError: (error) => {
      setIsConfirming(false);
      setFeedback({
        type: 'error',
        message: activationError(error, league, t),
        showSettings: error instanceof ApiError && error.status === 422,
      });
    },
  });

  if (!canActivate && feedback?.type !== 'success') return null;

  return (
    <section
      aria-live="polite"
      className="rounded-2xl border border-amber-400/30 bg-amber-950/20 p-6"
    >
      {feedback ? (
        <div
          className={`rounded-xl border p-4 text-sm ${
            feedback.type === 'success'
              ? 'border-emerald-400/30 bg-emerald-950/30 text-emerald-100'
              : 'border-red-500/30 bg-red-950/40 text-red-100'
          }`}
          role={feedback.type === 'error' ? 'alert' : 'status'}
        >
          <p>{feedback.message}</p>
          {feedback.showSettings ? (
            <Link className="mt-2 inline-block font-semibold underline" to="#league-settings">
              {t('leagueDetail.activation.openSettings')}
            </Link>
          ) : null}
        </div>
      ) : null}

      {canActivate ? (
        <button
          className="mt-4 rounded-lg bg-amber-400 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          disabled={activation.isPending}
          onClick={() => {
            setFeedback(null);
            setIsConfirming(true);
          }}
          type="button"
        >
          {t('leagueDetail.activation.action')}
        </button>
      ) : null}

      {isConfirming ? (
        <div
          aria-describedby="activation-description"
          aria-labelledby="activation-title"
          aria-modal="true"
          className="fixed inset-0 z-50 grid place-items-center bg-slate-950/80 p-4"
          role="dialog"
        >
          <div className="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
            <h2 className="text-xl font-semibold text-white" id="activation-title">
              {t('leagueDetail.activation.confirmTitle')}
            </h2>
            <p className="mt-3 text-slate-200" id="activation-description">
              {t('leagueDetail.activation.confirmDescription')}
            </p>
            <div className="mt-6 flex justify-end gap-3">
              <button
                className="rounded-lg border border-slate-600 px-4 py-2 font-semibold text-slate-200"
                disabled={activation.isPending}
                onClick={() => setIsConfirming(false)}
                type="button"
              >
                {t('common.cancel')}
              </button>
              <button
                autoFocus
                className="rounded-lg bg-amber-400 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
                disabled={activation.isPending}
                onClick={() => activation.mutate()}
                type="button"
              >
                {activation.isPending
                  ? t('leagueDetail.activation.pending')
                  : t('leagueDetail.activation.action')}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  );
}