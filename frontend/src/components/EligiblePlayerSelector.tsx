import { EligiblePlayerFilters } from './fantasy-team/EligiblePlayerFilters';
import { EligiblePlayerList } from './fantasy-team/EligiblePlayerList';
import { useEligiblePlayerFilters } from '../hooks/useEligiblePlayerFilters';
import { useEligiblePlayers } from '../hooks/useEligiblePlayers';
import { useTranslation } from '../i18n';
import type { EligiblePlayer } from '../types/league';

type Props = {
  leagueId?: string;
  selected: EligiblePlayer | null;
  onSelect: (player: EligiblePlayer | null) => void;
  disabled: boolean;
  error?: string;
};

export function EligiblePlayerSelector({ leagueId, selected, onSelect, disabled, error }: Props) {
  const { t } = useTranslation();
  const filters = useEligiblePlayerFilters();
  const query = useEligiblePlayers(leagueId, filters.filters, !disabled);

  return (
    <fieldset
      className="space-y-3"
      disabled={disabled}
      aria-describedby={error ? 'player-error' : undefined}
    >
      <legend className="text-sm font-semibold text-theme-text">
        {t('roster.eligible.title')}
      </legend>
      <EligiblePlayerFilters
        search={filters.searchInput}
        role={filters.role}
        clubId={filters.clubId}
        clubs={query.data?.filter_options.clubs ?? []}
        onSearch={filters.setSearchInput}
        onRole={filters.setRole}
        onClub={filters.setClubId}
      />

      {selected ? (
        <div className="flex items-center justify-between rounded-lg border border-theme-primary/30 bg-emerald-950/30 p-3 text-sm text-emerald-100">
          <span>{t('roster.eligible.selected', { name: selected.name })}</span>
          <button className="font-semibold underline" onClick={() => onSelect(null)} type="button">
            {t('roster.eligible.clear')}
          </button>
        </div>
      ) : null}

      <EligiblePlayerList
        players={query.data?.data ?? []}
        selected={selected}
        isLoading={query.isLoading}
        isFetching={query.isFetching}
        error={query.error}
        meta={query.data?.meta}
        page={filters.page}
        onSelect={onSelect}
        onPage={filters.setPage}
      />
      {error ? (
        <p className="text-sm text-red-200" id="player-error" role="alert">
          {error}
        </p>
      ) : null}
    </fieldset>
  );
}
