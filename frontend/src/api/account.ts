import { apiClient } from './client';
import type { ResourceResponse } from '../types/api';
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
  updateProfile: async (
    payload: UpdateProfilePayload,
  ): Promise<User> => {
    const response = await apiClient<ResourceResponse<User>>('/auth/me', {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });

    return response.data;
  },

  updatePassword: (payload: UpdatePasswordPayload) =>
    apiClient<void>('/auth/me/password', {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),
};