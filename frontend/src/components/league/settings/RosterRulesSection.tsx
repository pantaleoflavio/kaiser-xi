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
  initialBudget: string;
  refund: string;
  maxRosterPlayers: string;
  roleLimits: StringRoleLimits;
  errors: SettingsFieldErrors;
  disabled: boolean;
  t: (key: string) => string;
  onInitialBudgetChange: (value: string) => void;
  onRefundChange: (value: string) => void;
  onMaxRosterPlayersChange: (value: string) => void;
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

export function RosterRulesSection({
  initialBudget,
  refund,
  maxRosterPlayers,
  roleLimits,
  errors,
  disabled,
  t,
  onInitialBudgetChange,
  onRefundChange,
  onMaxRosterPlayersChange,
  onRoleLimitChange,
}: Props) {
  return (
    <fieldset className="grid gap-3 md:grid-cols-2">
      <legend className="mb-2 text-lg font-semibold text-white">
        {t('leagueSettings.sections.roster')}
      </legend>
      <label className="text-sm text-slate-300">
        {t('budget.initialBudget')}
        <input
          aria-describedby={
            exactError(errors, 'initial_budget') ? errorId('initial_budget') : undefined
          }
          className={settingsInputClass}
          disabled={disabled}
          min="0"
          onChange={(event) => onInitialBudgetChange(event.target.value)}
          step="1"
          type="number"
          value={initialBudget}
        />
        <FieldError errors={errors} field="initial_budget" />
      </label>
      <label className="text-sm text-slate-300">
        {t('budget.releaseRefundPercentage')}
        <input
          aria-describedby={
            exactError(errors, 'release_refund_percentage')
              ? errorId('release_refund_percentage')
              : undefined
          }
          className={settingsInputClass}
          disabled={disabled}
          max="100"
          min="0"
          onChange={(event) => onRefundChange(event.target.value)}
          step="1"
          type="number"
          value={refund}
        />
        <FieldError errors={errors} field="release_refund_percentage" />
      </label>
      <label className="text-sm text-slate-300 md:col-span-2">
        {t('leagueSettings.maxRosterPlayers')}
        <input
          aria-describedby={
            exactError(errors, 'max_roster_players') ? errorId('max_roster_players') : undefined
          }
          className={settingsInputClass}
          disabled={disabled}
          min="1"
          onChange={(event) => onMaxRosterPlayersChange(event.target.value)}
          step="1"
          type="number"
          value={maxRosterPlayers}
        />
        <FieldError errors={errors} field="max_roster_players" />
      </label>
      {playerRoleKeys.map((role) => {
        const field = `roster_role_limits.${role}`;
        return (
          <label className="text-sm text-slate-300" key={role}>
            {t(`leagueSettings.roles.${role}`)}
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
        <FieldError errors={errors} field="roster_role_limits" />
        <p className="text-sm text-slate-400">{t('leagueSettings.rosterRuleHelp')}</p>
      </div>
    </fieldset>
  );
}