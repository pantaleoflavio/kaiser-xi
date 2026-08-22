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
  defenseModifierThresholds: { id: string; threshold: string; bonus: string }[];
  goalkeeperCleanSheetBonusEnabled: boolean;
  goalkeeperCleanSheetBonusPoints: string;
  playerScoring: Record<string, string>;
  errors: SettingsFieldErrors;
  firstGoalThreshold: string;
  goalInterval: string;
  disabled: boolean;
  showGoalSettings: boolean;
  t: (key: string) => string;
  onRealCaptainBonusEnabledChange: (enabled: boolean) => void;
  onRealCaptainBonusPointsChange: (value: string) => void;
  onGoalkeeperCleanSheetBonusEnabledChange: (enabled: boolean) => void;
  onGoalkeeperCleanSheetBonusPointsChange: (value: string) => void;
  onPlayerScoringChange: (key: string, value: string) => void;
  onDefenseModifierChange: (enabled: boolean) => void;
  onDefenseModifierThresholdsChange: (
    rows: { id: string; threshold: string; bonus: string }[],
  ) => void;
  onFirstGoalThresholdChange: (value: string) => void;
  onGoalIntervalChange: (value: string) => void;
};

export function ScoringRulesSection({
  realCaptainBonusEnabled,
  realCaptainBonusPoints,
  goalkeeperCleanSheetBonusEnabled,
  goalkeeperCleanSheetBonusPoints,
  playerScoring,
  defenseModifierThresholds,
  defenseModifierEnabled,
  firstGoalThreshold,
  goalInterval,
  errors,
  disabled,
  showGoalSettings,
  t,
  onRealCaptainBonusEnabledChange,
  onRealCaptainBonusPointsChange,
  onGoalkeeperCleanSheetBonusEnabledChange,
  onGoalkeeperCleanSheetBonusPointsChange,
  onPlayerScoringChange,
  onDefenseModifierChange,
  onDefenseModifierThresholdsChange,
  onFirstGoalThresholdChange,
  onGoalIntervalChange,
}: Props) {
  const bonusError = exactError(errors, 'real_captain_bonus_points');
  const cleanSheetError = exactError(errors, 'goalkeeper_clean_sheet_bonus_points');

  return (
    <fieldset className="grid gap-4">
      <legend className="text-lg font-semibold text-white">
        {t('leagueSettings.scoring.title')}
      </legend>
      <div className="grid gap-3 sm:grid-cols-3">
        {Object.entries(playerScoring).map(([key, value]) => (
          <label className="block text-sm text-slate-200" key={key}>
            {t(`leagueSettings.scoring.${key}`)}
            <input
              className={settingsInputClass}
              disabled={disabled}
              max={100}
              min={-100}
              onChange={(event) => onPlayerScoringChange(key, event.target.value)}
              step={0.01}
              type="number"
              value={value}
            />
            {exactError(errors, key) ? (
              <span className="mt-1 block text-red-300">{exactError(errors, key)}</span>
            ) : null}
          </label>
        ))}
      </div>
      <label className="flex items-center gap-2 text-sm text-slate-200">
        <input
          checked={realCaptainBonusEnabled}
          disabled={disabled}
          onChange={(event) => onRealCaptainBonusEnabledChange(event.target.checked)}
          type="checkbox"
        />
        <span>{t('leagueSettings.scoring.enableRealCaptainBonus')}</span>
      </label>
      <label className="flex items-center gap-2 text-sm text-slate-200">
        <input
          checked={goalkeeperCleanSheetBonusEnabled}
          disabled={disabled}
          onChange={(event) => onGoalkeeperCleanSheetBonusEnabledChange(event.target.checked)}
          type="checkbox"
        />
        <span>{t('leagueSettings.scoring.goalkeeperCleanSheetBonus')}</span>
      </label>
      <label className="block text-sm text-slate-200">
        {t('leagueSettings.scoring.cleanSheetBonusPoints')}
        <input
          className={settingsInputClass}
          disabled={disabled || !goalkeeperCleanSheetBonusEnabled}
          max={5}
          min={0}
          onChange={(event) => onGoalkeeperCleanSheetBonusPointsChange(event.target.value)}
          step={0.5}
          type="number"
          value={goalkeeperCleanSheetBonusPoints}
        />
        {cleanSheetError ? (
          <span className="mt-1 block text-red-300">{cleanSheetError}</span>
        ) : null}
      </label>
      {showGoalSettings ? (
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
      ) : null}
      {showGoalSettings ? (
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
      ) : null}
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
      <div className="grid gap-2 text-sm text-slate-200">
        <span className="font-medium">{t('leagueSettings.scoring.defenseThresholds')}</span>
        {defenseModifierThresholds.map((row) => (
          <div className="grid grid-cols-[1fr_1fr_auto] gap-2" key={row.id}>
            <input
              aria-label={t('leagueSettings.scoring.defenseAverage')}
              className={settingsInputClass}
              disabled={disabled || !defenseModifierEnabled}
              min={0}
              step={0.25}
              type="number"
              value={row.threshold}
              onChange={(event) =>
                onDefenseModifierThresholdsChange(
                  defenseModifierThresholds.map((item) =>
                    item.id === row.id ? { ...item, threshold: event.target.value } : item,
                  ),
                )
              }
            />
            <input
              aria-label={t('leagueSettings.scoring.defenseBonus')}
              className={settingsInputClass}
              disabled={disabled || !defenseModifierEnabled}
              min={0}
              step={0.5}
              type="number"
              value={row.bonus}
              onChange={(event) =>
                onDefenseModifierThresholdsChange(
                  defenseModifierThresholds.map((item) =>
                    item.id === row.id ? { ...item, bonus: event.target.value } : item,
                  ),
                )
              }
            />
            <button
              className="mt-1 rounded border border-slate-700 px-3"
              disabled={
                disabled || !defenseModifierEnabled || defenseModifierThresholds.length === 1
              }
              onClick={() =>
                onDefenseModifierThresholdsChange(
                  defenseModifierThresholds.filter((item) => item.id !== row.id),
                )
              }
              type="button"
            >
              {t('leagueSettings.scoring.removeDefenseThreshold')}
            </button>
          </div>
        ))}
        {exactError(errors, 'defense_modifier_thresholds') ? (
          <span className="text-red-300">{exactError(errors, 'defense_modifier_thresholds')}</span>
        ) : null}
        <button
          className="w-fit rounded border border-emerald-500 px-3 py-1"
          disabled={disabled || !defenseModifierEnabled}
          onClick={() =>
            onDefenseModifierThresholdsChange([
              ...defenseModifierThresholds,
              { id: crypto.randomUUID(), threshold: '', bonus: '' },
            ])
          }
          type="button"
        >
          {t('leagueSettings.scoring.addDefenseThreshold')}
        </button>
      </div>
    </fieldset>
  );
}
