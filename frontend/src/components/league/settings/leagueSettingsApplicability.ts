export type SupportedLeagueType = 'classic' | 'head_to_head' | 'formula_one';

export function hasHeadToHeadGoalSettings(leagueType: string): boolean {
  return leagueType === 'head_to_head';
}

export function hasFormulaOnePositionPoints(leagueType: string): boolean {
  return leagueType === 'formula_one';
}
