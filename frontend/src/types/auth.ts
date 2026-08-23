import type { ThemeId } from '../theme/themes';

export type User = {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string | null;
  created_at?: string | null;
  roles?: string[];
  theme?: ThemeId | null;
};

export type AuthResponse = {
  token: string;
  user: User;
};

export type LoginPayload = {
  email: string;
  password: string;
};

export type RegisterPayload = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

export type ApiErrorResponse = {
  message?: string;
  code?: string;
  missing_team_count?: number;
  errors?: Record<string, string[]>;
};
