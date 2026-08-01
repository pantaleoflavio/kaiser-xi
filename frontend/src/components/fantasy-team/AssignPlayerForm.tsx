import { useState, type FormEvent } from 'react';
import { EligiblePlayerSelector } from '../EligiblePlayerSelector';
import { ErrorPanel } from '../feedback/ErrorPanel';
import { useTranslation } from '../../i18n';
import type { EligiblePlayer } from '../../types/league';
import type { ErrorState } from '../../utils/apiErrors';

type FieldErrors = { player_id?: string; purchase_price?: string };

export function AssignPlayerForm({
  leagueId,
  isAssigning,
  error,
  fieldErrors,
  setFieldErrors,
  onAssign,
}: {
  leagueId: string;
  isAssigning: boolean;
  error: ErrorState | null;
  fieldErrors: FieldErrors;
  setFieldErrors: React.Dispatch<React.SetStateAction<FieldErrors>>;
  onAssign: (payload: { player_id: number; purchase_price: number }) => Promise<void>;
}) {
  const { t } = useTranslation();
  const [selected, setSelected] = useState<EligiblePlayer | null>(null);
  const [purchasePrice, setPurchasePrice] = useState('');
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!selected) {
      setFieldErrors({ player_id: t('roster.assign.playerRequired') });
      return;
    }
    if (
      purchasePrice === '' ||
      Number(purchasePrice) < 0 ||
      !Number.isInteger(Number(purchasePrice))
    ) {
      setFieldErrors({ purchase_price: t('roster.assign.priceRequired') });
      return;
    }
    try {
      await onAssign({ player_id: selected.id, purchase_price: Number(purchasePrice) });
      setSelected(null);
      setPurchasePrice('');
    } catch {
      /* The mutation hook maps and exposes API errors. */
    }
  };
  return (
    <form className="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4" onSubmit={submit}>
      <h3 className="text-lg font-semibold text-white">{t('roster.assign.title')}</h3>
      {error ? (
        <div className="mt-4">
          <ErrorPanel error={error} title={t('roster.errors.assignTitle')} />
        </div>
      ) : null}
      <div className="mt-4">
        <EligiblePlayerSelector
          disabled={isAssigning}
          error={fieldErrors.player_id}
          leagueId={leagueId}
          onSelect={(player) => {
            setSelected(player);
            setFieldErrors((current) => ({ ...current, player_id: undefined }));
          }}
          selected={selected}
        />
      </div>
      <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
        <label className="text-sm text-slate-300" htmlFor="purchase-price">
          {t('roster.assign.purchasePrice')}
          <input
            aria-describedby={fieldErrors.purchase_price ? 'purchase-price-error' : undefined}
            aria-invalid={Boolean(fieldErrors.purchase_price)}
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            disabled={isAssigning}
            id="purchase-price"
            min="0"
            onChange={(e) => {
              setPurchasePrice(e.target.value);
              setFieldErrors((current) => ({ ...current, purchase_price: undefined }));
            }}
            step="1"
            type="number"
            value={purchasePrice}
          />
          {fieldErrors.purchase_price ? (
            <span className="mt-1 block text-red-200" id="purchase-price-error" role="alert">
              {fieldErrors.purchase_price}
            </span>
          ) : null}
        </label>
        <button
          className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          disabled={isAssigning || !selected}
          type="submit"
        >
          {isAssigning ? t('roster.assign.submitting') : t('roster.assign.submit')}
        </button>
      </div>
    </form>
  );
}