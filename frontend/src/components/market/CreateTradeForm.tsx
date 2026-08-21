import { useState, type SubmitEvent } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
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
}: {
  leagueId: string;
  market: Market;
  target: MarketPlayer;
  roster: RosterPlayer[];
  ownTeam: FantasyTeam;
  close: () => void;
}) {
  const { t } = useTranslation();
  const client = useQueryClient();
  const [offered, setOffered] = useState('');
  const [payer, setPayer] = useState('');
  const [cash, setCash] = useState('0');
  const mutation = useMutation({
    mutationFn: () =>
      leaguesApi.createTrade(leagueId, {
        receiving_fantasy_team_id: target.fantasy_team!.id,
        offered_fantasy_team_player_id: Number(offered),
        requested_fantasy_team_player_id: target.assignment_id!,
        cash_from_fantasy_team_id: payer ? Number(payer) : null,
        cash_amount: Number(cash),
      }),
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: leagueKeys.marketTrades(leagueId) });
      close();
    },
  });
  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    mutation.mutate();
  }
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/70">
      <form onSubmit={submit} className="w-full max-w-lg space-y-4 rounded-xl bg-slate-900 p-6">
        <h2 className="text-xl font-bold">
          {t('market.trades.proposeFor', { name: target.name })}
        </h2>
        <select
          required
          value={offered}
          onChange={(e) => setOffered(e.target.value)}
          className="w-full rounded border border-slate-700 bg-slate-950 p-2"
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
              className="w-full rounded border border-slate-700 bg-slate-950 p-2"
            >
              <option value="">{t('market.trades.noCash')}</option>
              <option value={ownTeam.id}>{ownTeam.name}</option>
              <option value={target.fantasy_team!.id}>{target.fantasy_team!.name}</option>
            </select>
            <input
              type="number"
              min="0"
              step="0.01"
              value={cash}
              onChange={(e) => setCash(e.target.value)}
              className="w-full rounded border border-slate-700 bg-slate-950 p-2"
            />
          </>
        ) : null}
        {mutation.error ? <p className="text-red-400">{mutation.error.message}</p> : null}
        <div className="flex gap-2">
          <button type="submit">{t('market.trades.propose')}</button>
          <button type="button" onClick={close}>
            {t('common.cancel')}
          </button>
        </div>
      </form>
    </div>
  );
}
