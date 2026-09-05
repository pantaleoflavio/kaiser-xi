import { apiClient } from './client';
import type {
  ClassicMatchdayResultsResponse,
  FormulaOneMatchdayResultsResponse,
  TeamMatchdayResultResponse,
  PlayerMatchdayDetails,
} from '../types/results';

export const teamMatchdayResultsApi = {
  playerDetails: (leagueId: string, playerId: number, matchdayId: number, formationId: number) =>
    apiClient<{ data: PlayerMatchdayDetails }>(
      `/leagues/${leagueId}/players/${playerId}/matchdays/${matchdayId}?formation_id=${formationId}`,
    ),
  classic: (leagueId: string, matchdayId: number) =>
    apiClient<FormulaOneMatchdayResultsResponse>(
      `/leagues/${leagueId}/matchdays/${matchdayId}/classic-results`,
    ),
  championship: (leagueId: string, matchdayId: number) =>
    apiClient<ClassicMatchdayResultsResponse>(
      `/leagues/${leagueId}/matchdays/${matchdayId}/championship-results`,
    ),
  show: (leagueId: string, matchdayId: number, fantasyTeamId: string) =>
    apiClient<TeamMatchdayResultResponse>(
      `/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${fantasyTeamId}/score`,
    ),
};
