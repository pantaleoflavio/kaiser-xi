import type {
  FormationModuleName,
  LeagueSettings,
  PlayerRoleKey,
  SubstitutionOrderMode,
} from '../../../types/league';
import { positionPointsToRows } from './formulaOnePositionPoints';

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
  goalkeeperCleanSheetBonusEnabled: boolean;
  goalkeeperCleanSheetBonusPoints: string;
  defenseModifierEnabled: boolean;
  firstGoalThreshold: string;
  goalInterval: string;
  formulaOnePositionPoints: string[];
};

const emptyRoleLimits = (): StringRoleLimits => ({
  goalkeeper: '',
  defender: '',
  midfielder: '',
  forward: '',
});

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
    rosterRoleLimits: settings ? roleStrings(settings.roster_role_limits) : emptyRoleLimits(),
    formationNames: settings?.allowed_formation_module_names ?? [],
    benchSize: String(settings?.bench_size ?? ''),
    benchRoleLimits: settings ? roleStrings(settings.bench_role_limits) : emptyRoleLimits(),
    maxSubstitutions: String(settings?.max_substitutions ?? ''),
    substitutionMode: settings?.substitution_order_mode ?? 'bench_order',
    allowFormationChange: settings?.allow_formation_change_on_substitution ?? false,
    realCaptainBonusEnabled: settings?.real_captain_bonus_enabled ?? false,
    realCaptainBonusPoints: String(settings?.real_captain_bonus_points ?? ''),
    goalkeeperCleanSheetBonusEnabled: settings?.goalkeeper_clean_sheet_bonus_enabled ?? false,
    goalkeeperCleanSheetBonusPoints: String(settings?.goalkeeper_clean_sheet_bonus_points ?? 1),
    defenseModifierEnabled: settings?.defense_modifier_enabled ?? false,
    firstGoalThreshold: String(settings?.first_goal_threshold ?? 66),
    goalInterval: String(settings?.goal_interval ?? 6),
    formulaOnePositionPoints: positionPointsToRows(
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

export function updateRoleLimit(limits: StringRoleLimits, role: PlayerRoleKey, value: string) {
  return { ...limits, [role]: value };
}

export type SettingsFieldErrors = Record<string, string[]>;
export const exactError = (errors: SettingsFieldErrors, field: string) => errors[field]?.[0];
export const errorId = (field: string) => `settings-${field.split('.').join('-')}-error`;
