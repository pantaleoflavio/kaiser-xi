import { ApiError } from '../../api/client';

export type FieldErrors = Record<string, string>;

export function fieldErrors(error: unknown): FieldErrors {
  if (!(error instanceof ApiError) || !error.errors) return {};
  return Object.fromEntries(
    Object.entries(error.errors).map(([field, messages]) => [field, messages[0] ?? '']),
  );
}

export function accountError(error: unknown, fallback: string) {
  if (error instanceof ApiError) {
    if (error.status === 401) return 'account.errors.authentication';
    if (error.status === 403) return 'account.errors.forbidden';
    if (error.status === 409) return 'account.errors.conflict';
    if (error.status === 422) return fallback;
  }

  return 'account.errors.generic';
}