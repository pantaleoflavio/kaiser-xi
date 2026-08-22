import { ApiError } from '../api/client';

export type ErrorState = { message: string; details: string[] };
export type Translate = (key: string) => string;

export function validationDetails(error: unknown) {
  if (!(error instanceof ApiError) || !error.errors) return [];
  return Object.values(error.errors).flat();
}

export function errorMessage(error: unknown, fallback: string, t: Translate) {
  if (error instanceof ApiError) {
    if (error.status === 403) return t('common.errors.forbidden');
    if (error.status === 404) return t('common.errors.notFound');
    if (error.status === 409) return t('common.errors.conflict');
    if (error.status === 422) return t('common.errors.validation');
    return t('common.errors.unexpected');
  }
  return error instanceof Error ? t('common.errors.unexpected') : fallback;
}

export function assignmentErrorMessage(error: unknown, fallback: string, t: Translate) {
  if (error instanceof ApiError && error.status === 409) {
    const message = error.message.toLowerCase();
    if (message.includes('budget')) return t('roster.errors.insufficientBudget');
    if (message.includes('assign')) return t('roster.errors.alreadyAssigned');
  }
  return errorMessage(error, fallback, t);
}