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

export type LeagueMember = {
  id: number;
  name: string;
  role: LeagueReference;
};

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
};

export type LeagueSettingsPayload = {
  initial_budget: number;
  release_refund_percentage: number;
};

export type RosterPlayer = {
  id: number;
  player_id: number;
  purchase_price: string | number;
  assigned_at: string | null;
  released_at: string | null;
  player?: {
    id: number;
    first_name?: string | null;
    last_name?: string | null;
    display_name?: string | null;
    slug?: string | null;
  } | null;
};

export type AssignPlayerPayload = {
  player_id: number;
  purchase_price: number;
};

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

export type LeagueCollectionResponse = {
  data: League[];
};

export type LeagueMemberCollectionResponse = {
  data: LeagueMember[];
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
