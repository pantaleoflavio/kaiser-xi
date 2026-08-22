import type {
  LeagueTypeCollectionResponse,
  SeasonCollectionResponse,
} from '../types/league';
import { apiClient } from './client';

export const leagueLookupsApi = {
  seasons: (active: boolean) =>
    apiClient<SeasonCollectionResponse>(`/seasons?active=${active ? 'true' : 'false'}`),
  leagueTypes: () => apiClient<LeagueTypeCollectionResponse>('/league-types'),
};