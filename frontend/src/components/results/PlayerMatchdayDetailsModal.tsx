import { useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { X } from 'lucide-react';
import { teamMatchdayResultsApi } from '../../api/teamMatchdayResults';
import { teamMatchdayResultKeys } from '../../api/queryKeys';
import type { HistoricalPlayerResult, TeamMatchdayResult } from '../../types/results';
import { useTranslation } from '../../i18n';

const signed = (value: string) => (Number(value) > 0 ? `+${value}` : value);

export function PlayerMatchdayDetailsModal({
  data,
  item,
  leagueId,
  onClose,
}: {
  data: TeamMatchdayResult;
  item: HistoricalPlayerResult;
  leagueId: string;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const closeRef = useRef<HTMLButtonElement>(null);
  const query = useQuery({
    queryKey: teamMatchdayResultKeys.player(
      leagueId,
      item.player.id,
      data.matchday.id,
      data.formation.id,
    ),
    queryFn: () =>
      teamMatchdayResultsApi.playerDetails(
        leagueId,
        item.player.id,
        data.matchday.id,
        data.formation.id,
      ),
  });
  useEffect(() => {
    closeRef.current?.focus();
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const keydown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', keydown);
    return () => {
      document.body.style.overflow = previous;
      document.removeEventListener('keydown', keydown);
    };
  }, [onClose]);
  const details = query.data?.data;
  return (
    <div
      aria-labelledby="player-matchday-title"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
      role="dialog"
    >
      <section className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-theme-border bg-theme-surface p-5 shadow-2xl sm:p-6">
        <header className="flex items-start justify-between gap-4">
          <div>
            <h2 className="text-xl font-bold text-theme-text" id="player-matchday-title">
              {details?.player.name ?? item.player.name}
            </h2>
            <p className="text-sm text-theme-muted">
              {t('playerMatchday.matchday', { number: data.matchday.number })}
            </p>
          </div>
          <button
            aria-label={t('playerMatchday.close')}
            className="rounded-lg p-2 text-theme-muted hover:bg-theme-background"
            onClick={onClose}
            ref={closeRef}
            type="button"
          >
            <X aria-hidden="true" size={20} />
          </button>
        </header>
        {query.isLoading ? (
          <p className="mt-6 text-theme-muted" role="status">
            {t('common.loading')}
          </p>
        ) : null}
        {query.isError ? (
          <p className="mt-6 text-red-300" role="alert">
            {t('playerMatchday.error')}
          </p>
        ) : null}
        {details ? (
          <div className="mt-5">
            <p className="text-sm text-theme-muted">
              {details.player.club} · {t(`roster.roles.${details.player.role}`)}
            </p>
            {details.status !== 'confirmed' ? (
              <p className="mt-6 rounded-xl bg-theme-background p-4 text-theme-text">
                {t(`playerMatchday.status.${details.status}`)}
              </p>
            ) : null}
            {details.breakdown ? (
              <dl className="mt-5 space-y-3 text-sm">
                <div className="flex justify-between border-b border-theme-border pb-3">
                  <dt>{t('playerMatchday.rating')}</dt>
                  <dd>{details.breakdown.base_rating}</dd>
                </div>
                {details.breakdown.components.map((component) => (
                  <div className="grid grid-cols-[1fr_auto_auto] gap-3" key={component.type}>
                    <dt>{t(`playerMatchday.components.${component.type}`)}</dt>
                    <dd>
                      {component.count} × {signed(component.coefficient)}
                    </dd>
                    <dd className="font-semibold">{signed(component.total)}</dd>
                  </div>
                ))}
                <div className="flex justify-between border-t border-theme-border pt-3 text-base font-bold">
                  <dt>{t('playerMatchday.fantasyScore')}</dt>
                  <dd>{details.breakdown.fantasy_score}</dd>
                </div>
                {details.formation_context?.bonuses.map((bonus) => (
                  <div className="flex justify-between" key={bonus.type}>
                    <dt>{t(`playerMatchday.components.${bonus.type}`)}</dt>
                    <dd>{signed(bonus.total)}</dd>
                  </div>
                ))}
                {details.formation_context ? (
                  <div className="flex justify-between border-t border-theme-border pt-3 text-base font-bold">
                    <dt>{t('playerMatchday.contribution')}</dt>
                    <dd>{details.formation_context.effective_contribution}</dd>
                  </div>
                ) : null}
              </dl>
            ) : null}
          </div>
        ) : null}
      </section>
    </div>
  );
}
