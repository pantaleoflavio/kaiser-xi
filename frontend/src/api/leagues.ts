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
  LeagueMemberResponse,
  ManageableLeagueRole,
  LeagueResponse,
  AssignPlayerPayload,
  CreateLeaguePayload,
  CreatedLeagueResponse,
  RosterPlayerCollectionResponse,
  RosterPlayerResponse,
  EligiblePlayerCollectionResponse,
  EligiblePlayerFilters,
  CreateLeagueInvitationPayload,
  StandingsResponse,
  FormulaOneStandingsResponse,
  MarketPlayerFilters,
  MarketPlayerResponse,
  MarketResponse,
} from '../types/league';
import { apiClient } from './client';

function eligiblePlayerQuery(filters: EligiblePlayerFilters) {
  const query = new URLSearchParams();

  if (filters.search) query.set('search', filters.search);
  if (filters.role) query.set('role', filters.role);
  if (filters.club_id) query.set('club_id', String(filters.club_id));
  if (filters.page) query.set('page', String(filters.page));
  if (filters.per_page) query.set('per_page', String(filters.per_page));

  const value = query.toString();
  return value ? `?${value}` : '';
}

export const leaguesApi = {
  market: (leagueId: string | number) => apiClient<MarketResponse>(`/leagues/${leagueId}/market`),
  marketPlayers: (leagueId: string | number, filters: MarketPlayerFilters = {}) => {
    const query = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== '') query.set(key, String(value));
    });
    return apiClient<MarketPlayerResponse>(
      `/leagues/${leagueId}/market/players${query.size ? `?${query}` : ''}`,
    );
  },
  list: () => apiClient<LeagueCollectionResponse>('/leagues'),
  create: (payload: CreateLeaguePayload) =>
    apiClient<CreatedLeagueResponse>('/leagues', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
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
  removeMember: (leagueId: string | number, memberId: string | number) =>
    apiClient<void>(`/leagues/${leagueId}/members/${memberId}`, { method: 'DELETE' }),
  updateMemberRole: (
    leagueId: string | number,
    memberId: string | number,
    role: ManageableLeagueRole,
  ) =>
    apiClient<LeagueMemberResponse>(`/leagues/${leagueId}/members/${memberId}/role`, {
      method: 'PATCH',
      body: JSON.stringify({ role }),
    }),
  invitations: (leagueId: string | number) =>
    apiClient<LeagueInvitationCollectionResponse>(`/leagues/${leagueId}/invitations`),
  createInvitation: (leagueId: string | number, payload: CreateLeagueInvitationPayload) =>
    apiClient<LeagueInvitationResponse>(`/leagues/${leagueId}/invitations`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  revokeInvitation: (leagueId: string | number, invitationId: string | number) =>
    apiClient<void>(`/leagues/${leagueId}/invitations/${invitationId}`, { method: 'DELETE' }),
  fantasyTeams: (leagueId: string | number) =>
    apiClient<FantasyTeamCollectionResponse>(`/leagues/${leagueId}/fantasy-teams`),
  standings: (leagueId: string | number) =>
    apiClient<StandingsResponse>(`/leagues/${leagueId}/standings`),
  formulaOneStandings: (leagueId: string | number) =>
    apiClient<FormulaOneStandingsResponse>(`/leagues/${leagueId}/standings`),
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

  eligiblePlayers: (leagueId: string | number, filters: EligiblePlayerFilters = {}) =>
    apiClient<EligiblePlayerCollectionResponse>(
      `/leagues/${leagueId}/eligible-players${eligiblePlayerQuery(filters)}`,
    ),
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
    apiClient<RosterPlayerResponse>(
      `/leagues/${leagueId}/fantasy-teams/${fantasyTeamId}/players/${playerId}`,
      { method: 'DELETE' },
    ),
};
