import { useTranslation } from '../../i18n';

const ROLES = ['goalkeeper', 'defender', 'midfielder', 'forward'] as const;

export function EligiblePlayerFilters({
  search,
  role,
  clubId,
  clubs,
  onSearch,
  onRole,
  onClub,
}: {
  search: string;
  role: string;
  clubId: string;
  clubs: Array<{ id: number; name: string }>;
  onSearch: (value: string) => void;
  onRole: (value: string) => void;
  onClub: (value: string) => void;
}) {
  const { t } = useTranslation();
  return (
    <div className="grid gap-3 sm:grid-cols-3">
      <label className="text-sm text-slate-300" htmlFor="eligible-player-search">
        {t('roster.eligible.search')}
        <input
          className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
          id="eligible-player-search"
          onChange={(e) => onSearch(e.target.value)}
          placeholder={t('roster.eligible.searchPlaceholder')}
          type="search"
          value={search}
        />
      </label>
      <label className="text-sm text-slate-300" htmlFor="eligible-player-role">
        {t('roster.eligible.role')}
        <select
          className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
          id="eligible-player-role"
          onChange={(e) => onRole(e.target.value)}
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
          onChange={(e) => onClub(e.target.value)}
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
  );
}