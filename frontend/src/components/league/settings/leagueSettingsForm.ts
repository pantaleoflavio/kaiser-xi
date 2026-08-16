import type {
  FormationModuleName,
  LeagueSettings,
  LeagueSettingsPayload,
  PlayerRoleKey,
  RosterRoleLimits,
  SubstitutionOrderMode,
} from '../../../types/league';

export const playerRoleKeys: PlayerRoleKey[] = ['goalkeeper', 'defender', 'midfielder', 'forward'];

export const substitutionModes: SubstitutionOrderMode[] = ['bench_order', 'role_priority'];

export const settingsInputClass =
  'mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white disabled:opacity-60';

export type StringRoleLimits = Record<PlayerRoleKey, string>;

export type LeagueSettingsFormState = {
  initialBudget: string;
  refund: string;
  maxRosterPlayers: string;
  rosterRoleLimits: StringRoleLimits;
  formationNames: FormationModuleName[];
  benchSize: string;
  benchRoleLimits: StringRoleLimits;
  maxSubstitutions: string;
  substitutionMode: SubstitutionOrderMode;
  allowFormationChange: boolean;
  realCaptainBonusEnabled: boolean;
  realCaptainBonusPoints: string;
  defenseModifierEnabled: boolean;
  firstGoalThreshold: string;
  goalInterval: string;
  formulaOnePositionPoints: string[];
};

function roleStrings(limits: Record<PlayerRoleKey, number>): StringRoleLimits {
  return {
    goalkeeper: String(limits.goalkeeper),
    defender: String(limits.defender),
    midfielder: String(limits.midfielder),
    forward: String(limits.forward),
  };
}

export function createLeagueSettingsFormState(
  settings: LeagueSettings | null,
): LeagueSettingsFormState {
  return {
    initialBudget: String(settings?.initial_budget ?? ''),
    refund: String(settings?.release_refund_percentage ?? ''),
    maxRosterPlayers: String(settings?.max_roster_players ?? ''),
    rosterRoleLimits: settings
      ? roleStrings(settings.roster_role_limits)
      : { goalkeeper: '', defender: '', midfielder: '', forward: '' },
    formationNames: settings?.allowed_formation_module_names ?? [],
    benchSize: String(settings?.bench_size ?? ''),
    benchRoleLimits: settings
      ? roleStrings(settings.bench_role_limits)
      : { goalkeeper: '', defender: '', midfielder: '', forward: '' },
    maxSubstitutions: String(settings?.max_substitutions ?? ''),
    substitutionMode: settings?.substitution_order_mode ?? 'bench_order',
    allowFormationChange: settings?.allow_formation_change_on_substitution ?? false,
    realCaptainBonusEnabled: settings?.real_captain_bonus_enabled ?? false,
    realCaptainBonusPoints: String(settings?.real_captain_bonus_points ?? ''),
    defenseModifierEnabled: settings?.defense_modifier_enabled ?? false,
    firstGoalThreshold: String(settings?.first_goal_threshold ?? 66),
    goalInterval: String(settings?.goal_interval ?? 6),
    formulaOnePositionPoints: Object.values(
      settings?.formula_one_position_points ?? {
        1: 25,
        2: 18,
        3: 15,
        4: 12,
        5: 10,
        6: 8,
        7: 6,
        8: 4,
        9: 2,
        10: 1,
      },
    ).map(String),
  };
}

export function updateRoleLimit(
  limits: StringRoleLimits,
  role: PlayerRoleKey,
  value: string,
): StringRoleLimits {
  return { ...limits, [role]: value };
}

export type SettingsFieldErrors = Record<string, string[]>;

export function exactError(errors: SettingsFieldErrors, field: string) {
  return errors[field]?.[0];
}

export function errorId(field: string) {
  return `settings-${field.split('.').join('-')}-error`;
}

type Translate = (key: string) => string;

type ValidationResult =
  | { errors: SettingsFieldErrors; payload?: never }
  | { errors: SettingsFieldErrors; payload: LeagueSettingsPayload };

