import type {
  FantasyTeamCollectionResponse,
  FantasyTeamPayload,
  FantasyTeamResponse,
  LeagueCollectionResponse,
  LeagueInvitationCollectionResponse,
  LeagueInvitationResponse,
  LeagueMemberCollectionResponse,
  LeagueResponse,
} from '../types/league';
import { apiClient } from './client';

export type CreateLeagueInvitationPayload = {
  max_uses?: number | null;
  expires_at?: string | null;
};

export const leaguesApi = {
  list: () => apiClient<LeagueCollectionResponse>('/leagues'),
  show: (leagueId: string | number) => apiClient<LeagueResponse>(`/leagues/${leagueId}`),
  members: (leagueId: string | number) =>
    apiClient<LeagueMemberCollectionResponse>(`/leagues/${leagueId}/members`),
  invitations: (leagueId: string | number) =>
    apiClient<LeagueInvitationCollectionResponse>(`/leagues/${leagueId}/invitations`),
  createInvitation: (leagueId: string | number, payload: CreateLeagueInvitationPayload) =>
    apiClient<LeagueInvitationResponse>(`/leagues/${leagueId}/invitations`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  deleteInvitation: (leagueId: string | number, invitationId: string | number) =>
    apiClient<void>(`/leagues/${leagueId}/invitations/${invitationId}`, { method: 'DELETE' }),
  fantasyTeams: (leagueId: string | number) =>
    apiClient<FantasyTeamCollectionResponse>(`/leagues/${leagueId}/fantasy-teams`),
  createFantasyTeam: (leagueId: string | number, payload: FantasyTeamPayload) =>
    apiClient<FantasyTeamResponse>(`/leagues/${leagueId}/fantasy-teams`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  fantasyTeam: (leagueId: string | number, fantasyTeamId: string | number) =>
    apiClient<FantasyTeamResponse>(`/leagues/${leagueId}/fantasy-teams/${fantasyTeamId}`),
  updateFantasyTeam: (
    leagueId: string | number,
    fantasyTeamId: string | number,
    payload: FantasyTeamPayload,
  ) =>
    apiClient<FantasyTeamResponse>(`/leagues/${leagueId}/fantasy-teams/${fantasyTeamId}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
};