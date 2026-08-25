import { apiClient } from './client';
import type { ResourceResponse } from '../types/api';
import type { User } from '../types/auth';
import type { ThemeId } from '../theme/themes';

export type UpdateProfilePayload = {
  name?: string;
  email?: string;
  current_password?: string;
  theme?: ThemeId;
};

export type UpdatePasswordPayload = {
  current_password: string;
  password: string;
  password_confirmation: string;
};

export const accountApi = {
  updateProfile: async (payload: UpdateProfilePayload): Promise<User> => {
    const response = await apiClient<ResourceResponse<User>>('/auth/me', {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });

    return response.data;
  },

  deleteAccount: (current_password: string) =>
    apiClient<{ message: string }>('/auth/me', {
      method: 'DELETE',
      body: JSON.stringify({ current_password, confirmation: true }),
    }),

  updatePassword: (payload: UpdatePasswordPayload) =>
    apiClient<void>('/auth/me/password', {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),
};
