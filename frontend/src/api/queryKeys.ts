export const authKeys = {
  all: ['auth'] as const,
  currentUser: () => [...authKeys.all, 'current-user'] as const,
};

export const leagueKeys = {
  all: ['leagues'] as const,
  lists: () => [...leagueKeys.all, 'list'] as const,
  detail: (leagueId: string | number) => [...leagueKeys.all, 'detail', String(leagueId)] as const,
  settings: (leagueId: string | number) =>
    [...leagueKeys.all, 'settings', String(leagueId)] as const,
  market: (leagueId: string | number) => [...leagueKeys.detail(leagueId), 'market'] as const,
  marketPlayers: (leagueId: string | number, filters: object) =>
    [...leagueKeys.market(leagueId), 'players', filters] as const,
  seasons: (active: boolean) => ['league-create', 'seasons', { active }] as const,
  types: () => ['league-create', 'league-types'] as const,
  invitations: (leagueId: string | number) =>
    [...leagueKeys.detail(leagueId), 'invitations'] as const,
  fantasyTeams: (leagueId: string | number) =>
    [...leagueKeys.detail(leagueId), 'fantasy-teams'] as const,
  headToHeadSchedule: (leagueId: string | number) =>
    [...leagueKeys.detail(leagueId), 'head-to-head-schedule'] as const,
  standings: (leagueId: string | number) => [...leagueKeys.detail(leagueId), 'standings'] as const,
  championship: (leagueId: string | number, type: 'classic' | 'formula_one') =>
    [...leagueKeys.detail(leagueId), 'championship', type] as const,
};

export const invitationKeys = {
  all: ['invitations'] as const,
  inbox: (status: string = 'pending') => [...invitationKeys.all, 'inbox', { status }] as const,
};

export const leagueMutationKeys = {
  create: ['leagues', 'create'] as const,
  settings: (leagueId: string | number) =>
    ['leagues', 'settings', String(leagueId), 'update'] as const,
};

export const formationKeys = {
  matchdays: (leagueId: string | number) => ['matchdays', String(leagueId)] as const,
  detail: (leagueId: string | number, matchdayId: string | number, teamId: string | number) =>
    ['formation', String(leagueId), String(matchdayId), String(teamId)] as const,
};

export const teamMatchdayResultKeys = {
  classic: (leagueId: string | number, matchdayId: string | number) =>
    ['classic-matchday-results', String(leagueId), String(matchdayId)] as const,
  formulaOne: (leagueId: string | number, matchdayId: string | number) =>
    ['formula-one-matchday-results', String(leagueId), String(matchdayId)] as const,
  detail: (leagueId: string | number, matchdayId: string | number, teamId: string | number) =>
    ['team-matchday-result', String(leagueId), String(matchdayId), String(teamId)] as const,
};
