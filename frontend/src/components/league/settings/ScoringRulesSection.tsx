import {
  errorId,
  exactError,
  settingsInputClass,
  type SettingsFieldErrors,
} from './leagueSettingsForm';

type Props = {
  captainScoreMultiplier: string;
  defenseModifierEnabled: boolean;
  errors: SettingsFieldErrors;
  disabled: boolean;
  t: (key: string) => string;
  onCaptainScoreMultiplierChange: (value: string) => void;
  onDefenseModifierChange: (enabled: boolean) => void;
};

export function ScoringRulesSection({
  captainScoreMultiplier,
  defenseModifierEnabled,
  errors,
  disabled,
  t,
  onCaptainScoreMultiplierChange,
  onDefenseModifierChange,
}: Props) {
  const multiplierError = exactError(errors, 'captain_score_multiplier');

  return (
    <fieldset className="grid gap-4">
      <legend className="text-lg font-semibold text-white">
        {t('leagueSettings.scoring.title')}
      </legend>
      <label className="block text-sm text-slate-200">
        {t('leagueSettings.scoring.captainMultiplier')}
        <input
          aria-describedby={multiplierError ? errorId('captain_score_multiplier') : undefined}
          className={settingsInputClass}
          disabled={disabled}
          max="3"
          min="1"
          onChange={(event) => onCaptainScoreMultiplierChange(event.target.value)}
          step="0.1"
          type="number"
          value={captainScoreMultiplier}
        />
        <span className="mt-1 block text-slate-400">
          {t('leagueSettings.scoring.captainMultiplierDescription')}
        </span>
        {multiplierError ? (
          <span className="mt-1 block text-red-300" id={errorId('captain_score_multiplier')}>
            {multiplierError}
          </span>
        ) : null}
      </label>
      <label className="flex items-start gap-2 text-sm text-slate-200">
        <input
          checked={defenseModifierEnabled}
          disabled={disabled}
          onChange={(event) => onDefenseModifierChange(event.target.checked)}
          type="checkbox"
        />
        <span>
          {t('leagueSettings.scoring.defenseModifier')}
          <span className="block text-slate-400">
            {t('leagueSettings.scoring.defenseModifierDescription')}
          </span>
        </span>
      </label>
    </fieldset>
  );
}
