import { useEffect, useState, type SubmitEvent } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ApiError } from '../../api/client';
import { leaguesApi } from '../../api/leagues';
import { leagueKeys, leagueMutationKeys } from '../../api/queryKeys';
import { useTranslation } from '../../i18n';
import type { Market } from '../../types/league';
import { apiTimestampToLocalInput, localInputToApiTimestamp } from '../../utils/dateTimeLocal';
import { ContentErrorPanel } from '../feedback/ContentErrorPanel';

export function MarketSettingsEditor({ leagueId, market }: { leagueId: string; market: Market }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [enabled, setEnabled] = useState(market.enabled);
  const [opensAt, setOpensAt] = useState(() => apiTimestampToLocalInput(market.opens_at));
  const [closesAt, setClosesAt] = useState(() => apiTimestampToLocalInput(market.closes_at));
  const [cashEnabled, setCashEnabled] = useState(market.cash_adjustment_enabled);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    setEnabled(market.enabled);
    setOpensAt(apiTimestampToLocalInput(market.opens_at));
    setClosesAt(apiTimestampToLocalInput(market.closes_at));
    setCashEnabled(market.cash_adjustment_enabled);
  }, [market]);

  const mutation = useMutation({
    mutationKey: leagueMutationKeys.settings(leagueId),
    mutationFn: leaguesApi.updateSettings.bind(null, leagueId),
    onSuccess: async () => {
      setSuccess(true);
      setError(null);
      setFieldErrors({});
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: leagueKeys.market(leagueId) }),
        queryClient.invalidateQueries({ queryKey: leagueKeys.settings(leagueId) }),
        queryClient.invalidateQueries({ queryKey: leagueKeys.detail(leagueId) }),
      ]);
    },
    onError: (updateError) => {
      setSuccess(false);
      if (updateError instanceof ApiError) {
        setFieldErrors(updateError.status === 422 ? (updateError.errors ?? {}) : {});
        if (updateError.status === 403) setError(t('common.errors.forbidden'));
        else if (updateError.status === 409) setError(t('leagueSettings.errors.conflict'));
        else if (updateError.status === 422) setError(t('common.errors.validation'));
        else setError(t('market.settings.updateError'));
      } else setError(t('market.settings.updateError'));
    },
  });

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setSuccess(false);
    const opens = localInputToApiTimestamp(opensAt);
    const closes = localInputToApiTimestamp(closesAt);
    if (enabled && (!opens || !closes || new Date(closes) <= new Date(opens))) {
      setError(t('market.settings.invalidWindow'));
      return;
    }
    setError(null);
    setFieldErrors({});
    mutation.mutate({
      trade_market_enabled: enabled,
      ...(opens ? { trade_market_opens_at: opens } : {}),
      ...(closes ? { trade_market_closes_at: closes } : {}),
      trade_cash_adjustment_enabled: cashEnabled,
    });
  }

  const fieldError = (key: string) => fieldErrors[key]?.[0];
  return (
    <section className="rounded-xl border border-theme-border bg-theme-surface p-5">
      <h2 className="text-xl font-bold text-theme-text">{t('market.settings.title')}</h2>
      <p className="mt-1 text-sm text-theme-muted">{t('market.settings.description')}</p>
      {error ? (
        <div className="mt-4">
          <ContentErrorPanel title={t('market.settings.errorTitle')} message={error} />
        </div>
      ) : null}
      {success ? (
        <p
          role="status"
          className="mt-4 rounded-lg border border-theme-primary/30 bg-emerald-950/30 p-3 text-emerald-100"
        >
          {t('market.settings.success')}
        </p>
      ) : null}
      <form className="mt-5 grid gap-4 sm:grid-cols-2" onSubmit={submit} noValidate>
        <label className="flex items-center gap-3">
          <input
            type="checkbox"
            checked={enabled}
            onChange={(event) => setEnabled(event.target.checked)}
          />
          {t('market.settings.enabled')}
        </label>
        <label className="flex items-center gap-3">
          <input
            type="checkbox"
            checked={cashEnabled}
            onChange={(event) => setCashEnabled(event.target.checked)}
          />
          {t('market.settings.cashEnabled')}
        </label>
        <label className="grid gap-1 text-sm">
          {t('market.opens')}
          <input
            className="rounded-lg border border-theme-border bg-theme-background p-2"
            type="datetime-local"
            value={opensAt}
            onChange={(event) => setOpensAt(event.target.value)}
          />
          {fieldError('trade_market_opens_at') ? (
            <span className="text-rose-400">{fieldError('trade_market_opens_at')}</span>
          ) : null}
        </label>
        <label className="grid gap-1 text-sm">
          {t('market.closes')}
          <input
            className="rounded-lg border border-theme-border bg-theme-background p-2"
            type="datetime-local"
            value={closesAt}
            onChange={(event) => setClosesAt(event.target.value)}
          />
          {fieldError('trade_market_closes_at') ? (
            <span className="text-rose-400">{fieldError('trade_market_closes_at')}</span>
          ) : null}
        </label>
        <button
          className="rounded-lg bg-theme-primary px-4 py-2 font-semibold text-theme-primary-foreground disabled:opacity-60 sm:col-span-2"
          disabled={mutation.isPending}
          type="submit"
        >
          {mutation.isPending ? t('leagueSettings.saving') : t('leagueSettings.save')}
        </button>
      </form>
    </section>
  );
}
