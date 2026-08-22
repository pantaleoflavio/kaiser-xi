import { useQuery } from '@tanstack/react-query';
import { leagueLookupsApi } from '../api/lookups';
import { leagueKeys } from '../api/queryKeys';

const LOOKUP_STALE_TIME = 15 * 60 * 1000;

export function useActiveSeasons(enabled = true) {
  return useQuery({
    queryKey: leagueKeys.seasons(true),
    queryFn: () => leagueLookupsApi.seasons(true),
    staleTime: LOOKUP_STALE_TIME,
    retry: 1,
    enabled,
  });
}

export function useLeagueTypes(enabled = true) {
  return useQuery({
    queryKey: leagueKeys.types(),
    queryFn: leagueLookupsApi.leagueTypes,
    staleTime: LOOKUP_STALE_TIME,
    retry: 1,
    enabled,
  });
}