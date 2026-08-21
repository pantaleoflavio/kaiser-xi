import type { MarketPlayer } from '../../types/league';
import { useTranslation } from '../../i18n';

export function MarketDirectory({
  players,
  canTrade,
  propose,
}: {
  players: MarketPlayer[];
  canTrade: boolean;
  propose: (player: MarketPlayer) => void;
}) {
  const { t } = useTranslation();
  if (!players.length)
    return (
      <p className="rounded-xl border border-slate-800 p-6 text-slate-400">{t('market.empty')}</p>
    );
  return (
    <div className="overflow-x-auto rounded-xl border border-slate-800">
      <table className="w-full text-left">
        <thead className="bg-slate-900 text-slate-400">
          <tr>
            {['player', 'role', 'club', 'quotation', 'roster'].map((k) => (
              <th className="p-3" key={k}>
                {t(`market.${k}`)}
              </th>
            ))}
            <th className="p-3"></th>
          </tr>
        </thead>
        <tbody>
          {players.map((player) => (
            <tr className="border-t border-slate-800" key={player.id}>
              <td className="p-3 font-semibold">{player.name}</td>
              <td className="p-3">{player.role.label}</td>
              <td className="p-3">{player.club.name}</td>
              <td className="p-3">{player.quotation ?? '—'}</td>
              <td className="p-3">
                {player.fantasy_team
                  ? player.fantasy_team.is_own
                    ? t('market.yourTeam')
                    : player.fantasy_team.name
                  : t('market.unassigned')}
              </td>
              <td className="p-3 text-right">
                {canTrade && player.assignment_id && !player.fantasy_team?.is_own ? (
                  <button
                    className="whitespace-nowrap rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed disabled:opacity-50"
                    onClick={() => propose(player)}
                    type="button"
                  >
                    {t('market.trades.propose')}
                  </button>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
