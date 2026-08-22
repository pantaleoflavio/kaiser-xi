import type { HeadToHeadScheduleResponse, InitializeHeadToHeadSchedulePayload } from '../types/api';
import { apiClient } from './client';

export const headToHeadScheduleApi = {
  getSchedule: (leagueId: string | number) =>
    apiClient<HeadToHeadScheduleResponse>(`/leagues/${leagueId}/head-to-head-schedule`),
  initializeSchedule: (leagueId: string | number, payload: InitializeHeadToHeadSchedulePayload) =>
    apiClient<HeadToHeadScheduleResponse>(`/leagues/${leagueId}/head-to-head-schedule`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
};
