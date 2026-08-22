import { useQuery } from '@tanstack/react-query';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';

const retry = (failureCount: number, error: Error) =>
  !(error instanceof ApiError && error.status >= 400 && error.status < 500) && failureCount < 2;

export const fantasyTeamDetailKeys = {
  league: (leagueId: string) => ['league', leagueId] as const,
  settings: (leagueId: string) => ['league-settings', leagueId] as const,
  team: (leagueId: string, teamId: string) => ['fantasy-team', leagueId, teamId] as const,
  roster: (leagueId: string, teamId: string) => ['fantasy-roster', leagueId, teamId] as const,
};

export function useFantasyTeamDetail(leagueId?: string, fantasyTeamId?: string) {
  const enabled = Boolean(leagueId && fantasyTeamId);
  const league = useQuery({
    queryKey: fantasyTeamDetailKeys.league(leagueId ?? ''),
    queryFn: () => leaguesApi.show(leagueId!),
    enabled,
    retry,
  });
  const settings = useQuery({
    queryKey: fantasyTeamDetailKeys.settings(leagueId ?? ''),
    queryFn: () => leaguesApi.settings(leagueId!),
    enabled,
    retry,
  });
  const team = useQuery({
    queryKey: fantasyTeamDetailKeys.team(leagueId ?? '', fantasyTeamId ?? ''),
    queryFn: () => leaguesApi.fantasyTeam(leagueId!, fantasyTeamId!),
    enabled,
    retry,
  });
  const roster = useQuery({
    queryKey: fantasyTeamDetailKeys.roster(leagueId ?? '', fantasyTeamId ?? ''),
    queryFn: () => leaguesApi.rosterPlayers(leagueId!, fantasyTeamId!),
    enabled,
    retry,
  });

  return {
    league,
    settings,
    team,
    roster,
    isLoading: [league, settings, team, roster].some((q) => q.isLoading),
  };
}