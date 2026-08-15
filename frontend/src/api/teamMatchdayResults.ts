import { apiClient } from './client';
import type { TeamMatchdayResultResponse } from '../types/results';

export const teamMatchdayResultsApi = {
  show: (leagueId: string, matchdayId: number, fantasyTeamId: string) =>
    apiClient<TeamMatchdayResultResponse>(
      `/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${fantasyTeamId}/score`,
    ),
};
