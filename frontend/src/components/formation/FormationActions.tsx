import { useTranslation } from '../../i18n';

export function FormationActions({
  canSubmit,
  isDirty,
  isSaving,
  isSubmitting,
  onSave,
  onSubmit,
}: {
  canSubmit: boolean;
  isDirty: boolean;
  isSaving: boolean;
  isSubmitting: boolean;
  onSave: () => void;
  onSubmit: () => void;
}) {
  const { t } = useTranslation();
  const pending = isSaving || isSubmitting;
  return (
    <div className="space-y-3">
      <p className={isDirty ? 'text-amber-300' : 'text-slate-400'} role="status">
        {isDirty ? t('formation.unsaved') : t('formation.draftSaved')}
      </p>
      <div className="flex flex-wrap gap-3">
        <button
          className="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-50"
          disabled={!isDirty || pending}
          onClick={onSave}
          type="button"
        >
          {isSaving ? t('formation.saving') : t('formation.save')}
        </button>
        <button
          className="rounded-lg border border-emerald-400 px-4 py-2 font-semibold text-emerald-200 disabled:opacity-50"
          disabled={!canSubmit || isDirty || pending}
          onClick={onSubmit}
          type="button"
        >
          {isSubmitting ? t('formation.submitting') : t('formation.submit')}
        </button>
      </div>
    </div>
  );
}
