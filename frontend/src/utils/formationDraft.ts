import type { Formation, FormationSavePayload } from '../types/formation';
import type { FormationModule, PlayerRoleKey, RosterPlayer } from '../types/league';

export type FormationDraft = {
  formationModuleId: number | null;
  starters: number[];
  bench: number[];
};

export const emptyFormationDraft: FormationDraft = {
  formationModuleId: null,
  starters: [],
  bench: [],
};

export const formationToDraft = (formation: Formation): FormationDraft => ({
  formationModuleId: formation.formation_module.id,
  starters: formation.starters.map((player) => player.fantasy_team_player_id),
  bench: formation.bench.map((player) => player.fantasy_team_player_id),
});

export const formationDraftPayload = (draft: FormationDraft): FormationSavePayload => ({
  formation_module_id: draft.formationModuleId!,
  starters: draft.starters,
  bench: draft.bench.map((id, index) => ({ fantasy_team_player_id: id, order: index + 1 })),
});

export function formationDraftValidation(
  draft: FormationDraft,
  modules: FormationModule[],
  roster: RosterPlayer[],
  benchSize: number,
  benchRoleLimits: Record<PlayerRoleKey, number>,
) {
  const module = modules.find((item) => item.id === draft.formationModuleId) ?? null;
  const rosterById = new Map(roster.map((player) => [player.id, player]));
  const starterCounts = draft.starters.reduce<Partial<Record<PlayerRoleKey, number>>>(
    (counts, id) => {
      const role = rosterById.get(id)?.player.role as PlayerRoleKey | null;
      if (role) counts[role] = (counts[role] ?? 0) + 1;
      return counts;
    },
    {},
  );
  const valid = Boolean(
    module &&
    Object.entries(module.requirements).every(
      ([role, required]) => starterCounts[role as PlayerRoleKey] === required,
    ) &&
    draft.bench.length <= benchSize &&
    Object.entries(benchRoleLimits).every(
      ([role, limit]) =>
        draft.bench.filter((id) => rosterById.get(id)?.player.role === role).length <= limit,
    ),
  );
  return { module, starterCounts, valid };
}

export const sameFormationDraft = (left: FormationDraft, right: FormationDraft) =>
  JSON.stringify(left) === JSON.stringify(right);
