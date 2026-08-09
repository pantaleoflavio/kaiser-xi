import { useTranslation } from '../../i18n';
import type { FormationModule } from '../../types/league';

export function FormationModuleSelector({
  modules,
  selectedId,
  disabled,
  error,
  onSelect,
}: {
  modules: FormationModule[];
  selectedId: number | null;
  disabled: boolean;
  error?: string;
  onSelect: (id: number) => void;
}) {
  const { t } = useTranslation();
  return (
    <fieldset aria-describedby={error ? 'formation-module-error' : undefined} disabled={disabled}>
      <legend className="text-lg font-semibold text-white">{t('formation.module')}</legend>
      <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {modules.map((module) => (
          <label className="rounded-xl border border-slate-700 bg-slate-950/60 p-4" key={module.id}>
            <span className="flex items-center gap-2 font-semibold text-white">
              <input
                checked={selectedId === module.id}
                name="formation-module"
                onChange={() => onSelect(module.id)}
                type="radio"
              />
              {module.label}
            </span>
            <span className="mt-2 block text-sm text-slate-300">
              {Object.entries(module.requirements)
                .map(([role, count]) => `${t(`formation.roles.${role}`)}: ${count}`)
                .join(' · ')}
            </span>
          </label>
        ))}
      </div>
      {error ? (
        <p className="mt-2 text-sm text-red-300" id="formation-module-error">
          {error}
        </p>
      ) : null}
    </fieldset>
  );
}
