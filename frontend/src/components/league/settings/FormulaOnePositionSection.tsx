import { settingsInputClass, type SettingsFieldErrors } from './leagueSettingsForm';
import { useTranslation } from '../../../i18n';

export function FormulaOnePositionPointsEditor({
  values,
  disabled,
  errors,
  onChange,
}: {
  values: Record<string, string>;
  disabled: boolean;
  errors: SettingsFieldErrors;
  onChange: (values: Record<string, string>) => void;
}) {
  const { t } = useTranslation();
  return (
    <fieldset className="rounded-xl border border-slate-700 p-4">
      <legend className="px-2 font-semibold text-white">{t('formulaOne.positionPoints')}</legend>
      <p className="mb-3 text-sm text-slate-400">{t('formulaOne.positionPointsHelp')}</p>
      <div className="space-y-2">
        {Object.entries(values)
          .sort(([a], [b]) => Number(a) - Number(b))
          .map(([position, value]) => (
            <label
              className="grid grid-cols-[8rem_1fr] items-center gap-3 text-slate-200"
              key={position}
            >
              <span>{t('formulaOne.positionLabel', { position })}</span>
              <input
                className={settingsInputClass}
                disabled={disabled}
                min="0"
                step="1"
                type="number"
                value={value}
                onChange={(event) => onChange({ ...values, [position]: event.target.value })}
              />
            </label>
          ))}
      </div>
      {errors.formula_one_position_points ? (
        <p className="mt-2 text-sm text-red-300">{errors.formula_one_position_points[0]}</p>
      ) : null}
      <div className="mt-3 flex gap-2">
        <button
          className="rounded bg-slate-700 px-3 py-2 text-white"
          disabled={disabled}
          type="button"
          onClick={() => onChange({ ...values, [String(Object.keys(values).length + 1)]: '0' })}
        >
          {t('formulaOne.addPosition')}
        </button>
        <button
          className="rounded bg-slate-700 px-3 py-2 text-white disabled:opacity-50"
          disabled={disabled || Object.keys(values).length <= 1}
          type="button"
          onClick={() => {
            const next = { ...values };
            delete next[String(Object.keys(values).length)];
            onChange(next);
          }}
        >
          {t('formulaOne.removePosition')}
        </button>
      </div>
    </fieldset>
  );
}
