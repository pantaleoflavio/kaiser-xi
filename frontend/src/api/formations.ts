import type {
  FormationResponse,
  FormationSavePayload,
  MatchdayCollectionResponse,
} from '../types/formation';
import { apiClient } from './client';

const path = (leagueId: string, matchdayId: number, fantasyTeamId: string) =>
  `/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${fantasyTeamId}/formation`;

export const formationsApi = {
  matchdays: (leagueId: string) =>
    apiClient<MatchdayCollectionResponse>(`/leagues/${leagueId}/matchdays`),
  show: (leagueId: string, matchdayId: number, fantasyTeamId: string) =>
    apiClient<FormationResponse>(path(leagueId, matchdayId, fantasyTeamId)),
  save: (
    leagueId: string,
    matchdayId: number,
    fantasyTeamId: string,
    payload: FormationSavePayload,
  ) =>
    apiClient<FormationResponse>(path(leagueId, matchdayId, fantasyTeamId), {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),
  submit: (leagueId: string, matchdayId: number, fantasyTeamId: string) =>
    apiClient<FormationResponse>(`${path(leagueId, matchdayId, fantasyTeamId)}/submit`, {
      method: 'POST',
    }),
};
