import type { FormationModule, FormationModuleName } from '../../../types/league';
import { errorId, exactError, type SettingsFieldErrors } from './leagueSettingsForm';

type Props = {
  availableNames: FormationModuleName[];
  modules: FormationModule[];
  selectedNames: FormationModuleName[];
  errors: SettingsFieldErrors;
  disabled: boolean;
  t: (key: string) => string;
  onToggle: (name: FormationModuleName) => void;
};

export function FormationRulesSection({
  availableNames,
  modules,
  selectedNames,
  errors,
  disabled,
  t,
  onToggle,
}: Props) {
  const message = exactError(errors, 'allowed_formation_module_names');
  return (
    <fieldset aria-describedby={message ? errorId('allowed_formation_module_names') : undefined}>
      <legend className="text-lg font-semibold text-white">
        {t('leagueSettings.formations.title')}
      </legend>
      <p className="mt-1 text-sm text-slate-400">{t('leagueSettings.formations.help')}</p>
      <div className="mt-3 grid gap-2 sm:grid-cols-3">
        {availableNames.map((name) => (
          <label
            className="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200"
            key={name}
          >
            <input
              checked={selectedNames.includes(name)}
              disabled={disabled}
              onChange={() => onToggle(name)}
              type="checkbox"
            />
            {modules.find((module) => module.name === name)?.label || name}
          </label>
        ))}
      </div>
      {message ? (
        <p className="mt-1 text-sm text-red-300" id={errorId('allowed_formation_module_names')}>
          {message}
        </p>
      ) : null}
    </fieldset>
  );
}