import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { leaguesApi } from '../../api/leagues';
import { leagueKeys } from '../../api/queryKeys';
import type { TradeProposal } from '../../types/league';
import { ContentErrorPanel } from '../feedback/ContentErrorPanel';
import { useTranslation } from '../../i18n';

export function TradeProposalPanel({ leagueId }: { leagueId: string }) {
  const { t } = useTranslation();
  const client = useQueryClient();
  const trades = useQuery({
    queryKey: leagueKeys.marketTrades(leagueId),
    queryFn: () => leaguesApi.marketTrades(leagueId),
  });
  const transition = useMutation({
    mutationFn: ({
      trade,
      action,
    }: {
      trade: TradeProposal;
      action: 'accept' | 'reject' | 'cancel';
    }) => leaguesApi.transitionTrade(leagueId, trade.id, action),
    onSuccess: async () => {
      await Promise.all([
        client.invalidateQueries({ queryKey: leagueKeys.detail(leagueId) }),
        client.invalidateQueries({ queryKey: leagueKeys.marketTrades(leagueId) }),
        client.invalidateQueries({ queryKey: leagueKeys.market(leagueId) }),
        client.invalidateQueries({ queryKey: leagueKeys.fantasyTeams(leagueId) }),
      ]);
    },
  });
  if (trades.error)
    return <ContentErrorPanel title={t('market.trades.title')} message={trades.error.message} />;
  const all = trades.data?.data ?? [];
  const sections = [
    ['incoming', all.filter((v) => v.capabilities.can_accept || v.capabilities.can_reject)],
    ['outgoing', all.filter((v) => v.capabilities.can_cancel)],
    ['history', all.filter((v) => v.status !== 'pending')],
  ] as const;
  return (
    <section className="space-y-4">
      <h2 className="text-xl font-bold">{t('market.trades.title')}</h2>
      {sections.map(([name, values]) => (
        <div key={name}>
          <h3 className="font-semibold">{t(`market.trades.${name}`)}</h3>
          <div className="space-y-2">
            {values.length ? (
              values.map((trade) => (
                <article key={trade.id} className="rounded-xl border border-slate-800 p-4">
                  <p>
                    {trade.proposing_fantasy_team.name}: {trade.offered_player.name} →{' '}
                    {trade.receiving_fantasy_team.name}: {trade.requested_player.name}
                  </p>
                  {trade.cash_from_fantasy_team ? (
                    <p>
                      {trade.cash_from_fantasy_team.name}: {trade.cash_amount}
                    </p>
                  ) : null}
                  <p className="text-sm text-slate-400">
                    {trade.status} · {new Date(trade.created_at).toLocaleDateString()}
                  </p>
                  <div className="flex gap-2">
                    {trade.capabilities.can_accept ? (
                      <button
                        onClick={() => {
                          if (window.confirm(t('market.trades.acceptConfirmation')))
                            transition.mutate({ trade, action: 'accept' });
                        }}
                      >
                        {t('market.trades.accept')}
                      </button>
                    ) : null}
                    {trade.capabilities.can_reject ? (
                      <button onClick={() => transition.mutate({ trade, action: 'reject' })}>
                        {t('market.trades.reject')}
                      </button>
                    ) : null}
                    {trade.capabilities.can_cancel ? (
                      <button onClick={() => transition.mutate({ trade, action: 'cancel' })}>
                        {t('common.cancel')}
                      </button>
                    ) : null}
                  </div>
                </article>
              ))
            ) : (
              <p className="text-slate-400">{t('market.trades.empty')}</p>
            )}
          </div>
        </div>
      ))}
    </section>
  );
}
