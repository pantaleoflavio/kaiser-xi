import { apiClient } from './client';
import type { PaginatedResponse } from '../types/api';
import type {
  LeagueInvitation,
  LeagueInvitationResponse,
  LeagueMemberResponse,
} from '../types/league';

export const invitationsApi = {
  inbox: () => apiClient<PaginatedResponse<LeagueInvitation>>('/invitations'),
  preview: (code: string) =>
    apiClient<LeagueInvitationResponse>(`/league-invitations/${code}`),
  accept: (id: number) =>
    apiClient<LeagueMemberResponse>(`/invitations/${id}/accept`, { method: 'POST' }),
  reject: (id: number) =>
    apiClient<LeagueInvitationResponse>(`/invitations/${id}/reject`, { method: 'POST' }),
};