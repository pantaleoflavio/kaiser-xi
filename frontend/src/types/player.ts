import type { ResourceResponse } from './api';

export type PlayerProfileStatistics = Record<
  | 'appearances'
  | 'goals'
  | 'assists'
  | 'yellow_cards'
  | 'red_cards'
  | 'own_goals'
  | 'penalties_scored'
  | 'penalties_missed'
  | 'penalties_saved'
  | 'goals_conceded'
  | 'clean_sheets'
  | 'captain_appearances',
  number
> & { average_rating: string | null };

export type PlayerMatchday = {
  id: number;
  number: number;
  name: string | null;
  starts_at: string | null;
  status: 'played' | 'did_not_play' | 'pending' | 'no_data';
  opponent: { id: number; name: string } | null;
  venue: 'home' | 'away' | null;
  base_rating: string | null;
  goals: number | null;
  assists: number | null;
  yellow_cards: number | null;
  red_cards: number | null;
  own_goals: number | null;
  penalties_scored: number | null;
  penalties_missed: number | null;
  penalties_saved: number | null;
  goals_conceded: number | null;
  clean_sheet: boolean | null;
  is_captain: boolean | null;
};

export type PlayerProfile = {
  player: { id: number; first_name: string; last_name: string; display_name: string };
  season: { id: number; name: string };
  registration: {
    id: number;
    club: { id: number; name: string };
    role: { key: string; label: string };
    shirt_number: number | null;
  };
  statistics: PlayerProfileStatistics;
  matchdays: PlayerMatchday[];
};

export type PlayerProfileResponse = ResourceResponse<PlayerProfile>;
