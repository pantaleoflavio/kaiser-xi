import type { CollectionResponse, PaginatedResponse, ResourceResponse } from './api';

export type LeagueRole = 'commissioner' | 'co_commissioner' | 'participant';

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

export type LeagueResponse = ResourceResponse<League>;

export type CreatedLeagueResponse = LeagueResponse;

export type LeagueCollectionResponse = PaginatedResponse<League>;

export type CreateLeaguePayload = {
  name: string;
  season_id: number;
  league_type_id: number;
  description?: string | null;
  max_participants?: number;
};

export type Season = {
  id: number;
  name: string;
  starts_at: string;
  ends_at: string;
  is_active: boolean;
  competition: { id: number; name: string; code: string };
};

export type LeagueType = {
  id: number;
  key: string;
  label: string;
};

export type SeasonCollectionResponse = CollectionResponse<Season>;
export type LeagueTypeCollectionResponse = CollectionResponse<LeagueType>;

export type LeagueMember = {
  id: number;
  name: string;
  role: LeagueReference;
};

export type LeagueMemberResponse = ResourceResponse<LeagueMember>;

export type LeagueMemberCollectionResponse = CollectionResponse<LeagueMember>;

export type ManageableLeagueRole = 'participant' | 'co_commissioner';

export type LeagueInvitation = {
  id: number;
  code: string;
  status: InvitationStatus;
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
  recipient?: { id: number; name: string };
  role: { key: InvitationRole; label: string };
  league?: { id: number; name: string };
  available_actions: Array<'accept' | 'reject'>;
};

export type LeagueInvitationResponse = ResourceResponse<LeagueInvitation>;

export type LeagueInvitationCollectionResponse = PaginatedResponse<LeagueInvitation>;

export type InvitationStatus = 'pending' | 'accepted' | 'rejected' | 'revoked' | 'expired';
export type InvitationRole = 'participant' | 'co_commissioner';
export type CreateLeagueInvitationPayload = {
  email: string;
  role: InvitationRole;
  expires_at?: string | null;
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

export type FantasyTeamResponse = ResourceResponse<FantasyTeam>;

export type FantasyTeamCollectionResponse = CollectionResponse<FantasyTeam>;

export type FantasyTeamPayload = {
  name: string;
};

export type Standing = {
  position: number;
  fantasy_team: Pick<FantasyTeam, 'id' | 'name' | 'slug'>;
  played: number;
  wins: number;
  draws: number;
  losses: number;
  goals_for: number;
  goals_against: number;
  goal_difference: number;
  points: number;
};

export type StandingsResponse = CollectionResponse<Standing>;

export type LeagueSettings = {
  initial_budget: string | number | null;
  release_refund_percentage: string | number | null;
  max_roster_players: number;
  roster_role_limits: RosterRoleLimits;
  allowed_formation_module_names: FormationModuleName[];
  allowed_formation_modules: FormationModule[];
  bench_size: number;
  bench_role_limits: BenchRoleLimits;
  max_substitutions: number;
  substitution_order_mode: SubstitutionOrderMode;
  allow_formation_change_on_substitution: boolean;
  real_captain_bonus_enabled: boolean;
  real_captain_bonus_points: number;
  defense_modifier_enabled: boolean;
  first_goal_threshold: number;
  goal_interval: number;
  status: string;
  can_update_settings: boolean;
  locked_rule_groups: string[];
};

export type LeagueSettingsResponse = ResourceResponse<LeagueSettings>;

export type PlayerRoleKey = 'goalkeeper' | 'defender' | 'midfielder' | 'forward';

export type RosterRoleLimits = Record<PlayerRoleKey, number>;

export type FormationModuleName = string;

export type FormationModule = {
  id: number;
  name: FormationModuleName;
  label: string;
  required_players_count: number;
  requirements: Record<PlayerRoleKey, number>;
};

export type BenchRoleLimits = Record<PlayerRoleKey, number>;

export type SubstitutionOrderMode = 'bench_order' | 'role_priority';

export type LeagueSettingsPayload = Partial<{
  initial_budget: number;
  release_refund_percentage: number;
  max_roster_players: number;
  roster_role_limits: RosterRoleLimits;
  allowed_formation_module_names: FormationModuleName[];
  bench_size: number;
  bench_role_limits: BenchRoleLimits;
  max_substitutions: number;
  substitution_order_mode: SubstitutionOrderMode;
  allow_formation_change_on_substitution: boolean;
  real_captain_bonus_enabled: boolean;
  real_captain_bonus_points: number;
  defense_modifier_enabled: boolean;
  first_goal_threshold: number;
  goal_interval: number;
}>;

export type RosterPlayer = {
  id: number;
  purchase_price: string | number;
  assigned_at: string | null;
  released_at: string | null;
  player: {
    id: number;
    name: string | null;
    role: PlayerRoleKey | null;
  };
};

export type RosterPlayerResponse = ResourceResponse<RosterPlayer>;

export type RosterPlayerCollectionResponse = CollectionResponse<RosterPlayer>;

export type AssignPlayerPayload = {
  player_id: number;
  purchase_price: number;
};

export type EligiblePlayer = {
  id: number;
  name: string;
  role: {
    key: PlayerRoleKey | null;
    label: string | null;
  };
  club: {
    id: number;
    name: string;
    real_club_id: number;
  } | null;
  quotation: string | number | null;
  availability: string;
};

export type EligiblePlayerCollectionResponse = PaginatedResponse<EligiblePlayer>;

export type EligiblePlayerFilters = {
  search?: string;
  role?: PlayerRoleKey;
  club_id?: number;
  page?: number;
  per_page?: number;
};
