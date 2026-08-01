import { useEffect, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { leaguesApi } from '../api/leagues';
import { useTranslation } from '../i18n';
import type { FantasyTeam } from '../types/league';
import { errorMessage, validationDetails, type ErrorState } from '../utils/apiErrors';
import { fantasyTeamDetailKeys } from './useFantasyTeamDetail';

export function useFantasyTeamName(leagueId: string, fantasyTeamId: string, team?: FantasyTeam) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [name, setName] = useState(team?.name ?? '');
  const [error, setError] = useState<ErrorState | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => setName(team?.name ?? ''), [team?.name]);

  const mutation = useMutation({
    mutationFn: (nextName: string) =>
      leaguesApi.updateFantasyTeam(leagueId, fantasyTeamId, { name: nextName.trim() }),
    onMutate: () => {
      setError(null);
      setSuccess(null);
    },
    onSuccess: (response) => {
      queryClient.setQueryData(fantasyTeamDetailKeys.team(leagueId, fantasyTeamId), response);
      setName(response.data.name);
      setSuccess(t('fantasyTeams.update.success'));
    },
    onError: (cause) =>
      setError({
        details: validationDetails(cause),
        message: errorMessage(cause, t('fantasyTeams.errors.update'), t),
      }),
  });

  return {
    name,
    setName,
    error,
    success,
    isUpdating: mutation.isPending,
    update: () => mutation.mutate(name),
  };
}