import type { ResourceResponse } from '../types/api';
import { apiClient } from './client';

export type ClassicChampionship = {
  initialized: boolean;
  started_at: string | null;
  participant_count: number;
  max_participants: number | null;
  start_matchday: { id: number; number: number; name: string | null } | null;
};

export const classicChampionshipApi = {
  get: (leagueId: string | number) =>
    apiClient<ResourceResponse<ClassicChampionship>>(`/leagues/${leagueId}/classic-championship`),
  initialize: (leagueId: string | number, startMatchdayId: number) =>
    apiClient<ResourceResponse<ClassicChampionship>>(`/leagues/${leagueId}/classic-championship`, {
      method: 'POST',
      body: JSON.stringify({ start_matchday_id: startMatchdayId }),
    }),
};
