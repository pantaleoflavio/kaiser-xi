import type { PlayerRoleKey } from '../../../types/league';
import {
  errorId,
  exactError,
  playerRoleKeys,
  settingsInputClass,
  type SettingsFieldErrors,
  type StringRoleLimits,
} from './leagueSettingsForm';

type Props = {
  benchSize: string;
  roleLimits: StringRoleLimits;
  errors: SettingsFieldErrors;
  disabled: boolean;
  t: (key: string) => string;
  onBenchSizeChange: (value: string) => void;
  onRoleLimitChange: (role: PlayerRoleKey, value: string) => void;
};

function FieldError({ errors, field }: { errors: SettingsFieldErrors; field: string }) {
  const message = exactError(errors, field);
  return message ? (
    <p className="mt-1 text-sm text-red-300" id={errorId(field)}>
      {message}
    </p>
  ) : null;
}

export function BenchRulesSection({
  benchSize,
  roleLimits,
  errors,
  disabled,
  t,
  onBenchSizeChange,
  onRoleLimitChange,
}: Props) {
  return (
    <fieldset className="grid gap-3 md:grid-cols-2">
      <legend className="text-lg font-semibold text-white">
        {t('leagueSettings.bench.title')}
      </legend>
      <p className="text-sm text-slate-400 md:col-span-2">{t('leagueSettings.bench.help')}</p>
      <label className="text-sm text-slate-300 md:col-span-2">
        {t('leagueSettings.bench.size')}
        <input
          aria-describedby={exactError(errors, 'bench_size') ? errorId('bench_size') : undefined}
          className={settingsInputClass}
          disabled={disabled}
          min="0"
          onChange={(event) => onBenchSizeChange(event.target.value)}
          step="1"
          type="number"
          value={benchSize}
        />
        <FieldError errors={errors} field="bench_size" />
      </label>
      {playerRoleKeys.map((role) => {
        const field = `bench_role_limits.${role}`;
        return (
          <label className="text-sm text-slate-300" key={role}>
            {t(`leagueSettings.bench.roles.${role}`)}
            <input
              aria-describedby={exactError(errors, field) ? errorId(field) : undefined}
              className={settingsInputClass}
              disabled={disabled}
              min="0"
              onChange={(event) => onRoleLimitChange(role, event.target.value)}
              step="1"
              type="number"
              value={roleLimits[role]}
            />
            <FieldError errors={errors} field={field} />
          </label>
        );
      })}
      <div className="md:col-span-2">
        <FieldError errors={errors} field="bench_role_limits" />
      </div>
    </fieldset>
  );
}