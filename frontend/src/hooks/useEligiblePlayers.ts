import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';
import type { EligiblePlayerFilters } from '../types/league';

type EligiblePlayerQueryFilters = Required<Omit<EligiblePlayerFilters, 'role'>> &
  Pick<EligiblePlayerFilters, 'role'>;

export const eligiblePlayerKeys = {
  all: ['eligible-players'] as const,
  league: (leagueId: string | number) => [...eligiblePlayerKeys.all, String(leagueId)] as const,
  list: (leagueId: string | number, filters: EligiblePlayerQueryFilters) =>
    [
      ...eligiblePlayerKeys.league(leagueId),
      filters.search,
      filters.role,
      filters.club_id,
      filters.page,
      filters.per_page,
    ] as const,
};

export function useEligiblePlayers(
  leagueId: string | undefined,
  filters: EligiblePlayerQueryFilters,
  enabled = true,
) {
  return useQuery({
    queryKey: eligiblePlayerKeys.list(leagueId ?? '', filters),
    queryFn: () => leaguesApi.eligiblePlayers(leagueId!, filters),
    enabled: enabled && Boolean(leagueId),
    placeholderData: keepPreviousData,
    retry: (failureCount, error) =>
      !(error instanceof ApiError && error.status >= 400 && error.status < 500) && failureCount < 2,
  });
}