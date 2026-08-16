import { apiClient } from './client';
import type { ClassicMatchdayResultsResponse, TeamMatchdayResultResponse } from '../types/results';

export const teamMatchdayResultsApi = {
  classic: (leagueId: string, matchdayId: number) =>
    apiClient<ClassicMatchdayResultsResponse>(
      `/leagues/${leagueId}/matchdays/${matchdayId}/classic-results`,
    ),
  show: (leagueId: string, matchdayId: number, fantasyTeamId: string) =>
    apiClient<TeamMatchdayResultResponse>(
      `/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${fantasyTeamId}/score`,
    ),
};
