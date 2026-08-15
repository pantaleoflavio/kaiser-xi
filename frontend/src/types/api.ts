export type ResourceResponse<T> = {
  data: T;
};

export type CollectionResponse<T> = {
  data: T[];
};

export type PaginationLinks = {
  first: string;
  last: string;
  prev: string | null;
  next: string | null;
};

export type PaginationMetaLink = {
  url: string | null;
  label: string;
  active: boolean;
};

export type PaginationMeta = {
  current_page: number;
  from: number | null;
  last_page: number;
  links: PaginationMetaLink[];
  path: string;
  per_page: number;
  to: number | null;
  total: number;
};

export type PaginatedResponse<T> = CollectionResponse<T> & {
  links: PaginationLinks;
  meta: PaginationMeta;
};

export type HeadToHeadMatchday = {
  id: number;
  number: number;
  name: string | null;
  starts_at: string;
};

export type HeadToHeadFantasyTeam = {
  id: number;
  name: string;
  slug: string;
};

export type HeadToHeadFixture = {
  id: number;
  home_fantasy_team: HeadToHeadFantasyTeam;
  away_fantasy_team: HeadToHeadFantasyTeam;
  result: FantasyMatchResult | null;
};

export type FantasyMatchResultStatus = 'pending' | 'calculated';

export type FantasyMatchResult = {
  id: number;
  home_points: string | number;
  away_points: string | number;
  home_goals: number;
  away_goals: number;
  status: FantasyMatchResultStatus;
  calculated_at: string | null;
};

export type HeadToHeadScheduleMatchday = {
  matchday: HeadToHeadMatchday;
  fixtures: HeadToHeadFixture[];
};

export type HeadToHeadSchedule = {
  initialized: boolean;
  generated_at: string | null;
  start_matchday: HeadToHeadMatchday | null;
  participant_count: number;
  matchdays: HeadToHeadScheduleMatchday[];
};

export type HeadToHeadScheduleResponse = ResourceResponse<HeadToHeadSchedule>;

export type InitializeHeadToHeadSchedulePayload = {
  start_matchday_id: number;
};
