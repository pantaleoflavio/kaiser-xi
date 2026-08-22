import type { SubstitutionOrderMode } from '../../../types/league';
import {
  errorId,
  exactError,
  settingsInputClass,
  substitutionModes,
  type SettingsFieldErrors,
} from './leagueSettingsForm';

type Props = {
  maxSubstitutions: string;
  mode: SubstitutionOrderMode;
  allowFormationChange: boolean;
  errors: SettingsFieldErrors;
  disabled: boolean;
  t: (key: string) => string;
  onMaximumChange: (value: string) => void;
  onModeChange: (mode: SubstitutionOrderMode) => void;
  onAllowFormationChange: (enabled: boolean) => void;
};

function FieldError({ errors, field }: { errors: SettingsFieldErrors; field: string }) {
  const message = exactError(errors, field);
  return message ? (
    <p className="mt-1 text-sm text-red-300" id={errorId(field)}>
      {message}
    </p>
  ) : null;
}

export function SubstitutionRulesSection({
  maxSubstitutions,
  mode,
  allowFormationChange,
  errors,
  disabled,
  t,
  onMaximumChange,
  onModeChange,
  onAllowFormationChange,
}: Props) {
  return (
    <fieldset className="grid gap-3 md:grid-cols-2">
      <legend className="text-lg font-semibold text-white">
        {t('leagueSettings.substitutions.title')}
      </legend>
      <label className="text-sm text-slate-300">
        {t('leagueSettings.substitutions.maximum')}
        <input
          aria-describedby={
            exactError(errors, 'max_substitutions') ? errorId('max_substitutions') : undefined
          }
          className={settingsInputClass}
          disabled={disabled}
          min="0"
          onChange={(event) => onMaximumChange(event.target.value)}
          step="1"
          type="number"
          value={maxSubstitutions}
        />
        <FieldError errors={errors} field="max_substitutions" />
      </label>
      <label className="text-sm text-slate-300">
        {t('leagueSettings.substitutions.order')}
        <select
          aria-describedby={
            exactError(errors, 'substitution_order_mode')
              ? errorId('substitution_order_mode')
              : undefined
          }
          className={settingsInputClass}
          disabled={disabled}
          onChange={(event) =>
            onModeChange(event.target.value === 'role_priority' ? 'role_priority' : 'bench_order')
          }
          value={mode}
        >
          {substitutionModes.map((value) => (
            <option key={value} value={value}>
              {t(`leagueSettings.substitutions.modes.${value}.label`)}
            </option>
          ))}
        </select>
        <FieldError errors={errors} field="substitution_order_mode" />
      </label>
      <p className="text-sm text-slate-400 md:col-span-2">
        {t(`leagueSettings.substitutions.modes.${mode}.description`)}
      </p>
      <label className="flex items-start gap-2 text-sm text-slate-200 md:col-span-2">
        <input
          checked={allowFormationChange}
          disabled={disabled}
          onChange={(event) => onAllowFormationChange(event.target.checked)}
          type="checkbox"
        />
        <span>{t('leagueSettings.substitutions.allowFormationChange')}</span>
      </label>
      <FieldError errors={errors} field="allow_formation_change_on_substitution" />
    </fieldset>
  );
}