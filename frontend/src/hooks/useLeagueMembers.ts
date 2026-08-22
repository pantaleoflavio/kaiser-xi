import { useQuery } from '@tanstack/react-query';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';

export const leagueMemberKeys = {
  all: ['league-members'] as const,
  league: (leagueId: string | number) => [...leagueMemberKeys.all, String(leagueId)] as const,
};

export function useLeagueMembers(leagueId: string | undefined) {
  return useQuery({
    queryKey: leagueMemberKeys.league(leagueId ?? ''),
    queryFn: () => leaguesApi.members(leagueId!),
    enabled: Boolean(leagueId),
    retry: (failureCount, error) =>
      !(error instanceof ApiError && error.status >= 400 && error.status < 500) && failureCount < 2,
  });
}