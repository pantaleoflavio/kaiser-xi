import { useState, type SubmitEvent } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ApiError } from '../../api/client';
import { leaguesApi } from '../../api/leagues';
import { leagueKeys } from '../../api/queryKeys';
import type { FantasyTeam, Market, MarketPlayer, RosterPlayer } from '../../types/league';
import { useTranslation } from '../../i18n';

export function CreateTradeForm({
  leagueId,
  market,
  target,
  roster,
  ownTeam,
  close,
  sent,
}: {
  leagueId: string;
  market: Market;
  target: MarketPlayer;
  roster: RosterPlayer[];
  ownTeam: FantasyTeam;
  close: () => void;
  sent: () => void;
}) {
  const { t } = useTranslation();
  const client = useQueryClient();
  const [offered, setOffered] = useState('');
  const [payer, setPayer] = useState('');
  const [cash, setCash] = useState('0');
  const [error, setError] = useState<string | null>(null);
  const mutation = useMutation({
    mutationFn: () =>
      leaguesApi.createTrade(leagueId, {
        receiving_fantasy_team_id: target.fantasy_team!.id,
        offered_fantasy_team_player_id: Number(offered),
        requested_fantasy_team_player_id: target.assignment_id!,
        cash_from_fantasy_team_id: payer ? Number(payer) : null,
        cash_amount: Number(cash),
      }),
    onMutate: () => setError(null),
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: leagueKeys.marketTrades(leagueId) });
      sent();
    },
    onError: (submissionError) => {
      if (
        submissionError instanceof ApiError &&
        submissionError.code === 'duplicate_trade_proposal'
      ) {
        setError(t('market.trades.duplicate'));
      } else if (submissionError instanceof ApiError && submissionError.status === 422) {
        setError(t('market.trades.invalidCash'));
      } else setError(submissionError.message);
    },
  });
  const cashAmount = Number(cash);
  const cashIsInteger = cash !== '' && Number.isInteger(cashAmount) && cashAmount >= 0;
  const payerIsValid = cashAmount === 0 ? payer === '' : payer !== '';
  const formIsValid = offered !== '' && cashIsInteger && payerIsValid;

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    if (mutation.isPending) return;
    if (!formIsValid) {
      setError(t('market.trades.invalidCash'));
      return;
    }
    mutation.mutate();
  }
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70">
      <form onSubmit={submit} className="w-full max-w-lg space-y-4 rounded-xl bg-theme-surface p-6">
        <h2 className="text-xl font-bold">
          {t('market.trades.proposeFor', { name: target.name })}
        </h2>
        <fieldset className="space-y-4" disabled={mutation.isPending}>
          <select
            required
            value={offered}
            onChange={(e) => setOffered(e.target.value)}
            className="w-full rounded border border-theme-border bg-theme-background p-2"
          >
            <option value="">{t('market.trades.choosePlayer')}</option>
            {roster.map((v) => (
              <option key={v.id} value={v.id}>
                {v.player.name}
              </option>
            ))}
          </select>
          {market.cash_adjustment_enabled ? (
            <>
              <select
                value={payer}
                onChange={(e) => setPayer(e.target.value)}
                className="w-full rounded border border-theme-border bg-theme-background p-2"
              >
                <option value="">{t('market.trades.noCash')}</option>
                <option value={ownTeam.id}>{ownTeam.name}</option>
                <option value={target.fantasy_team!.id}>{target.fantasy_team!.name}</option>
              </select>
              <input
                type="number"
                min="0"
                step="1"
                value={cash}
                onChange={(e) => setCash(e.target.value)}
                className="w-full rounded border border-theme-border bg-theme-background p-2"
              />
            </>
          ) : null}
          {error ? (
            <p role="alert" className="text-red-400">
              {error}
            </p>
          ) : null}
          <div className="flex flex-wrap gap-2">
            <button
              className="rounded-lg bg-theme-primary px-4 py-2 text-sm font-semibold text-theme-primary-foreground transition hover:bg-theme-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-focus focus-visible:ring-offset-2 focus-visible:ring-offset-theme-surface disabled:cursor-not-allowed disabled:opacity-50"
              disabled={!formIsValid || !market.can_trade}
              type="submit"
            >
              {mutation.isPending ? t('market.trades.sendingProposal') : t('market.trades.propose')}
            </button>
            <button
              className="rounded-lg border border-slate-600 bg-theme-muted-surface px-4 py-2 text-sm font-semibold text-theme-text transition hover:border-slate-500 hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2 focus-visible:ring-offset-theme-surface disabled:cursor-not-allowed disabled:opacity-50"
              type="button"
              onClick={close}
            >
              {t('common.cancel')}
            </button>
          </div>
        </fieldset>
      </form>
    </div>
  );
}
