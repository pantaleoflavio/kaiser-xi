import { apiClient } from './client';
import type { User } from '../types/auth';

export type UpdateProfilePayload = {
  name?: string;
  email?: string;
  current_password?: string;
};

export type UpdatePasswordPayload = {
  current_password: string;
  password: string;
  password_confirmation: string;
};

export const accountApi = {
  updateProfile: (payload: UpdateProfilePayload) =>
    apiClient<User>('/auth/me', {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
  updatePassword: (payload: UpdatePasswordPayload) =>
    apiClient<void>('/auth/me/password', {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),
};