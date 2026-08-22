import { useTranslation } from '../../i18n';
import type { ErrorState } from '../../utils/apiErrors';
import { AssignPlayerForm } from './AssignPlayerForm';

type FieldErrors = { player_id?: string; purchase_price?: string };
export function RosterManagementPanel({
  canManage,
  leagueId,
  isAssigning,
  error,
  fieldErrors,
  setFieldErrors,
  onAssign,
}: {
  canManage: boolean;
  leagueId: string;
  isAssigning: boolean;
  error: ErrorState | null;
  fieldErrors: FieldErrors;
  setFieldErrors: React.Dispatch<React.SetStateAction<FieldErrors>>;
  onAssign: (payload: { player_id: number; purchase_price: number }) => Promise<void>;
}) {
  const { t } = useTranslation();
  if (!canManage)
    return <p className="mt-4 text-sm text-slate-400">{t('roster.managementReadOnly')}</p>;
  return (
    <AssignPlayerForm
      leagueId={leagueId}
      isAssigning={isAssigning}
      error={error}
      fieldErrors={fieldErrors}
      setFieldErrors={setFieldErrors}
      onAssign={onAssign}
    />
  );
}