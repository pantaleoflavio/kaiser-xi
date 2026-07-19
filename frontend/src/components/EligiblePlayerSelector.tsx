import { useEffect, useMemo, useState } from 'react';
import { ApiError } from '../api/client';
import { useEligiblePlayers } from '../hooks/useEligiblePlayers';
import { useTranslation } from '../i18n';
import type { EligiblePlayer } from '../types/league';

const PER_PAGE = 10;
const ROLES = ['goalkeeper', 'defender', 'midfielder', 'forward'] as const;

type Props = {
  leagueId: string | undefined;
  selected: EligiblePlayer | null;
  onSelect: (player: EligiblePlayer | null) => void;
  disabled: boolean;
  error?: string;
};

export function EligiblePlayerSelector({ leagueId, selected, onSelect, disabled, error }: Props) {
  const { t } = useTranslation();
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const [clubId, setClubId] = useState('');
  const [clubs, setClubs] = useState<Array<{ id: number; name: string }>>([]);
  const [page, setPage] = useState(1);

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setSearch(searchInput.trim());
      setPage(1);
    }, 350);
    return () => window.clearTimeout(timeout);
  }, [searchInput]);

  const filters = useMemo(
    () => ({
      search,
      role,
      club_id: clubId ? Number(clubId) : 0,
      page,
      per_page: PER_PAGE,
    }),
    [clubId, page, role, search],
  );
  const query = useEligiblePlayers(leagueId, filters, !disabled);
  const players = query.data?.data ?? [];
  const meta = query.data?.meta;

  useEffect(() => {
    if (!query.data) return;
    setClubs((current) => {
      const clubsById = new Map(current.map((club) => [club.id, club]));
      query.data.data.forEach((player) => {
        if (player.club.id && player.club.name) {
          clubsById.set(player.club.id, { id: player.club.id, name: player.club.name });
        }
      });
      return [...clubsById.values()].sort((first, second) => first.name.localeCompare(second.name));
    });
  }, [query.data]);
  let errorMessage = t('roster.eligible.error');
  if (query.error instanceof ApiError) {
    if (query.error.status === 403) errorMessage = t('common.errors.forbidden');
    if (query.error.status === 404) errorMessage = t('common.errors.notFound');
    if (query.error.status === 409) errorMessage = t('common.errors.conflict');
    if (query.error.status === 422) errorMessage = t('common.errors.validation');
  }

  return (
    <fieldset
      className="space-y-3"
      disabled={disabled}
      aria-describedby={error ? 'player-error' : undefined}
    >
      <legend className="text-sm font-semibold text-slate-200">{t('roster.eligible.title')}</legend>
      <div className="grid gap-3 sm:grid-cols-3">
        <label className="text-sm text-slate-300" htmlFor="eligible-player-search">
          {t('roster.eligible.search')}
          <input
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            id="eligible-player-search"
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder={t('roster.eligible.searchPlaceholder')}
            type="search"
            value={searchInput}
          />
        </label>
        <label className="text-sm text-slate-300" htmlFor="eligible-player-role">
          {t('roster.eligible.role')}
          <select
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            id="eligible-player-role"
            onChange={(event) => {
              setRole(event.target.value);
              setPage(1);
            }}
            value={role}
          >
            <option value="">{t('roster.eligible.allRoles')}</option>
            {ROLES.map((key) => (
              <option key={key} value={key}>
                {t(`roster.roles.${key}`)}
              </option>
            ))}
          </select>
        </label>
        <label className="text-sm text-slate-300" htmlFor="eligible-player-club">
          {t('roster.eligible.club')}
          <select
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            id="eligible-player-club"
            onChange={(event) => {
              setClubId(event.target.value);
              setPage(1);
            }}
            value={clubId}
          >
            <option value="">{t('roster.eligible.allClubs')}</option>
            {clubs.map((club) => (
              <option key={club.id} value={club.id}>
                {club.name}
              </option>
            ))}
          </select>
        </label>
      </div>

      {selected ? (
        <div className="flex items-center justify-between rounded-lg border border-emerald-400/30 bg-emerald-950/30 p-3 text-sm text-emerald-100">
          <span>{t('roster.eligible.selected', { name: selected.name })}</span>
          <button className="font-semibold underline" onClick={() => onSelect(null)} type="button">
            {t('roster.eligible.clear')}
          </button>
        </div>
      ) : null}

      {query.isLoading ? (
        <p className="text-sm text-slate-300" role="status">
          {t('roster.eligible.loading')}
        </p>
      ) : null}
      {query.isError ? (
        <p className="text-sm text-red-200" role="alert">
          {errorMessage}
        </p>
      ) : null}
      {!query.isLoading && !query.isError && players.length === 0 ? (
        <p className="rounded-lg border border-slate-800 p-3 text-sm text-slate-400">
          {t('roster.eligible.empty')}
        </p>
      ) : null}
      {players.length > 0 ? (
        <div className="grid gap-2" aria-label={t('roster.eligible.results')} role="listbox">
          {players.map((player) => (
            <button
              aria-selected={selected?.id === player.id}
              className="rounded-lg border border-slate-700 px-3 py-2 text-left text-sm text-slate-200 hover:border-emerald-400 disabled:opacity-60"
              key={player.id}
              onClick={() => onSelect(player)}
              role="option"
              type="button"
            >
              <span className="font-semibold text-white">{player.name}</span>
              <span className="ml-2 text-slate-400">
                {player.role.label ?? t('roster.eligible.unknownRole')} ·{' '}
                {player.club.name ?? t('roster.eligible.unknownClub')} ·{' '}
                {t('roster.eligible.quotation', { value: player.quotation ?? '—' })}
              </span>
            </button>
          ))}
        </div>
      ) : null}
      {meta && meta.last_page > 1 ? (
        <nav
          className="flex items-center justify-between text-sm text-slate-300"
          aria-label={t('roster.eligible.pagination')}
        >
          <button
            className="rounded-lg border border-slate-700 px-3 py-2 disabled:opacity-50"
            disabled={page <= 1 || query.isFetching}
            onClick={() => setPage((value) => value - 1)}
            type="button"
          >
            {t('roster.eligible.previous')}
          </button>
          <span aria-live="polite">
            {t('roster.eligible.page', { current: meta.current_page, total: meta.last_page })}
          </span>
          <button
            className="rounded-lg border border-slate-700 px-3 py-2 disabled:opacity-50"
            disabled={page >= meta.last_page || query.isFetching}
            onClick={() => setPage((value) => value + 1)}
            type="button"
          >
            {t('roster.eligible.next')}
          </button>
        </nav>
      ) : null}
      {error ? (
        <p className="text-sm text-red-200" id="player-error" role="alert">
          {error}
        </p>
      ) : null}
    </fieldset>
  );
}