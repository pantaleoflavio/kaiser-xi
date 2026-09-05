import { useState } from 'react';
import type { HistoricalPlayerResult, TeamMatchdayResult } from '../../types/results';
import { useTranslation } from '../../i18n';
import { PlayerMatchdayDetailsModal } from './PlayerMatchdayDetailsModal';

export function PlayerScoreBreakdownRow({
  item,
  data,
  leagueId,
}: {
  item: HistoricalPlayerResult;
  data: TeamMatchdayResult;
  leagueId: string;
}) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const playable =
    item.player_score?.status === 'confirmed' && item.player_score.base_rating !== null;
  return (
    <li className="rounded-xl border border-theme-border bg-theme-background/50 p-4">
      <div className="flex flex-wrap justify-between gap-3">
        <div>
          <button
            className="font-semibold text-theme-text underline-offset-4 hover:underline"
            onClick={() => setOpen(true)}
            type="button"
          >
            {item.player.name}
          </button>
          <p className="text-sm text-theme-muted">{t(`roster.roles.${item.player.role}`)}</p>
        </div>
        <span className="text-sm font-semibold text-theme-accent">
          {t(`results.${item.submitted_slot}`)} #{item.submitted_order}
        </span>
      </div>
      <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-theme-muted">{t('playerMatchday.rating')}</dt>
          <dd className="text-theme-text">
            {playable ? item.player_score?.base_rating : t('results.noPlayableScore')}
          </dd>
        </div>
        <div>
          <dt className="text-theme-muted">{t('results.effectiveContribution')}</dt>
          <dd className="text-theme-text">{item.effective_contribution ?? '—'}</dd>
        </div>
      </dl>
      <button
        className="mt-3 text-sm font-semibold text-theme-accent hover:underline"
        onClick={() => setOpen(true)}
        type="button"
      >
        {t('playerMatchday.details')}
      </button>
      {open ? (
        <PlayerMatchdayDetailsModal
          data={data}
          item={item}
          leagueId={leagueId}
          onClose={() => setOpen(false)}
        />
      ) : null}
    </li>
  );
}
