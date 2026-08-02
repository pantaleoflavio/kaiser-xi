import { apiClient } from './client';
import type { LeagueInvitation, LeagueMember, PaginatedResponse } from '../types/league';

export const invitationsApi = {
  inbox: () => apiClient<PaginatedResponse<LeagueInvitation>>('/invitations'),
  preview: (code: string) => apiClient<{ data: LeagueInvitation }>(`/league-invitations/${code}`),
  accept: (id: number) =>
    apiClient<{ data: LeagueMember }>(`/invitations/${id}/accept`, { method: 'POST' }),
  reject: (id: number) =>
    apiClient<{ data: LeagueInvitation }>(`/invitations/${id}/reject`, { method: 'POST' }),
};