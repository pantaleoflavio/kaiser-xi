export const leagueKeys = {
  all: ['leagues'] as const,
  lists: () => [...leagueKeys.all, 'list'] as const,
  detail: (leagueId: string | number) => [...leagueKeys.all, 'detail', String(leagueId)] as const,
  settings: (leagueId: string | number) =>
    [...leagueKeys.all, 'settings', String(leagueId)] as const,
  seasons: (active: boolean) => ['league-create', 'seasons', { active }] as const,
  types: () => ['league-create', 'league-types'] as const,
  invitations: (leagueId: string | number) =>
    [...leagueKeys.detail(leagueId), 'invitations'] as const,
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