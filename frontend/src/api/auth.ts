import { apiClient } from './client';
import type { AuthResponse, LoginPayload, RegisterPayload, User } from '../types/auth';
import type { ResourceResponse } from '../types/api';

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

  forgotPassword: (email: string) =>
    apiClient<{ message: string }>('/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify({ email }),
      skipAuth: true,
    }),

  resetPassword: (payload: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) =>
    apiClient<{ message: string }>('/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify(payload),
      skipAuth: true,
    }),

  resendVerification: () =>
    apiClient<{ message: string }>('/auth/email/verification-notification', { method: 'POST' }),

  acknowledgePrivacy: async (): Promise<User> => {
    const response = await apiClient<ResourceResponse<User>>('/auth/privacy-acknowledgement', {
      method: 'POST',
      body: JSON.stringify({ privacy_acknowledged: true }),
    });

    return response.data;
  },

  logout: () =>
    apiClient<{ message: string }>('/auth/logout', {
      method: 'POST',
    }),

  me: async (): Promise<User> => {
    const response = await apiClient<ResourceResponse<User>>('/auth/me');

    return response.data;
  },
};
