import { useTranslation } from '../../i18n';
import type { FormationModule, PlayerRoleKey, RosterPlayer } from '../../types/league';

export function StarterSelectionSection({
  roster,
  selected,
  module,
  counts,
  disabled,
  error,
  onToggle,
}: {
  roster: RosterPlayer[];
  selected: number[];
  module: FormationModule | null;
  counts: Partial<Record<PlayerRoleKey, number>>;
  disabled: boolean;
  error?: string;
  onToggle: (id: number) => void;
}) {
  const { t } = useTranslation();
  const roles: PlayerRoleKey[] = ['goalkeeper', 'defender', 'midfielder', 'forward'];
  return (
    <fieldset
      aria-describedby={error ? 'starters-error' : undefined}
      disabled={disabled || !module}
    >
      <legend className="text-lg font-semibold text-white">{t('formation.starters')}</legend>
      {module ? (
        <p className="mt-1 text-sm text-slate-300">
          {t('formation.roleRequirements')}:{' '}
          {Object.entries(module.requirements)
            .map(
              ([role, required]) =>
                `${t(`formation.roles.${role}`)} ${counts[role as PlayerRoleKey] ?? 0}/${required}`,
            )
            .join(' · ')}
        </p>
      ) : (
        <p className="mt-1 text-sm text-slate-400">{t('formation.chooseModuleFirst')}</p>
      )}
      <div className="mt-4 space-y-5">
        {roles.map((groupRole) => (
          <section key={groupRole}>
            <h3 className="text-sm font-semibold uppercase tracking-wide text-emerald-200">
              {t(`formation.roles.${groupRole}`)} · {counts[groupRole] ?? 0}/
              {module?.requirements[groupRole] ?? 0}
              {module
                ? ` · ${t('formation.remaining', { count: Math.max(0, module.requirements[groupRole] - (counts[groupRole] ?? 0)) })}`
                : ''}
            </h3>
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
              {roster
                .filter((item) => item.player.role === groupRole)
                .map((item) => {
                  const checked = selected.includes(item.id);
                  const role = item.player.role as PlayerRoleKey | null;
                  const atLimit =
                    role && module ? (counts[role] ?? 0) >= module.requirements[role] : true;
                  return (
                    <label
                      className="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-950/60 p-3"
                      key={item.id}
                    >
                      <input
                        checked={checked}
                        disabled={!checked && Boolean(atLimit)}
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
          </section>
        ))}
      </div>
      {error ? (
        <p className="mt-2 text-sm text-red-300" id="starters-error">
          {error}
        </p>
      ) : null}
    </fieldset>
  );
}
