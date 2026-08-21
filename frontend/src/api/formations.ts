import type {
  FormationResponse,
  FormationSavePayload,
  Matchday,
  MatchdayCollectionResponse,
} from '../types/formation';
import type { ResourceResponse } from '../types/api';
import { apiClient } from './client';

const path = (leagueId: string, matchdayId: number, fantasyTeamId: string) =>
  `/leagues/${leagueId}/matchdays/${matchdayId}/fantasy-teams/${fantasyTeamId}/formation`;

export const formationsApi = {
  matchdays: (leagueId: string) =>
    apiClient<MatchdayCollectionResponse>(`/leagues/${leagueId}/matchdays`),
  calculate: (leagueId: string, matchdayId: number) =>
    apiClient<ResourceResponse<Matchday>>(
      `/leagues/${leagueId}/matchdays/${matchdayId}/calculate`,
      {
        method: 'POST',
      },
    ),
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
