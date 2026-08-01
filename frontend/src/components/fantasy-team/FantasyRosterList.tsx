import { useTranslation } from '../../i18n';
import type { RosterPlayer } from '../../types/league';
import { formatDate, formatMoney } from '../../utils/formatters';
import { ReleasePlayerButton } from './ReleasePlayerButton';

export function FantasyRosterList({
  players,
  canManage,
  releasingPlayerId,
  onRelease,
}: {
  players: RosterPlayer[];
  canManage: boolean;
  releasingPlayerId: number | null;
  onRelease: (player: RosterPlayer) => void;
}) {
  const { language, t } = useTranslation();
  const fallback = t('leagueDetail.notAvailable');
  if (players.length === 0)
    return (
      <div className="mt-4 rounded-xl border border-slate-800 bg-slate-950/60 p-5 text-center text-sm text-slate-300">
        <p className="font-semibold text-white">{t('roster.emptyTitle')}</p>
        <p className="mt-2">{t('roster.emptyDescription')}</p>
      </div>
    );
  return (
    <div className="mt-4 grid gap-3">
      {players.map((rosterPlayer) => (
        <div
          className="rounded-xl border border-slate-800 bg-slate-950/60 p-4"
          key={rosterPlayer.player.id}
        >
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h3 className="font-semibold text-white">
                {rosterPlayer.player.name || t('roster.unknownPlayer')}
              </h3>
              <p className="mt-1 text-sm text-slate-400">
                {t('roster.playerId', { id: rosterPlayer.player.id })}
              </p>
            </div>
            {canManage && !rosterPlayer.released_at ? (
              <ReleasePlayerButton
                isReleasing={releasingPlayerId === rosterPlayer.player.id}
                onRelease={() => onRelease(rosterPlayer)}
              />
            ) : null}
          </div>
          <dl className="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-3">
            <div>
              <dt className="text-slate-500">{t('roster.purchasePrice')}</dt>
              <dd>{formatMoney(rosterPlayer.purchase_price, fallback, language)}</dd>
            </div>
            <div>
              <dt className="text-slate-500">{t('roster.assignedAt')}</dt>
              <dd>{formatDate(rosterPlayer.assigned_at, fallback, language)}</dd>
            </div>
            <div>
              <dt className="text-slate-500">{t('roster.releasedAt')}</dt>
              <dd>{formatDate(rosterPlayer.released_at, fallback, language)}</dd>
            </div>
          </dl>
        </div>
      ))}
    </div>
  );
}