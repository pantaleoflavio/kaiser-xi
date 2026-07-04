import type { LeagueCollectionResponse } from '../types/league';
import { apiClient } from './client';

export const leaguesApi = {
  list: () => apiClient<LeagueCollectionResponse>('/leagues'),
};