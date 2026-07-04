export type User = {
  id: number;
  name: string;
  email: string;
  roles?: string[];
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
  errors?: Record<string, string[]>;
};