function requiredNumber(value: string): number | null {
  if (value.trim() === '') return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function parsedRoleLimits(limits: StringRoleLimits): Record<PlayerRoleKey, number | null> {
  return {
    goalkeeper: requiredNumber(limits.goalkeeper),
    defender: requiredNumber(limits.defender),
    midfielder: requiredNumber(limits.midfielder),
    forward: requiredNumber(limits.forward),
  };
}

function completeRoleLimits(
  limits: Record<PlayerRoleKey, number | null>,
): limits is RosterRoleLimits {
  return playerRoleKeys.every((role) => limits[role] !== null);
}

export function validateLeagueSettingsForm(
  form: LeagueSettingsFormState,
  t: Translate,
): ValidationResult {
  const initialBudget = requiredNumber(form.initialBudget);
  const refund = requiredNumber(form.refund);
  const maximum = requiredNumber(form.maxRosterPlayers);
  const rosterLimits = parsedRoleLimits(form.rosterRoleLimits);
  const benchSize = requiredNumber(form.benchSize);
  const benchLimits = parsedRoleLimits(form.benchRoleLimits);
  const maximumSubstitutions = requiredNumber(form.maxSubstitutions);
  const realCaptainBonusPoints = requiredNumber(form.realCaptainBonusPoints);
  const firstGoalThreshold = requiredNumber(form.firstGoalThreshold);
  const goalInterval = requiredNumber(form.goalInterval);
  const errors: SettingsFieldErrors = {};
  const positionPoints = form.formulaOnePositionPoints.map(requiredNumber);
  if (
    positionPoints.length < 1 ||
    positionPoints.some((value) => value === null || !Number.isInteger(value) || value < 0) ||
    positionPoints.some(
      (value, index) =>
        index > 0 &&
        value !== null &&
        positionPoints[index - 1] !== null &&
        value > positionPoints[index - 1]!,
    )
  )
    errors.formula_one_position_points = [t('leagueSettings.validation.nonNegativeInteger')];

  if (initialBudget === null || !Number.isInteger(initialBudget) || initialBudget < 0)
    errors.initial_budget = [t('leagueSettings.validation.nonNegativeInteger')];
  if (refund === null || !Number.isInteger(refund) || refund < 0 || refund > 100)
    errors.release_refund_percentage = [t('leagueSettings.validation.percentage')];
  if (maximum === null || !Number.isInteger(maximum) || maximum < 1)
    errors.max_roster_players = [t('leagueSettings.validation.positiveInteger')];
  playerRoleKeys.forEach((role) => {
    const value = rosterLimits[role];
    if (value === null || !Number.isInteger(value) || value < 0)
      errors[`roster_role_limits.${role}`] = [t('leagueSettings.validation.nonNegativeInteger')];
  });
  if (
    maximum !== null &&
    Number.isInteger(maximum) &&
    completeRoleLimits(rosterLimits) &&
    playerRoleKeys.every((role) => !errors[`roster_role_limits.${role}`]) &&
    Object.values(rosterLimits).reduce((sum, limit) => sum + limit, 0) < maximum
  )
    errors.roster_role_limits = [t('leagueSettings.errors.rosterValidation')];
  if (form.formationNames.length === 0)
    errors.allowed_formation_module_names = [t('leagueSettings.validation.formationRequired')];
  if (benchSize === null || !Number.isInteger(benchSize) || benchSize < 0)
    errors.bench_size = [t('leagueSettings.validation.nonNegativeInteger')];
  playerRoleKeys.forEach((role) => {
    const value = benchLimits[role];
    if (value === null || !Number.isInteger(value) || value < 0)
      errors[`bench_role_limits.${role}`] = [t('leagueSettings.validation.nonNegativeInteger')];
  });
  if (
    benchSize !== null &&
    Number.isInteger(benchSize) &&
    completeRoleLimits(benchLimits) &&
    playerRoleKeys.every((role) => !errors[`bench_role_limits.${role}`]) &&
    Object.values(benchLimits).reduce((sum, limit) => sum + limit, 0) < benchSize
  )
    errors.bench_role_limits = [t('leagueSettings.validation.benchTotal')];
  if (
    maximumSubstitutions === null ||
    !Number.isInteger(maximumSubstitutions) ||
    maximumSubstitutions < 0 ||
    benchSize === null ||
    maximumSubstitutions > benchSize
  )
    errors.max_substitutions = [t('leagueSettings.validation.substitutionRange')];
  if (
    realCaptainBonusPoints === null ||
    realCaptainBonusPoints < 0 ||
    realCaptainBonusPoints > 5 ||
    !Number.isInteger(realCaptainBonusPoints * 2)
  )
    errors.real_captain_bonus_points = [t('leagueSettings.validation.realCaptainBonusRange')];
  if (
    firstGoalThreshold === null ||
    firstGoalThreshold < 0 ||
    firstGoalThreshold > 200 ||
    !Number.isInteger(firstGoalThreshold * 2)
  )
    errors.first_goal_threshold = [t('leagueSettings.validation.halfPoint')];
  if (
    goalInterval === null ||
    goalInterval <= 0 ||
    goalInterval > 50 ||
    !Number.isInteger(goalInterval * 2)
  )
    errors.goal_interval = [t('leagueSettings.validation.halfPoint')];
  if (
    Object.keys(errors).length > 0 ||
    initialBudget === null ||
    refund === null ||
    maximum === null ||
    benchSize === null ||
    maximumSubstitutions === null ||
    realCaptainBonusPoints === null ||
    firstGoalThreshold === null ||
    goalInterval === null ||
    !completeRoleLimits(rosterLimits) ||
    !completeRoleLimits(benchLimits)
  )
    return { errors };

  return {
    errors,
    payload: {
      initial_budget: initialBudget,
      release_refund_percentage: refund,
      max_roster_players: maximum,
      roster_role_limits: rosterLimits,
      allowed_formation_module_names: form.formationNames,
      bench_size: benchSize,
      bench_role_limits: benchLimits,
      max_substitutions: maximumSubstitutions,
      substitution_order_mode: form.substitutionMode,
      allow_formation_change_on_substitution: form.allowFormationChange,
      real_captain_bonus_enabled: form.realCaptainBonusEnabled,
      real_captain_bonus_points: realCaptainBonusPoints,
      defense_modifier_enabled: form.defenseModifierEnabled,
      first_goal_threshold: firstGoalThreshold,
      goal_interval: goalInterval,
      formula_one_position_points: Object.fromEntries(
        positionPoints.map((points, index) => [String(index + 1), points ?? 0]),
      ),
    },
  };
}
