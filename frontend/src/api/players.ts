import { apiClient } from './client';
import type { PlayerProfileResponse } from '../types/player';

export const playersApi = {
  profile: (playerId: string, seasonId: string) =>
    apiClient<PlayerProfileResponse>(
      `/players/${playerId}?season_id=${encodeURIComponent(seasonId)}`,
    ),
};
