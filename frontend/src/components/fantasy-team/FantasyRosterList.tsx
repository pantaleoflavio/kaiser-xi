import { useTranslation } from '../../i18n';
import type { RosterPlayer } from '../../types/league';
import { formatDate, formatMoney } from '../../utils/formatters';
import { ReleasePlayerButton } from './ReleasePlayerButton';
import { Link } from 'react-router-dom';

export function FantasyRosterList({
  players,
  seasonId,
  canManage,
  releasingPlayerId,
  onRelease,
}: {
  players: RosterPlayer[];
  seasonId: number;
  canManage: boolean;
  releasingPlayerId: number | null;
  onRelease: (player: RosterPlayer) => void;
}) {
  const { language, t } = useTranslation();
  const fallback = t('leagueDetail.notAvailable');
  if (players.length === 0)
    return (
      <div className="mt-4 rounded-xl border border-theme-border bg-theme-background/60 p-5 text-center text-sm text-theme-muted">
        <p className="font-semibold text-theme-text">{t('roster.emptyTitle')}</p>
        <p className="mt-2">{t('roster.emptyDescription')}</p>
      </div>
    );
  return (
    <div className="mt-4 grid gap-3">
      {players.map((rosterPlayer) => (
        <div
          className="rounded-xl border border-theme-border bg-theme-background/60 p-4"
          key={rosterPlayer.player.id}
        >
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h3 className="font-semibold text-theme-text">
                <Link
                  className="hover:text-theme-accent"
                  to={`/players/${rosterPlayer.player.id}?season=${seasonId}`}
                >
                  {rosterPlayer.player.name || t('roster.unknownPlayer')}
                </Link>
              </h3>
              <p className="mt-1 text-sm text-theme-muted">
                {rosterPlayer.player.role
                  ? t(`roster.roles.${rosterPlayer.player.role}`)
                  : t('roster.eligible.unknownRole')}
              </p>
            </div>
            {canManage && !rosterPlayer.released_at ? (
              <ReleasePlayerButton
                isReleasing={releasingPlayerId === rosterPlayer.player.id}
                onRelease={() => onRelease(rosterPlayer)}
              />
            ) : null}
          </div>
          <dl className="mt-4 grid gap-3 text-sm text-theme-muted md:grid-cols-3">
            <div>
              <dt className="text-theme-muted">{t('roster.purchasePrice')}</dt>
              <dd>{formatMoney(rosterPlayer.purchase_price, fallback, language)}</dd>
            </div>
            <div>
              <dt className="text-theme-muted">{t('roster.assignedAt')}</dt>
              <dd>{formatDate(rosterPlayer.assigned_at, fallback, language)}</dd>
            </div>
            {rosterPlayer.released_at ? (
              <div>
                <dt className="text-theme-muted">{t('roster.releasedAt')}</dt>
                <dd>{formatDate(rosterPlayer.released_at, fallback, language)}</dd>
              </div>
            ) : null}
            {!rosterPlayer.released_at ? (
              <div>
                <dt className="text-theme-muted">{t('roster.availability')}</dt>
                <dd>{t('roster.notAvailable')}</dd>
              </div>
            ) : null}
          </dl>
        </div>
      ))}
    </div>
  );
}
