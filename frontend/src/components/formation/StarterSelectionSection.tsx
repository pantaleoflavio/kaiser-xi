import { useTranslation } from '../../i18n';
import type { FormationModule, PlayerRoleKey, RosterPlayer } from '../../types/league';
import { FormationPitch, type PitchPlayer } from './FormationPitch';

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
  const selectedPlayers = selected.reduce<PitchPlayer[]>((players, id, order) => {
    const item = roster.find((player) => player.id === id);
    if (!item?.player.name || !item.player.role) return players;
    players.push({
      id: item.id,
      name: item.player.name,
      role: item.player.role,
      order,
    });
    return players;
  }, []);

  return (
    <fieldset
      aria-describedby={error ? 'starters-error' : undefined}
      disabled={disabled || !module}
    >
      <legend className="text-lg font-semibold text-theme-text">{t('formation.starters')}</legend>
      {module ? (
        <p className="mt-1 text-sm text-theme-muted">
          {t('formation.roleRequirements')}:{' '}
          {Object.entries(module.requirements)
            .map(
              ([role, required]) =>
                `${t(`formation.roles.${role}`)} ${counts[role as PlayerRoleKey] ?? 0}/${required}`,
            )
            .join(' · ')}
        </p>
      ) : (
        <p className="mt-1 text-sm text-theme-muted">{t('formation.chooseModuleFirst')}</p>
      )}
      {module ? (
        <div className="mt-4">
          <FormationPitch
            ariaLabel={`${t('formation.starters')} · ${module.name}`}
            emptyLabel={t('formation.remaining', {
              count: module.required_players_count - selected.length,
            })}
            mode="editor"
            onPlayerClick={(player) => onToggle(player.id)}
            players={selectedPlayers}
          />
        </div>
      ) : null}
      <div className="mt-4 space-y-5">
        {roles.map((groupRole) => (
          <section key={groupRole}>
            <h3 className="text-sm font-semibold uppercase tracking-wide text-theme-accent">
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
                      className="flex items-center gap-3 rounded-lg border border-theme-border bg-theme-background/60 p-3"
                      key={item.id}
                    >
                      <input
                        checked={checked}
                        disabled={!checked && Boolean(atLimit)}
                        onChange={() => onToggle(item.id)}
                        type="checkbox"
                      />
                      <span>
                        <span className="block font-medium text-theme-text">
                          {item.player.name}
                        </span>
                        <span className="text-sm text-theme-muted">
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
