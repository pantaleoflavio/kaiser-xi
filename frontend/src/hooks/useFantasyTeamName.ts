import { useEffect, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { leaguesApi } from '../api/leagues';
import { ApiError } from '../api/client';
import { useTranslation } from '../i18n';
import type { FantasyTeam } from '../types/league';
import { errorMessage, validationDetails, type ErrorState } from '../utils/apiErrors';
import { fantasyTeamDetailKeys } from './useFantasyTeamDetail';

export function useFantasyTeamName(leagueId: string, fantasyTeamId: string, team?: FantasyTeam) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [name, setName] = useState(team?.name ?? '');
  const [isEditing, setIsEditing] = useState(false);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [error, setError] = useState<ErrorState | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => setName(team?.name ?? ''), [team?.name]);

  const mutation = useMutation({
    mutationFn: (nextName: string) =>
      leaguesApi.updateFantasyTeam(leagueId, fantasyTeamId, { name: nextName.trim() }),
    onMutate: () => {
      setError(null);
      setFieldError(null);
      setSuccess(null);
    },
    onSuccess: (response) => {
      queryClient.setQueryData(fantasyTeamDetailKeys.team(leagueId, fantasyTeamId), response);
      void queryClient.invalidateQueries({
        queryKey: fantasyTeamDetailKeys.team(leagueId, fantasyTeamId),
      });
      setName(response.data.name);
      setIsEditing(false);
      setSuccess(t('fantasyTeams.update.success'));
    },
    onError: (cause) => {
      if (cause instanceof ApiError && cause.status === 422 && cause.errors?.name?.[0]) {
        setFieldError(cause.errors.name[0]);
        return;
      }
      setError({
        details: validationDetails(cause),
        message:
          cause instanceof ApiError && cause.status === 403
            ? t('fantasyTeams.update.forbidden')
            : errorMessage(cause, t('fantasyTeams.errors.update'), t),
      });
    },
  });

  const update = () => {
    const trimmedName = name.trim();
    if (!trimmedName) {
      setFieldError(t('fantasyTeams.update.nameRequired'));
      return;
    }
    if (trimmedName.length > 100) {
      setFieldError(t('fantasyTeams.update.nameLength'));
      return;
    }
    mutation.mutate(trimmedName);
  };

  return {
    name,
    setName: (value: string) => {
      setName(value);
      setFieldError(null);
    },
    isEditing,
    fieldError,
    error,
    success,
    isUpdating: mutation.isPending,
    edit: () => {
      setName(team?.name ?? '');
      setError(null);
      setSuccess(null);
      setFieldError(null);
      setIsEditing(true);
    },
    cancel: () => {
      setName(team?.name ?? '');
      setError(null);
      setFieldError(null);
      setIsEditing(false);
    },
    update,
  };
}