import { apiClient } from './client';
import type {
  ApiResource,
  AuthResponse,
  LoginPayload,
  RegisterPayload,
  User,
} from '../types/auth';

export const authApi = {
  register: (payload: RegisterPayload) =>
    apiClient<AuthResponse>('/auth/register', {
      method: 'POST',
      body: JSON.stringify(payload),
      skipAuth: true,
    }),

  login: (payload: LoginPayload) =>
    apiClient<AuthResponse>('/auth/login', {
      method: 'POST',
      body: JSON.stringify(payload),
      skipAuth: true,
    }),

  logout: () =>
    apiClient<{ message: string }>('/auth/logout', {
      method: 'POST',
    }),

  me: async (): Promise<User> => {
    const response = await apiClient<ApiResource<User>>('/auth/me');

    return response.data;
  },
};