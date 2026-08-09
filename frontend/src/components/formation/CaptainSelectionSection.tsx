import { useTranslation } from '../../i18n';
import type { RosterPlayer } from '../../types/league';

export function CaptainSelectionSection({
  starters,
  roster,
  selectedId,
  disabled,
  error,
  onSelect,
}: {
  starters: number[];
  roster: RosterPlayer[];
  selectedId: number | null;
  disabled: boolean;
  error?: string;
  onSelect: (id: number | null) => void;
}) {
  const { t } = useTranslation();
  const byId = new Map(roster.map((item) => [item.id, item]));
  return (
    <div>
      <label className="block text-lg font-semibold text-white" htmlFor="formation-captain">
        {t('formation.captain')}
      </label>
      <select
        aria-describedby={error ? 'captain-error' : undefined}
        className="mt-3 w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-white"
        disabled={disabled}
        id="formation-captain"
        onChange={(event) => onSelect(event.target.value ? Number(event.target.value) : null)}
        value={selectedId ?? ''}
      >
        <option value="">{t('formation.noCaptain')}</option>
        {starters.map((id) => (
          <option key={id} value={id}>
            {byId.get(id)?.player.name}
          </option>
        ))}
      </select>
      {error ? (
        <p className="mt-2 text-sm text-red-300" id="captain-error">
          {error}
        </p>
      ) : null}
    </div>
  );
}
