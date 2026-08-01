import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';
import { useTranslation } from '../i18n';
import type { AssignPlayerPayload, LeagueSettings, RosterPlayer } from '../types/league';
import {
  assignmentErrorMessage,
  errorMessage,
  validationDetails,
  type ErrorState,
} from '../utils/apiErrors';
import { formatMoney } from '../utils/formatters';
import { eligiblePlayerKeys } from './useEligiblePlayers';
import { fantasyTeamDetailKeys } from './useFantasyTeamDetail';

type FieldErrors = { player_id?: string; purchase_price?: string };

export function useRosterManagement(
  leagueId: string,
  fantasyTeamId: string,
  canManage: boolean,
  settings?: LeagueSettings,
) {
  const { language, t } = useTranslation();
  const queryClient = useQueryClient();
  const [error, setError] = useState<ErrorState | null>(null);
  const [assignError, setAssignError] = useState<ErrorState | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [success, setSuccess] = useState<string | null>(null);
  const [releasingPlayerId, setReleasingPlayerId] = useState<number | null>(null);

  const refreshRosterData = async () =>
    Promise.all([
      queryClient.invalidateQueries({
        queryKey: fantasyTeamDetailKeys.roster(leagueId, fantasyTeamId),
      }),
      queryClient.invalidateQueries({
        queryKey: fantasyTeamDetailKeys.team(leagueId, fantasyTeamId),
      }),
      queryClient.invalidateQueries({ queryKey: eligiblePlayerKeys.league(leagueId) }),
    ]);

  const assignMutation = useMutation({
    mutationFn: (payload: AssignPlayerPayload) =>
      leaguesApi.assignPlayer(leagueId, fantasyTeamId, payload),
    onMutate: () => {
      setAssignError(null);
      setFieldErrors({});
      setSuccess(null);
    },
    onSuccess: async () => {
      await refreshRosterData();
      setSuccess(t('roster.assign.success'));
    },
    onError: (cause) => {
      const errors = cause instanceof ApiError ? cause.errors : undefined;
      setFieldErrors({
        player_id: errors?.player_id?.[0],
        purchase_price: errors?.purchase_price?.[0],
      });
      setAssignError({
        details: validationDetails(cause),
        message: assignmentErrorMessage(cause, t('roster.errors.assign'), t),
      });
    },
  });

  const releaseMutation = useMutation({
    mutationFn: (player: RosterPlayer) =>
      leaguesApi.releasePlayer(leagueId, fantasyTeamId, player.player.id),
    onMutate: (player) => {
      setReleasingPlayerId(player.player.id);
      setError(null);
      setSuccess(null);
    },
    onSuccess: async () => {
      await refreshRosterData();
      setSuccess(t('roster.release.success'));
    },
    onError: (cause) =>
      setError({
        details: validationDetails(cause),
        message: errorMessage(cause, t('roster.errors.release'), t),
      }),
    onSettled: () => setReleasingPlayerId(null),
  });

  const release = (player: RosterPlayer) => {
    if (!canManage) return;
    const name = player.player.name || t('roster.unknownPlayer');
    const percent = formatMoney(
      settings?.release_refund_percentage,
      t('leagueDetail.notAvailable'),
      language,
    );
    if (window.confirm(t('common.confirmations.releasePlayer', { name, percent })))
      releaseMutation.mutate(player);
  };

  return {
    error,
    assignError,
    fieldErrors,
    setFieldErrors,
    success,
    releasingPlayerId,
    isAssigning: assignMutation.isPending,
    assign: async (payload: AssignPlayerPayload) => {
      if (!canManage) return;
      await assignMutation.mutateAsync(payload);
    },
    release,
  };
}