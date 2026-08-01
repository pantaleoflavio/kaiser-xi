export type LeagueRole = 'commissioner' | 'co_commissioner' | 'participant' | string;

export type LeagueReference = {
  key: string;
  label: string;
};

export type League = {
  id: number;
  name: string;
  description: string | null;
  max_participants: number | null;
  season: {
    id: number;
    name: string;
    competition: {
      id: number;
      name: string;
    };
  };
  type: LeagueReference;
  status: LeagueReference;
  my_role: LeagueRole | null;
};

export type CreateLeaguePayload = {
  name: string;
  season_id: number;
  league_type_id: number;
  description?: string | null;
  max_participants?: number;
};

export type SeasonOption = {
  id: number;
  name: string;
  competition: { id: number; name: string };
};

export type LeagueTypeOption = {
  id: number;
  key: string;
  label: string;
};

export type LeagueMember = {
  id: number;
  name: string;
  role: LeagueReference;
};

export type ManageableLeagueRole = 'participant' | 'co_commissioner';

export type LeagueInvitation = {
  id: number;
  code: string;
  status: string;
  max_uses: number | null;
  used_count: number;
  remaining_uses: number | null;
  expires_at: string | null;
  is_active: boolean;
  is_expired: boolean;
  is_exhausted: boolean;
  is_available: boolean;
  created_at: string | null;
  creator?: {
    id: number;
    name: string;
  };
};

export type FantasyTeamOwner = {
  id: number;
  name: string;
};

export type FantasyTeam = {
  id: number;
  name: string;
  slug: string;
  league_id: number;
  owner: FantasyTeamOwner;
  is_owned_by_current_user: boolean;
  budget?: string | number | null;
  remaining_budget?: string | number | null;
  created_at: string | null;
  updated_at: string | null;
};

export type FantasyTeamPayload = {
  name: string;
};

export type LeagueSettings = {
  initial_budget: string | number | null;
  release_refund_percentage: string | number | null;
  max_roster_players: number;
  roster_role_limits: RosterRoleLimits;
};

export type PlayerRoleKey = 'goalkeeper' | 'defender' | 'midfielder' | 'forward';

export type RosterRoleLimits = Record<PlayerRoleKey, number>;

export type LeagueSettingsPayload = Partial<{
  initial_budget: number;
  release_refund_percentage: number;
  max_roster_players: number;
  roster_role_limits: RosterRoleLimits;
}>;

export type RosterPlayer = {
  purchase_price: string | number;
  assigned_at: string | null;
  released_at: string | null;
  player: {
    id: number;
    name: string | null;
    role: string | null;
  };
};

export type AssignPlayerPayload = {
  player_id: number;
  purchase_price: number;
};

export type EligiblePlayer = {
  id: number;
  name: string;
  role: {
    key: string | null;
    label: string | null;
  };
  club: {
    id: number | null;
    name: string | null;
    real_club_id: number | null;
  };
  quotation: number | null;
  availability: string;
};

export type EligiblePlayerFilters = {
  search?: string;
  role?: string;
  club_id?: number;
  page?: number;
  per_page?: number;
};

export type PaginationLink = {
  url: string | null;
  label: string;
  active: boolean;
};

export type PaginationMeta = {
  current_page: number;
  from: number | null;
  last_page: number;
  links: PaginationLink[];
  path: string;
  per_page: number;
  to: number | null;
  total: number;
};

export type PaginationLinks = {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
};

export type PaginatedResponse<T> = {
  data: T[];
  links: PaginationLinks;
  meta: PaginationMeta;
};

export type EligiblePlayerCollectionResponse = PaginatedResponse<EligiblePlayer>;

export type LeagueSettingsResponse = {
  data: LeagueSettings;
};

export type RosterPlayerCollectionResponse = {
  data: RosterPlayer[];
  meta?: {
    budget?: string | number | null;
    total_budget?: string | number | null;
    remaining_budget?: string | number | null;
  };
};

export type RosterPlayerResponse = {
  data: RosterPlayer;
};

export type LeagueResponse = {
  data: League;
};

export type CreatedLeagueResponse = LeagueResponse;

export type LeagueCollectionResponse = {
  data: League[];
};

export type LeagueMemberCollectionResponse = {
  data: LeagueMember[];
};

export type LeagueMemberResponse = {
  data: LeagueMember;
};

export type LeagueInvitationResponse = {
  data: LeagueInvitation;
};

export type LeagueInvitationCollectionResponse = {
  data: LeagueInvitation[];
};

export type FantasyTeamResponse = {
  data: FantasyTeam;
};

export type FantasyTeamCollectionResponse = {
  data: FantasyTeam[];
};
