import { clearStoredToken, getStoredToken } from '../auth/tokenStorage';
import type { ApiErrorResponse } from '../types/auth';

const configuredApiUrl = import.meta.env.VITE_API_BASE_URL;
if (import.meta.env.PROD && !configuredApiUrl) {
  throw new Error('VITE_API_BASE_URL is required for production builds.');
}

export const API_BASE_URL = (configuredApiUrl ?? 'http://localhost:8000/api/v1').replace(/\/$/, '');

export class ApiError extends Error {
  status: number;
  code?: string;
  missingTeamCount?: number;
  errors?: Record<string, string[]>;

  constructor(
    message: string,
    status: number,
    errors?: Record<string, string[]>,
    code?: string,
    missingTeamCount?: number,
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
    this.code = code;
    this.missingTeamCount = missingTeamCount;
  }
}

type ApiRequestOptions = RequestInit & {
  skipAuth?: boolean;
};

export async function apiClient<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const { skipAuth = false, headers, ...requestOptions } = options;
  const token = getStoredToken();
  const requestHeaders = new Headers(headers);

  if (!requestHeaders.has('Accept')) requestHeaders.set('Accept', 'application/json');
  if (!requestHeaders.has('Content-Type') && requestOptions.body) {
    requestHeaders.set('Content-Type', 'application/json');
  }
  if (!skipAuth && token) requestHeaders.set('Authorization', `Bearer ${token}`);

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...requestOptions,
    headers: requestHeaders,
  });

  if (response.status === 204) return undefined as T;

  const contentType = response.headers.get('content-type');
  const body = contentType?.includes('application/json') ? await response.json() : null;

  if (!response.ok) {
    if (response.status === 401) clearStoredToken();
    const errorBody = body as ApiErrorResponse | null;
    throw new ApiError(
      errorBody?.message ?? `Request failed with status ${response.status}`,
      response.status,
      errorBody?.errors,
      errorBody?.code,
      errorBody?.missing_team_count,
    );
  }

  return body as T;
}
