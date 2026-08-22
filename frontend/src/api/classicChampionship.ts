import type { ResourceResponse } from '../types/api';
import { apiClient } from './client';

export type ClassicChampionship = {
  initialized: boolean;
  started_at: string | null;
  participant_count: number;
  max_participants: number | null;
  start_matchday: { id: number; number: number; name: string | null } | null;
  available_start_matchdays: Array<{
    id: number;
    number: number;
    name: string | null;
    starts_at: string;
  }>;
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

export const formulaOneChampionshipApi = {
  get: (leagueId: string | number) =>
    apiClient<ResourceResponse<ClassicChampionship>>(
      `/leagues/${leagueId}/formula-one-championship`,
    ),
  initialize: (leagueId: string | number, startMatchdayId: number) =>
    apiClient<ResourceResponse<ClassicChampionship>>(
      `/leagues/${leagueId}/formula-one-championship`,
      {
        method: 'POST',
        body: JSON.stringify({ start_matchday_id: startMatchdayId }),
      },
    ),
};
