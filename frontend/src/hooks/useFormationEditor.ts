import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ApiError } from '../api/client';
import { formationsApi } from '../api/formations';
import { formationKeys } from '../api/queryKeys';
import type { FormationModule, PlayerRoleKey, RosterPlayer } from '../types/league';

import {
  emptyFormationDraft,
  formationDraftPayload,
  formationDraftValidation,
  formationToDraft,
  sameFormationDraft,
  type FormationDraft,
} from '../utils/formationDraft';

export function useFormationEditor({
  leagueId,
  fantasyTeamId,
  matchdayId,
  modules,
  roster,
  benchSize,
  benchRoleLimits,
}: {
  leagueId: string;
  fantasyTeamId: string;
  matchdayId: number;
  modules: FormationModule[];
  roster: RosterPlayer[];
  benchSize: number;
  benchRoleLimits: Record<PlayerRoleKey, number>;
}) {
  const queryClient = useQueryClient();
  const [draft, setDraft] = useState<FormationDraft>(emptyFormationDraft);
  const [savedDraft, setSavedDraft] = useState<FormationDraft>(emptyFormationDraft);
  const [success, setSuccess] = useState<'saved' | 'submitted' | null>(null);
  const [deadlineConflict, setDeadlineConflict] = useState(false);
  const hydrated = useRef(false);
  const queryKey = formationKeys.detail(leagueId, matchdayId, fantasyTeamId);
  const formationQuery = useQuery({
    queryKey,
    queryFn: async () => {
      try {
        return await formationsApi.show(leagueId, matchdayId, fantasyTeamId);
      } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
          return null;
        }

        throw error;
      }
    },
    retry: (count, error) => !(error instanceof ApiError && error.status < 500) && count < 2,
  });
  const formation = formationQuery.data?.data;

  useEffect(() => {
    if (formationQuery.isLoading || hydrated.current) return;
    const initial = formation ? formationToDraft(formation) : emptyFormationDraft;
    setDraft(initial);
    setSavedDraft(initial);
    hydrated.current = true;
  }, [formation, formationQuery.isLoading]);

  const validation = useMemo(
    () => formationDraftValidation(draft, modules, roster, benchSize, benchRoleLimits),
    [benchRoleLimits, benchSize, draft, modules, roster],
  );

  const updateDraft = (next: (current: FormationDraft) => FormationDraft) => {
    setSuccess(null);
    setDraft(next);
  };
  const handleSuccess = (
    response: Awaited<ReturnType<typeof formationsApi.save>>,
    state: 'saved' | 'submitted',
  ) => {
    const next = formationToDraft(response.data);
    queryClient.setQueryData(queryKey, response);
    setDraft(next);
    setSavedDraft(next);
    setSuccess(state);
  };
  const handleError = (error: unknown) => {
    if (
      error instanceof ApiError &&
      error.status === 409 &&
      error.code === 'lineup_deadline_passed'
    ) {
      setDeadlineConflict(true);
    }
  };
  const save = useMutation({
    mutationFn: () =>
      formationsApi.save(leagueId, matchdayId, fantasyTeamId, formationDraftPayload(draft)),
    onSuccess: (response) => handleSuccess(response, 'saved'),
    onError: handleError,
  });
  const submit = useMutation({
    mutationFn: () => formationsApi.submit(leagueId, matchdayId, fantasyTeamId),
    onSuccess: (response) => handleSuccess(response, 'submitted'),
    onError: handleError,
  });

  return {
    draft,
    formation,
    formationQuery,
    selectedModule: validation.module,
    starterCounts: validation.starterCounts,
    locallyValid: validation.valid,
    isDirty: !sameFormationDraft(draft, savedDraft),
    success,
    deadlineConflict,
    save,
    submit,
    selectModule: (id: number) => updateDraft((current) => ({ ...current, formationModuleId: id })),
    toggleStarter: (id: number) =>
      updateDraft((current) => {
        const selected = current.starters.includes(id);
        return {
          ...current,
          starters: selected
            ? current.starters.filter((value) => value !== id)
            : [...current.starters, id],
          bench: current.bench.filter((value) => value !== id),
        };
      }),
    toggleBench: (id: number) =>
      updateDraft((current) => ({
        ...current,
        bench: current.bench.includes(id)
          ? current.bench.filter((value) => value !== id)
          : [...current.bench, id],
        starters: current.starters.filter((value) => value !== id),
      })),
    moveBench: (index: number, direction: -1 | 1) =>
      updateDraft((current) => {
        const bench = [...current.bench];
        const target = index + direction;
        if (target < 0 || target >= bench.length) return current;
        [bench[index], bench[target]] = [bench[target], bench[index]];
        return { ...current, bench };
      }),
  };
}
