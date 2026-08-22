import type { RosterPlayer } from '../../types/league';
import type { ErrorState } from '../../utils/apiErrors';
import { useTranslation } from '../../i18n';
import { ErrorPanel } from '../feedback/ErrorPanel';
import { FantasyRosterList } from './FantasyRosterList';
import { RosterManagementPanel } from './RosterManagementPanel';

type FieldErrors = { player_id?: string; purchase_price?: string };
export function FantasyRosterSection({
  leagueId,
  players,
  canManage,
  success,
  error,
  assignError,
  fieldErrors,
  setFieldErrors,
  isAssigning,
  releasingPlayerId,
  onAssign,
  onRelease,
}: {
  leagueId: string;
  players: RosterPlayer[];
  canManage: boolean;
  success: string | null;
  error: ErrorState | null;
  assignError: ErrorState | null;
  fieldErrors: FieldErrors;
  setFieldErrors: React.Dispatch<React.SetStateAction<FieldErrors>>;
  isAssigning: boolean;
  releasingPlayerId: number | null;
  onAssign: (payload: { player_id: number; purchase_price: number }) => Promise<void>;
  onRelease: (player: RosterPlayer) => void;
}) {
  const { t } = useTranslation();
  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 className="text-2xl font-semibold text-white">{t('roster.title')}</h2>
      <p className="mt-1 text-sm text-slate-300">{t('roster.description')}</p>
      {success ? (
        <p
          className="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-950/30 p-4 text-sm text-emerald-100"
          role="status"
        >
          {success}
        </p>
      ) : null}
      {error ? (
        <div className="mt-4">
          <ErrorPanel error={error} title={t('roster.errors.title')} />
        </div>
      ) : null}
      {!error ? (
        <FantasyRosterList
          players={players}
          canManage={canManage}
          releasingPlayerId={releasingPlayerId}
          onRelease={onRelease}
        />
      ) : null}
      <RosterManagementPanel
        canManage={canManage}
        leagueId={leagueId}
        isAssigning={isAssigning}
        error={assignError}
        fieldErrors={fieldErrors}
        setFieldErrors={setFieldErrors}
        onAssign={onAssign}
      />
    </section>
  );
}