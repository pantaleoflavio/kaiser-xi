import { ApiError } from '../../api/client';
import { useTranslation } from '../../i18n';
import type { PaginationMeta } from '../../types/api';
import type { EligiblePlayer } from '../../types/league';

export function EligiblePlayerList({
  players,
  selected,
  isLoading,
  isFetching,
  error,
  meta,
  page,
  onSelect,
  onPage,
}: {
  players: EligiblePlayer[];
  selected: EligiblePlayer | null;
  isLoading: boolean;
  isFetching: boolean;
  error: Error | null;
  meta?: PaginationMeta;
  page: number;
  onSelect: (player: EligiblePlayer) => void;
  onPage: (update: (page: number) => number) => void;
}) {
  const { t } = useTranslation();
  let message = t('roster.eligible.error');
  if (error instanceof ApiError) {
    if (error.status === 403) message = t('common.errors.forbidden');
    if (error.status === 404) message = t('common.errors.notFound');
    if (error.status === 409) message = t('common.errors.conflict');
    if (error.status === 422) message = t('common.errors.validation');
  }
  return (
    <>
      {isLoading ? (
        <p className="text-sm text-theme-muted" role="status">
          {t('roster.eligible.loading')}
        </p>
      ) : null}
      {error ? (
        <p className="text-sm text-red-200" role="alert">
          {message}
        </p>
      ) : null}
      {!isLoading && !error && players.length === 0 ? (
        <p className="rounded-lg border border-theme-border p-3 text-sm text-theme-muted">
          {t('roster.eligible.empty')}
        </p>
      ) : null}
      {players.length > 0 ? (
        <div className="grid gap-2" aria-label={t('roster.eligible.results')} role="listbox">
          {players.map((player) => (
            <button
              aria-selected={selected?.id === player.id}
              className="rounded-lg border border-theme-border px-3 py-2 text-left text-sm text-theme-text hover:border-theme-primary disabled:opacity-60"
              key={player.id}
              onClick={() => onSelect(player)}
              role="option"
              type="button"
            >
              <span className="font-semibold text-theme-text">{player.name}</span>
              <span className="ml-2 text-theme-muted">
                {player.role.label ?? t('roster.eligible.unknownRole')} ·{' '}
                {player.club?.name ?? t('roster.eligible.unknownClub')} ·{' '}
                {t('roster.eligible.quotation', { value: player.quotation ?? '—' })}
              </span>
              <span className="ml-2 rounded bg-emerald-950 px-2 py-0.5 text-xs text-theme-accent">
                {player.availability === 'available'
                  ? t('playerMarket.available')
                  : t('playerMarket.alreadyAssigned')}
              </span>
            </button>
          ))}
        </div>
      ) : null}
      {meta && meta.last_page > 1 ? (
        <nav
          className="flex items-center justify-between text-sm text-theme-muted"
          aria-label={t('roster.eligible.pagination')}
        >
          <button
            className="rounded-lg border border-theme-border px-3 py-2 disabled:opacity-50"
            disabled={page <= 1 || isFetching}
            onClick={() => onPage((value) => value - 1)}
            type="button"
          >
            {t('roster.eligible.previous')}
          </button>
          <span aria-live="polite">
            {t('roster.eligible.page', { current: meta.current_page, total: meta.last_page })}
          </span>
          <button
            className="rounded-lg border border-theme-border px-3 py-2 disabled:opacity-50"
            disabled={page >= meta.last_page || isFetching}
            onClick={() => onPage((value) => value + 1)}
            type="button"
          >
            {t('roster.eligible.next')}
          </button>
        </nav>
      ) : null}
    </>
  );
}
