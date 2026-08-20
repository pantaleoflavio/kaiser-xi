import { ApiError } from '../api/client';

type Translate = (key: string, params?: Record<string, string | number | undefined>) => string;

export function championshipInitializationError(
  error: Error | null,
  t: Translate,
  options: { alreadyInitializedKey?: string } = {},
): string | null {
  if (!error) return null;
  if (!(error instanceof ApiError)) return t('championshipInitialization.errors.generic');

  if (error.code === 'championship_participants_missing_teams') {
    const count = error.missingTeamCount ?? 0;
    return t(
      count === 1
        ? 'championshipInitialization.errors.missingTeam'
        : 'championshipInitialization.errors.missingTeams',
      {
        count,
      },
    );
  }
  if (error.status === 403) return t('championshipInitialization.errors.unauthorized');
  if (error.status === 422) return t('championshipInitialization.errors.invalidMatchday');
  if (error.status === 409 && options.alreadyInitializedKey) {
    return t(options.alreadyInitializedKey);
  }

  return t('championshipInitialization.errors.generic');
}
