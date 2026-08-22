import { useTranslation } from '../../i18n';
import type { PlayerRoleKey, RosterPlayer } from '../../types/league';

export function BenchSelectionSection({
  roster,
  starters,
  selected,
  benchSize,
  roleLimits,
  disabled,
  error,
  onToggle,
  onMove,
}: {
  roster: RosterPlayer[];
  starters: number[];
  selected: number[];
  benchSize: number;
  roleLimits: Record<PlayerRoleKey, number>;
  disabled: boolean;
  error?: string;
  onToggle: (id: number) => void;
  onMove: (index: number, direction: -1 | 1) => void;
}) {
  const { t } = useTranslation();
  const byId = new Map(roster.map((item) => [item.id, item]));
  const roleCount = (role: string | null) =>
    selected.filter((id) => byId.get(id)?.player.role === role).length;
  return (
    <fieldset aria-describedby={error ? 'bench-error' : undefined} disabled={disabled}>
      <legend className="text-lg font-semibold text-white">{t('formation.bench')}</legend>
      <p className="mt-1 text-sm text-slate-300">
        {t('formation.benchCount', { count: selected.length, limit: benchSize })}
      </p>
      <p className="mt-1 text-sm text-slate-400">
        {Object.entries(roleLimits)
          .map(([role, limit]) => `${t(`formation.roles.${role}`)} ${roleCount(role)}/${limit}`)
          .join(' · ')}
      </p>
      <div className="mt-3 grid gap-2 sm:grid-cols-2">
        {roster
          .filter((item) => !starters.includes(item.id))
          .map((item) => {
            const checked = selected.includes(item.id);
            const role = item.player.role as PlayerRoleKey | null;
            const unavailable =
              !checked &&
              (selected.length >= benchSize || !role || roleCount(role) >= roleLimits[role]);
            return (
              <label
                className="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-950/60 p-3"
                key={item.id}
              >
                <input
                  checked={checked}
                  disabled={unavailable}
                  onChange={() => onToggle(item.id)}
                  type="checkbox"
                />
                <span>
                  <span className="block font-medium text-white">{item.player.name}</span>
                  <span className="text-sm text-slate-400">
                    {role ? t(`formation.roles.${role}`) : t('formation.unknownRole')}
                  </span>
                </span>
              </label>
            );
          })}
      </div>
      {selected.length ? (
        <ol className="mt-4 space-y-2" aria-label={t('formation.benchOrder')}>
          {selected.map((id, index) => (
            <li
              className="flex items-center justify-between rounded-lg bg-slate-800/70 p-3"
              key={id}
            >
              <span className="text-white">
                {index + 1}. {byId.get(id)?.player.name}
              </span>
              <span className="flex gap-2">
                <button
                  aria-label={t('formation.moveUp', { player: byId.get(id)?.player.name ?? '' })}
                  className="rounded border border-slate-600 px-3 py-1 text-white disabled:opacity-40"
                  disabled={index === 0}
                  onClick={() => onMove(index, -1)}
                  type="button"
                >
                  ↑
                </button>
                <button
                  aria-label={t('formation.moveDown', { player: byId.get(id)?.player.name ?? '' })}
                  className="rounded border border-slate-600 px-3 py-1 text-white disabled:opacity-40"
                  disabled={index === selected.length - 1}
                  onClick={() => onMove(index, 1)}
                  type="button"
                >
                  ↓
                </button>
              </span>
            </li>
          ))}
        </ol>
      ) : null}
      {error ? (
        <p className="mt-2 text-sm text-red-300" id="bench-error">
          {error}
        </p>
      ) : null}
    </fieldset>
  );
}
