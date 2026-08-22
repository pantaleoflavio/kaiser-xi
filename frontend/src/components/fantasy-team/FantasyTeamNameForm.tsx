import type { SubmitEvent  } from 'react';
import { useTranslation } from '../../i18n';
export function FantasyTeamNameForm({
  name,
  isEditing,
  isUpdating,
  fieldError,
  success,
  error,
  onCancel,
  onEdit,
  onNameChange,
  onSubmit,
}: {
  name: string;
  isEditing: boolean;
  isUpdating: boolean;
  fieldError: string | null;
  success: string | null;
  error: React.ReactNode;
  onCancel: () => void;
  onEdit: () => void;
  onNameChange: (value: string) => void;
  onSubmit: (event: SubmitEvent<HTMLFormElement>) => void;
}) {
  const { t } = useTranslation();
  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-semibold text-white">{t('fantasyTeams.update.title')}</h2>
          <p className="mt-1 text-sm text-slate-300">{t('fantasyTeams.update.description')}</p>
        </div>
        {!isEditing ? (
          <button
            className="rounded-lg border border-emerald-400 px-4 py-2 font-semibold text-emerald-200"
            onClick={onEdit}
            type="button"
          >
            {t('fantasyTeams.update.edit')}
          </button>
        ) : null}
      </div>
      {success ? (
        <div className="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-950/30 p-4 text-sm text-emerald-100">
          {success}
        </div>
      ) : null}
      {error}
      {isEditing ? (
        <form className="mt-4 grid gap-3" onSubmit={onSubmit}>
          <label className="text-sm text-slate-300">
            {t('fantasyTeams.update.name')}
            <input
              className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
              maxLength={100}
              onChange={(event) => onNameChange(event.target.value)}
              type="text"
              value={name}
            />
            {fieldError ? (
              <span className="mt-1 block text-sm text-red-200" role="alert">
                {fieldError}
              </span>
            ) : null}
          </label>
          <div className="flex justify-end gap-3">
            <button
              className="rounded-lg border border-slate-600 px-4 py-2 font-semibold text-slate-200 disabled:opacity-60"
              disabled={isUpdating}
              onClick={onCancel}
              type="button"
            >
              {t('fantasyTeams.update.cancel')}
            </button>
            <button
              className="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
              disabled={isUpdating}
              type="submit"
            >
              {isUpdating ? t('fantasyTeams.update.submitting') : t('fantasyTeams.update.submit')}
            </button>
          </div>
        </form>
      ) : null}
    </section>
  );
}