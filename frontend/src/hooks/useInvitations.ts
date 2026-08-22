import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { invitationsApi } from '../api/invitations';
import { invitationKeys, leagueKeys } from '../api/queryKeys';

export function useInvitationInbox() {
  return useQuery({ queryKey: invitationKeys.inbox(), queryFn: invitationsApi.inbox });
}

export function useAcceptInvitation() {
  const client = useQueryClient();
  return useMutation({
    mutationKey: ['invitations', 'accept'],
    mutationFn: invitationsApi.accept,
    onSuccess: async () => {
      await Promise.all([
        client.invalidateQueries({ queryKey: invitationKeys.all }),
        client.invalidateQueries({ queryKey: leagueKeys.all }),
      ]);
    },
  });
}

export function useRejectInvitation() {
  const client = useQueryClient();
  return useMutation({
    mutationKey: ['invitations', 'reject'],
    mutationFn: invitationsApi.reject,
    onSuccess: () => client.invalidateQueries({ queryKey: invitationKeys.all }),
  });
}