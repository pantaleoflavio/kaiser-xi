import {
  errorId,
  exactError,
  settingsInputClass,
  type SettingsFieldErrors,
} from './leagueSettingsForm';

type Props = {
  realCaptainBonusEnabled: boolean;
  realCaptainBonusPoints: string;
  defenseModifierEnabled: boolean;
  errors: SettingsFieldErrors;
  firstGoalThreshold: string;
  goalInterval: string;
  disabled: boolean;
  t: (key: string) => string;
  onRealCaptainBonusEnabledChange: (enabled: boolean) => void;
  onRealCaptainBonusPointsChange: (value: string) => void;
  onDefenseModifierChange: (enabled: boolean) => void;
  onFirstGoalThresholdChange: (value: string) => void;
  onGoalIntervalChange: (value: string) => void;
};

export function ScoringRulesSection({
  realCaptainBonusEnabled,
  realCaptainBonusPoints,
  defenseModifierEnabled,
  firstGoalThreshold,
  goalInterval,
  errors,
  disabled,
  t,
  onRealCaptainBonusEnabledChange,
  onRealCaptainBonusPointsChange,
  onDefenseModifierChange,
  onFirstGoalThresholdChange,
  onGoalIntervalChange,
}: Props) {
  const bonusError = exactError(errors, 'real_captain_bonus_points');

  return (
    <fieldset className="grid gap-4">
      <legend className="text-lg font-semibold text-white">
        {t('leagueSettings.scoring.title')}
      </legend>
      <label className="flex items-center gap-2 text-sm text-slate-200">
        <input
          checked={realCaptainBonusEnabled}
          disabled={disabled}
          onChange={(event) => onRealCaptainBonusEnabledChange(event.target.checked)}
          type="checkbox"
        />
        <span>{t('leagueSettings.scoring.enableRealCaptainBonus')}</span>
      </label>
      <label className="block text-sm text-slate-200">
        {t('leagueSettings.scoring.firstGoalThreshold')}
        <input
          className={settingsInputClass}
          disabled={disabled}
          max={200}
          min={0}
          onChange={(event) => onFirstGoalThresholdChange(event.target.value)}
          step={0.5}
          type="number"
          value={firstGoalThreshold}
        />
        <span className="mt-1 block text-slate-400">
          {t('leagueSettings.scoring.firstGoalThresholdDescription')}
        </span>
        {exactError(errors, 'first_goal_threshold') ? (
          <span className="mt-1 block text-red-300">
            {exactError(errors, 'first_goal_threshold')}
          </span>
        ) : null}
      </label>
      <label className="block text-sm text-slate-200">
        {t('leagueSettings.scoring.goalInterval')}
        <input
          className={settingsInputClass}
          disabled={disabled}
          max={50}
          min={0.5}
          onChange={(event) => onGoalIntervalChange(event.target.value)}
          step={0.5}
          type="number"
          value={goalInterval}
        />
        <span className="mt-1 block text-slate-400">
          {t('leagueSettings.scoring.goalIntervalDescription')}
        </span>
        {exactError(errors, 'goal_interval') ? (
          <span className="mt-1 block text-red-300">{exactError(errors, 'goal_interval')}</span>
        ) : null}
      </label>
      <label className="block text-sm text-slate-200">
        {t('leagueSettings.scoring.realCaptainBonusPoints')}
        <input
          aria-describedby={bonusError ? errorId('real_captain_bonus_points') : undefined}
          className={settingsInputClass}
          disabled={disabled || !realCaptainBonusEnabled}
          max={5}
          min={0}
          onChange={(event) => onRealCaptainBonusPointsChange(event.target.value)}
          step={0.5}
          type="number"
          value={realCaptainBonusPoints}
        />
        <span className="mt-1 block text-slate-400">
          {t('leagueSettings.scoring.realCaptainBonusDescription')}
        </span>
        {bonusError ? (
          <span className="mt-1 block text-red-300" id={errorId('real_captain_bonus_points')}>
            {bonusError}
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
