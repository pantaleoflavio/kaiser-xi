import type { LeagueSettingsPayload, PlayerRoleKey, RosterRoleLimits } from '../../../types/league';
import {
  hasFormulaOnePositionPoints,
  hasHeadToHeadGoalSettings,
} from './leagueSettingsApplicability';
import { positionPointRowsToMap } from './formulaOnePositionPoints';
import {
  playerRoleKeys,
  type LeagueSettingsFormState,
  type SettingsFieldErrors,
  type StringRoleLimits,
} from './leagueSettingsFormState';

export * from './leagueSettingsFormState';

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
  leagueType: string,
): ValidationResult {
  const includeHeadToHead = hasHeadToHeadGoalSettings(leagueType);
  const includeFormulaOne = hasFormulaOnePositionPoints(leagueType);
  const initialBudget = requiredNumber(form.initialBudget);
  const refund = requiredNumber(form.refund);
  const maximum = requiredNumber(form.maxRosterPlayers);
  const rosterLimits = parsedRoleLimits(form.rosterRoleLimits);
  const benchSize = requiredNumber(form.benchSize);
  const benchLimits = parsedRoleLimits(form.benchRoleLimits);
  const maximumSubstitutions = requiredNumber(form.maxSubstitutions);
  const realCaptainBonusPoints = requiredNumber(form.realCaptainBonusPoints);
  const goalkeeperCleanSheetBonusPoints = requiredNumber(form.goalkeeperCleanSheetBonusPoints);
  const firstGoalThreshold = requiredNumber(form.firstGoalThreshold);
  const goalInterval = requiredNumber(form.goalInterval);
  const errors: SettingsFieldErrors = {};
  const positionPoints = form.formulaOnePositionPoints.map(requiredNumber);
  if (includeFormulaOne && positionPoints.length < 1)
    errors.formula_one_position_points = [t('formulaOne.validation.atLeastOne')];
  else if (
    includeFormulaOne &&
    positionPoints.some((value) => value === null || !Number.isInteger(value) || value < 0)
  )
    errors.formula_one_position_points = [t('formulaOne.validation.nonNegativeInteger')];
  else if (
    includeFormulaOne &&
    positionPoints.some((value, index) => {
      const previous = positionPoints[index - 1];
      return (
        index > 0 &&
        value !== null &&
        previous !== null &&
        previous !== undefined &&
        value > previous
      );
    })
  )
    errors.formula_one_position_points = [t('formulaOne.validation.nonIncreasing')];

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
    goalkeeperCleanSheetBonusPoints === null ||
    goalkeeperCleanSheetBonusPoints < 0 ||
    goalkeeperCleanSheetBonusPoints > 5 ||
    !Number.isInteger(goalkeeperCleanSheetBonusPoints * 2)
  )
    errors.goalkeeper_clean_sheet_bonus_points = [
      t('leagueSettings.validation.cleanSheetBonusRange'),
    ];
  if (
    includeHeadToHead &&
    (firstGoalThreshold === null ||
      firstGoalThreshold < 0 ||
      firstGoalThreshold > 200 ||
      !Number.isInteger(firstGoalThreshold * 2))
  )
    errors.first_goal_threshold = [t('leagueSettings.validation.halfPoint')];
  if (
    includeHeadToHead &&
    (goalInterval === null ||
      goalInterval <= 0 ||
      goalInterval > 50 ||
      !Number.isInteger(goalInterval * 2))
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
    goalkeeperCleanSheetBonusPoints === null ||
    (includeHeadToHead && firstGoalThreshold === null) ||
    (includeHeadToHead && goalInterval === null) ||
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
      goalkeeper_clean_sheet_bonus_enabled: form.goalkeeperCleanSheetBonusEnabled,
      goalkeeper_clean_sheet_bonus_points: goalkeeperCleanSheetBonusPoints,
      defense_modifier_enabled: form.defenseModifierEnabled,
      ...(includeHeadToHead
        ? { first_goal_threshold: firstGoalThreshold!, goal_interval: goalInterval! }
        : {}),
      ...(includeFormulaOne
        ? {
            formula_one_position_points: positionPointRowsToMap(
              positionPoints.filter((points): points is number => points !== null),
            ),
          }
        : {}),
    },
  };
}
