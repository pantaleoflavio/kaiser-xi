import { errorId, exactError, type SettingsFieldErrors } from './leagueSettingsForm';

type Props = {
  captainEnabled: boolean;
  errors: SettingsFieldErrors;
  disabled: boolean;
  t: (key: string) => string;
  onCaptainChange: (enabled: boolean) => void;
};

function FieldError({ errors, field }: { errors: SettingsFieldErrors; field: string }) {
  const message = exactError(errors, field);
  return message ? (
    <p className="mt-1 text-sm text-red-300" id={errorId(field)}>
      {message}
    </p>
  ) : null;
}

export function CaptainRulesSection({
  captainEnabled,
  errors,
  disabled,
  t,
  onCaptainChange,
}: Props) {
  return (
    <fieldset className="grid gap-3">
      <legend className="text-lg font-semibold text-white">
        {t('leagueSettings.captains.title')}
      </legend>
      <label className="flex items-start gap-2 text-sm text-slate-200">
        <input
          aria-describedby={
            exactError(errors, 'captain_enabled') ? errorId('captain_enabled') : undefined
          }
          checked={captainEnabled}
          disabled={disabled}
          onChange={(event) => onCaptainChange(event.target.checked)}
          type="checkbox"
        />
        <span>{t('leagueSettings.captains.captain')}</span>
      </label>
      <FieldError errors={errors} field="captain_enabled" />
    </fieldset>
  );
}