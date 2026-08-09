import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ApiError } from '../api/client';
import { formationsApi } from '../api/formations';
import { formationKeys } from '../api/queryKeys';
import type { Formation, FormationSavePayload } from '../types/formation';
import type { FormationModule, PlayerRoleKey, RosterPlayer } from '../types/league';

export type FormationDraft = {
  formationModuleId: number | null;
  starters: number[];
  bench: number[];
  captainId: number | null;
};

const emptyDraft: FormationDraft = {
  formationModuleId: null,
  starters: [],
  bench: [],
  captainId: null,
};

function draftFromFormation(formation: Formation): FormationDraft {
  return {
    formationModuleId: formation.formation_module.id,
    starters: formation.starters.map((player) => player.fantasy_team_player_id),
    bench: formation.bench.map((player) => player.fantasy_team_player_id),
    captainId: formation.captain_fantasy_team_player_id,
  };
}

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
  matchdayId: number | null;
  modules: FormationModule[];
  roster: RosterPlayer[];
  benchSize: number;
  benchRoleLimits: Record<PlayerRoleKey, number>;
}) {
  const queryClient = useQueryClient();
  const [draft, setDraft] = useState<FormationDraft>(emptyDraft);
  const [success, setSuccess] = useState<'saved' | 'submitted' | null>(null);
  const [deadlineConflict, setDeadlineConflict] = useState(false);
  const hydratedMatchday = useRef<number | null>(null);
  const queryKey = formationKeys.detail(leagueId, matchdayId ?? '', fantasyTeamId);
  const formationQuery = useQuery({
    queryKey,
    queryFn: () => formationsApi.show(leagueId, matchdayId!, fantasyTeamId),
    enabled: matchdayId !== null,
    retry: (count, error) => !(error instanceof ApiError && error.status < 500) && count < 2,
  });
  const formation = formationQuery.data?.data;

  useEffect(() => {
    if (matchdayId === null || formationQuery.isLoading || hydratedMatchday.current === matchdayId)
      return;
    setDraft(formation ? draftFromFormation(formation) : emptyDraft);
    setSuccess(null);
    setDeadlineConflict(false);
    hydratedMatchday.current = matchdayId;
  }, [formation, formationQuery.isLoading, matchdayId]);

  const selectedModule = modules.find((module) => module.id === draft.formationModuleId) ?? null;
  const rosterById = useMemo(() => new Map(roster.map((player) => [player.id, player])), [roster]);
  const starterCounts = useMemo(
    () =>
      draft.starters.reduce<Partial<Record<PlayerRoleKey, number>>>((counts, id) => {
        const role = rosterById.get(id)?.player.role as PlayerRoleKey | null;
        if (role) counts[role] = (counts[role] ?? 0) + 1;
        return counts;
      }, {}),
    [draft.starters, rosterById],
  );
  const locallyValid = Boolean(
    selectedModule &&
    Object.entries(selectedModule.requirements).every(
      ([role, required]) => starterCounts[role as PlayerRoleKey] === required,
    ) &&
    draft.bench.length <= benchSize &&
    Object.entries(benchRoleLimits).every(
      ([role, limit]) =>
        draft.bench.filter((id) => rosterById.get(id)?.player.role === role).length <= limit,
    ) &&
    (draft.captainId === null || draft.starters.includes(draft.captainId)),
  );

  const updateDraft = (next: (current: FormationDraft) => FormationDraft) => {
    setSuccess(null);
    setDraft(next);
  };
  const payload = (): FormationSavePayload => ({
    formation_module_id: draft.formationModuleId!,
    starters: draft.starters,
    bench: draft.bench.map((id, index) => ({ fantasy_team_player_id: id, order: index + 1 })),
    captain_fantasy_team_player_id: draft.captainId,
  });
  const handleError = (error: unknown) => {
    if (
      error instanceof ApiError &&
      error.status === 409 &&
      error.code === 'lineup_deadline_passed'
    )
      setDeadlineConflict(true);
  };
  const save = useMutation({
    mutationFn: () => formationsApi.save(leagueId, matchdayId!, fantasyTeamId, payload()),
    onSuccess: (response) => {
      queryClient.setQueryData(queryKey, response);
      setDraft(draftFromFormation(response.data));
      setSuccess('saved');
    },
    onError: handleError,
  });
  const submit = useMutation({
    mutationFn: () => formationsApi.submit(leagueId, matchdayId!, fantasyTeamId),
    onSuccess: (response) => {
      queryClient.setQueryData(queryKey, response);
      setDraft(draftFromFormation(response.data));
      setSuccess('submitted');
    },
    onError: handleError,
  });

  return {
    draft,
    formation,
    formationQuery,
    selectedModule,
    starterCounts,
    locallyValid,
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
          captainId: selected && current.captainId === id ? null : current.captainId,
        };
      }),
    toggleBench: (id: number) =>
      updateDraft((current) => ({
        ...current,
        bench: current.bench.includes(id)
          ? current.bench.filter((value) => value !== id)
          : [...current.bench, id],
        starters: current.starters.filter((value) => value !== id),
        captainId: current.captainId === id ? null : current.captainId,
      })),
    moveBench: (index: number, direction: -1 | 1) =>
      updateDraft((current) => {
        const bench = [...current.bench];
        const target = index + direction;
        if (target < 0 || target >= bench.length) return current;
        [bench[index], bench[target]] = [bench[target], bench[index]];
        return { ...current, bench };
      }),
    selectCaptain: (id: number | null) => updateDraft((current) => ({ ...current, captainId: id })),
  };
}
