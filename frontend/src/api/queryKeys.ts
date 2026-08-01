export const leagueKeys = {
  all: ['leagues'] as const,
  lists: () => [...leagueKeys.all, 'list'] as const,
  detail: (leagueId: string | number) => [...leagueKeys.all, 'detail', String(leagueId)] as const,
  settings: (leagueId: string | number) =>
    [...leagueKeys.all, 'settings', String(leagueId)] as const,
  seasons: ['league-create', 'seasons'] as const,
  types: ['league-create', 'types'] as const,
};