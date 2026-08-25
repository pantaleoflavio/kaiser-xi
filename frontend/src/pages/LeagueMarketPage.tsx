import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { MarketStatus } from '../components/market/MarketStatus';
import { MarketDirectory } from '../components/market/MarketDirectory';
import { MarketSettingsEditor } from '../components/market/MarketSettingsEditor';
import { TradeProposalPanel } from '../components/market/TradeProposalPanel';
import { CreateTradeForm } from '../components/market/CreateTradeForm';
import type { MarketPlayer } from '../types/league';
import { useTranslation } from '../i18n';

export function LeagueMarketPage() {
  const { leagueId = '' } = useParams();
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const [state, setState] = useState('');
  const [page, setPage] = useState(1);
  const [target, setTarget] = useState<MarketPlayer | null>(null);
  const [tradeSent, setTradeSent] = useState(false);
  const market = useQuery({
    queryKey: leagueKeys.market(leagueId),
    queryFn: () => leaguesApi.market(leagueId),
  });
  const filters = { search, role, assignment_state: state, page, per_page: 25 };
  const teams = useQuery({
    queryKey: leagueKeys.fantasyTeams(leagueId),
    queryFn: () => leaguesApi.fantasyTeams(leagueId),
  });
  const ownTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);
  const roster = useQuery({
    queryKey: [...leagueKeys.fantasyTeams(leagueId), ownTeam?.id, 'players'],
    queryFn: () => leaguesApi.rosterPlayers(leagueId, ownTeam!.id),
    enabled: Boolean(ownTeam),
  });
  const players = useQuery({
    queryKey: leagueKeys.marketPlayers(leagueId, filters),
    queryFn: () => leaguesApi.marketPlayers(leagueId, filters),
  });
  if (market.isLoading) return <LoadingState message={t('common.loading')} />;
  if (!market.data?.data)
    return (
      <ContentErrorPanel
        title={t('market.title')}
        message={market.error?.message ?? t('market.error')}
      />
    );
  return (
    <section className="space-y-6">
      <LeagueNavigation leagueId={leagueId} />
      <h1 className="text-3xl font-bold text-theme-text">{t('market.title')}</h1>
      {tradeSent ? (
        <p
          role="status"
          className="rounded-lg border border-theme-primary/30 bg-emerald-950/30 p-3 text-emerald-100"
        >
          {t('market.trades.sent')}
        </p>
      ) : null}
      <MarketStatus market={market.data.data} />
      {market.data.data.can_manage ? (
        <MarketSettingsEditor leagueId={leagueId} market={market.data.data} />
      ) : null}
      <section className="space-y-4">
        <h2 className="text-xl font-bold">{t('market.directory')}</h2>
        <div className="flex flex-wrap gap-3">
          <input
            className="rounded-lg border border-theme-border bg-theme-surface p-2"
            placeholder={t('market.search')}
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
          />
          <select
            className="rounded-lg border border-theme-border bg-theme-surface p-2"
            value={role}
            onChange={(e) => {
              setRole(e.target.value);
              setPage(1);
            }}
          >
            <option value="">{t('market.allRoles')}</option>
            <option value="goalkeeper">{t('formation.roles.goalkeeper')}</option>
            <option value="defender">{t('formation.roles.defender')}</option>
            <option value="midfielder">{t('formation.roles.midfielder')}</option>
            <option value="forward">{t('formation.roles.forward')}</option>
          </select>
          <select
            className="rounded-lg border border-theme-border bg-theme-surface p-2"
            value={state}
            onChange={(e) => {
              setState(e.target.value);
              setPage(1);
            }}
          >
            <option value="">{t('market.allAssignments')}</option>
            <option value="assigned">{t('market.assigned')}</option>
            <option value="unassigned">{t('market.unassigned')}</option>
          </select>
        </div>
        {players.isLoading ? (
          <LoadingState message={t('common.loading')} />
        ) : players.error ? (
          <ContentErrorPanel title={t('market.directory')} message={players.error.message} />
        ) : (
          <MarketDirectory
            players={players.data?.data ?? []}
            canTrade={market.data.data.can_trade}
            propose={(player) => {
              setTradeSent(false);
              setTarget(player);
            }}
          />
        )}
        <div className="flex justify-end gap-2">
          <button
            className="rounded-lg border border-slate-600 bg-theme-surface px-4 py-2 text-sm font-semibold text-theme-text transition hover:border-slate-500 hover:bg-theme-muted-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2 focus-visible:ring-offset-theme-background disabled:cursor-not-allowed disabled:border-theme-border disabled:text-theme-muted disabled:opacity-60"
            disabled={page === 1}
            onClick={() => setPage((p) => p - 1)}
            type="button"
          >
            {t('common.previous')}
          </button>
          <button
            className="rounded-lg border border-slate-600 bg-theme-surface px-4 py-2 text-sm font-semibold text-theme-text transition hover:border-slate-500 hover:bg-theme-muted-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2 focus-visible:ring-offset-theme-background disabled:cursor-not-allowed disabled:border-theme-border disabled:text-theme-muted disabled:opacity-60"
            disabled={!players.data?.links.next}
            onClick={() => setPage((p) => p + 1)}
            type="button"
          >
            {t('common.next')}
          </button>
        </div>
      </section>
      <TradeProposalPanel leagueId={leagueId} />
      {target && ownTeam ? (
        <CreateTradeForm
          leagueId={leagueId}
          market={market.data.data}
          target={target}
          roster={roster.data?.data ?? []}
          ownTeam={ownTeam!}
          close={() => setTarget(null)}
          sent={() => {
            setTarget(null);
            setTradeSent(true);
          }}
        />
      ) : null}
    </section>
  );
}
