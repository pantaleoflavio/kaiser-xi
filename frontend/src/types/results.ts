import type { PlayerRoleKey } from './league';
import type { ResourceResponse } from './api';

export type HistoricalPlayerResult = {
  player: { id: number; name: string; role: PlayerRoleKey };
  submitted_slot: 'starter' | 'bench';
  submitted_order: number;
  used_as_substitute: boolean;
  replaced_player: { id: number; name: string } | null;
  replaced_by_player: { id: number; name: string } | null;
  effective_contribution: string | null;
  player_score: null | {
    status: 'pending' | 'confirmed' | 'did_not_play';
    base_rating: string | null;
    goals: number;
    assists: number;
    yellow_cards: number;
    red_cards: number;
    own_goals: number;
    penalties_scored: number;
    penalties_missed: number;
    penalties_saved: number;
    goals_conceded: number;
    clean_sheet: boolean;
    is_real_captain: boolean;
  };
};

export type PlayerMatchdayDetails = {
  player: { id: number; name: string; club: string; role: PlayerRoleKey };
  matchday: { id: number; number: number; name: string | null };
  status: 'confirmed' | 'pending' | 'did_not_play' | 'no_data';
  performance: { base_rating: string | null } | null;
  breakdown: null | {
    base_rating: string;
    components: { type: string; count: number; coefficient: string; total: string }[];
    fantasy_score: string;
  };
  formation_context: null | {
    source: 'persisted_result';
    bonuses: { type: 'captain_bonus' | 'clean_sheet_bonus'; total: string }[];
    effective_contribution: string;
  };
};

export type TeamMatchdayResult = {
  fantasy_team: { id: number; name: string; slug: string };
  matchday: { id: number; number: number; name: string | null; deadline: string };
  formation: {
    id: number;
    module: string;
    submitted_at: string;
    players: HistoricalPlayerResult[];
  };
  result: null | {
    status: string;
    points: string;
    base_points: string;
    substitution_points: string;
    defense_modifier_points: string;
    goalkeeper_clean_sheet_bonus_points: string;
    calculated_at: string | null;
  };
};

export type TeamMatchdayResultResponse = ResourceResponse<TeamMatchdayResult>;

export type ChampionshipMatchdayTeamResult = {
  fantasy_team: { id: number; name: string; slug: string };
  formation_submitted: boolean;
  formation_id: number | null;
  result_status: 'calculated' | 'missing_formation' | 'pending';
  points: string | null;
  finishing_position: number | null;
  championship_points: number | null;
};

export type ClassicMatchdayResultsResponse = ResourceResponse<{
  matchday: { id: number; number: number; name: string | null; counted: boolean };
  teams: ChampionshipMatchdayTeamResult[];
}>;

export type FormulaOneMatchdayResultsResponse = ResourceResponse<{
  matchday: { id: number; number: number; name: string | null; counted: boolean };
  teams: ChampionshipMatchdayTeamResult[];
}>;
