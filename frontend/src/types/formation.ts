import type { CollectionResponse, ResourceResponse } from './api';
import type { PlayerRoleKey } from './league';

export type Matchday = {
  id: number;
  number: number;
  name: string;
  starts_at: string;
  ends_at: string;
  deadline: string;
  championship_state?: 'past' | 'current' | 'upcoming';
  formation_allowed?: boolean;
  is_calculated?: boolean;
  can_calculate?: boolean;
  can_recalculate?: boolean;
  calculation_status?: 'queued' | 'calculating' | 'completed' | 'failed' | null;
  is_waiting_for_calculation_unlock?: boolean;
};

export type FormationPlayer = {
  fantasy_team_player_id: number;
  player: { id: number; name: string; role: PlayerRoleKey };
  order: number;
};

export type Formation = {
  id: number;
  fantasy_team: { id: number; name: string; slug: string };
  formation_module: {
    id: number;
    name: string;
    requirements: Record<PlayerRoleKey, number>;
  };
  starters: FormationPlayer[];
  bench: FormationPlayer[];
  submitted: boolean;
  submitted_at: string | null;
  matchday: Pick<Matchday, 'id' | 'number' | 'name' | 'deadline'>;
};

export type FormationSavePayload = {
  formation_module_id: number;
  starters: number[];
  bench: Array<{ fantasy_team_player_id: number; order: number }>;
};

export type FormationResponse = ResourceResponse<Formation>;
export type MatchdayCollectionResponse = CollectionResponse<Matchday>;
