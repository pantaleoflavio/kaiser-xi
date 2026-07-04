import { createContext } from 'react';
import type { LoginPayload, RegisterPayload, User } from '../types/auth';

export type AuthContextValue = {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
  login: (payload: LoginPayload) => Promise<void>;
  register: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  clearError: () => void;
};

export const AuthContext = createContext<AuthContextValue | undefined>(undefined);