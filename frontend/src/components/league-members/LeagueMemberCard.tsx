import type { LeagueMember, ManageableLeagueRole } from '../../types/league';

type Props = {
  member: LeagueMember;
  role: string;
  canRemove: boolean;
  canChangeRole: boolean;
  promoteLabel: string;
  revokeLabel: string;
  removeLabel: string;
  onRemove: () => void;
  onRoleChange: (role: ManageableLeagueRole) => void;
};
export function LeagueMemberCard({
  member,
  role,
  canRemove,
  canChangeRole,
  promoteLabel,
  revokeLabel,
  removeLabel,
  onRemove,
  onRoleChange,
}: Props) {
  return (
    <div className="rounded-xl border border-theme-border bg-theme-background/60 p-4">
      <p className="font-semibold text-theme-text">{member.name}</p>
      <p className="mt-1 text-sm text-theme-muted">{role}</p>
      {canRemove || canChangeRole ? (
        <div className="mt-4 flex flex-wrap gap-2">
          {canChangeRole ? (
            <button
              className="rounded-lg border border-cyan-500/50 px-3 py-2 text-sm font-semibold text-cyan-100 hover:bg-cyan-950/60"
              onClick={() =>
                onRoleChange(member.role.key === 'participant' ? 'co_commissioner' : 'participant')
              }
              type="button"
            >
              {member.role.key === 'participant' ? promoteLabel : revokeLabel}
            </button>
          ) : null}
          {canRemove ? (
            <button
              className="rounded-lg border border-red-500/50 px-3 py-2 text-sm font-semibold text-red-100 hover:bg-red-950/60"
              onClick={onRemove}
              type="button"
            >
              {removeLabel}
            </button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
