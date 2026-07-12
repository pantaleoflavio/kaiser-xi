import type {
  FantasyTeamCollectionResponse,
  FantasyTeamPayload,
  FantasyTeamResponse,
  LeagueCollectionResponse,
  LeagueSettingsPayload,
  LeagueSettingsResponse,
  LeagueInvitationCollectionResponse,
  LeagueInvitationResponse,
  LeagueMemberCollectionResponse,
  LeagueResponse,
  AssignPlayerPayload,
  RosterPlayerCollectionResponse,
  RosterPlayerResponse,
} from '../types/league';
import { apiClient } from './client';

export type CreateLeagueInvitationPayload = {
  max_uses?: number | null;
  expires_at?: string | null;
};

export const leaguesApi = {
  list: () => apiClient<LeagueCollectionResponse>('/leagues'),
  show: (leagueId: string | number) => apiClient<LeagueResponse>(`/leagues/${leagueId}`),
  settings: (leagueId: string | number) =>
    apiClient<LeagueSettingsResponse>(`/leagues/${leagueId}/settings`),
  updateSettings: (leagueId: string | number, payload: LeagueSettingsPayload) =>
    apiClient<LeagueSettingsResponse>(`/leagues/${leagueId}/settings`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
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

    rosterPlayers: (leagueId: string | number, fantasyTeamId: string | number) =>
    apiClient<RosterPlayerCollectionResponse>(
      `/leagues/${leagueId}/fantasy-teams/${fantasyTeamId}/players`,
    ),
  assignPlayer: (
    leagueId: string | number,
    fantasyTeamId: string | number,
    payload: AssignPlayerPayload,
  ) =>
    apiClient<RosterPlayerResponse>(`/leagues/${leagueId}/fantasy-teams/${fantasyTeamId}/players`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  releasePlayer: (
    leagueId: string | number,
    fantasyTeamId: string | number,
    playerId: string | number,
  ) =>
    apiClient<void>(`/leagues/${leagueId}/fantasy-teams/${fantasyTeamId}/players/${playerId}`, {
      method: 'DELETE',
    }),
};