import type { FormEvent } from 'react';
import { useTranslation } from '../../i18n';
export function FantasyTeamNameForm({
  name,
  isUpdating,
  success,
  error,
  onNameChange,
  onSubmit,
}: {
  name: string;
  isUpdating: boolean;
  success: string | null;
  error: React.ReactNode;
  onNameChange: (value: string) => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
  const { t } = useTranslation();
  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 className="text-2xl font-semibold text-white">{t('fantasyTeams.update.title')}</h2>
      <p className="mt-1 text-sm text-slate-300">{t('fantasyTeams.update.description')}</p>
      {success ? (
        <div className="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-950/30 p-4 text-sm text-emerald-100">
          {success}
        </div>
      ) : null}
      {error}
      <form className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]" onSubmit={onSubmit}>
        <label className="text-sm text-slate-300">
          {t('fantasyTeams.update.name')}
          <input
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            maxLength={100}
            onChange={(event) => onNameChange(event.target.value)}
            type="text"
            value={name}
          />
        </label>
        <button
          className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
          disabled={isUpdating}
          type="submit"
        >
          {isUpdating ? t('fantasyTeams.update.submitting') : t('fantasyTeams.update.submit')}
        </button>
      </form>
    </section>
  );
}