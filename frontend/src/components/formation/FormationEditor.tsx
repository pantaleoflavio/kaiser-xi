import { BenchSelectionSection } from './BenchSelectionSection';
import { FormationActions } from './FormationActions';
import { FormationModuleSelector } from './FormationModuleSelector';
import { StarterSelectionSection } from './StarterSelectionSection';
import { useFormationEditor } from '../../hooks/useFormationEditor';
import { useTranslation } from '../../i18n';
import type { LeagueSettings, RosterPlayer } from '../../types/league';
import { ApiError } from '../../api/client';

const fieldError = (error: unknown, fields: string[], fallback: string) =>
  error instanceof ApiError &&
  error.status === 422 &&
  Object.keys(error.errors ?? {}).some((field) => fields.some((name) => field.startsWith(name)))
    ? fallback
    : undefined;

export function FormationEditor({
  leagueId,
  fantasyTeamId,
  matchdayId,
  settings,
  roster,
  locked,
}: {
  leagueId: string;
  fantasyTeamId: string;
  matchdayId: number;
  settings: LeagueSettings;
  roster: RosterPlayer[];
  locked: boolean;
}) {
  const { t } = useTranslation();
  const editor = useFormationEditor({
    leagueId,
    fantasyTeamId,
    matchdayId,
    modules: settings.allowed_formation_modules,
    roster,
    benchSize: settings.bench_size,
    benchRoleLimits: settings.bench_role_limits,
  });
  const readOnly = locked || editor.deadlineConflict;
  const mutationError = editor.save.error ?? editor.submit.error;
  const formationMissing =
    editor.formationQuery.error instanceof ApiError && editor.formationQuery.error.status === 404;
  const generalError =
    mutationError instanceof ApiError
      ? mutationError.status === 409 && mutationError.code === 'lineup_deadline_passed'
        ? t('formation.errors.deadline')
        : mutationError.status === 403
          ? t('common.errors.forbidden')
          : mutationError.status === 404
            ? t('common.errors.notFound')
            : mutationError.status === 422
              ? t('formation.errors.invalid')
              : t('common.errors.unexpected')
      : editor.formationQuery.error && !formationMissing
        ? t('formation.errors.load')
        : null;
  const activeRoster = roster.filter((item) => !item.released_at);

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 className="text-2xl font-semibold text-white">{t('formation.title')}</h2>
      <p className="mt-1 text-sm text-slate-300">{t('formation.description')}</p>
      {formationMissing && !editor.formationQuery.isLoading ? (
        <p className="mt-4 text-slate-400">{t('formation.noneYet')}</p>
      ) : null}
      {editor.formation?.submitted ? (
        <p className="mt-4 rounded-lg bg-emerald-950/50 p-3 text-emerald-200" role="status">
          {t('formation.submitted')}
        </p>
      ) : null}
      {readOnly ? (
        <p className="mt-4 rounded-lg bg-amber-950/40 p-3 text-amber-200" role="status">
          {t('formation.locked')}
        </p>
      ) : null}
      {editor.success ? (
        <p className="mt-4 text-emerald-300" role="status">
          {t(`formation.success.${editor.success}`)}
        </p>
      ) : null}
      {generalError ? (
        <p className="mt-4 text-red-300" role="alert">
          {generalError}
        </p>
      ) : null}
      {!editor.formationQuery.isLoading ? (
        <div className="mt-6 space-y-7">
          <FormationModuleSelector
            disabled={readOnly}
            error={fieldError(mutationError, ['formation_module_id'], t('formation.errors.module'))}
            modules={settings.allowed_formation_modules}
            onSelect={editor.selectModule}
            selectedId={editor.draft.formationModuleId}
          />
          <StarterSelectionSection
            counts={editor.starterCounts}
            disabled={readOnly}
            error={fieldError(
              mutationError,
              ['starters', 'players'],
              t('formation.errors.starters'),
            )}
            module={editor.selectedModule}
            onToggle={editor.toggleStarter}
            roster={activeRoster}
            selected={editor.draft.starters}
          />
          <BenchSelectionSection
            benchSize={settings.bench_size}
            disabled={readOnly}
            error={fieldError(mutationError, ['bench', 'players'], t('formation.errors.bench'))}
            onMove={editor.moveBench}
            onToggle={editor.toggleBench}
            roleLimits={settings.bench_role_limits}
            roster={activeRoster}
            selected={editor.draft.bench}
            starters={editor.draft.starters}
          />
          {!readOnly ? (
            <FormationActions
              canSubmit={Boolean(editor.formation) && editor.locallyValid}
              isDirty={editor.isDirty}
              isSaving={editor.save.isPending}
              isSubmitting={editor.submit.isPending}
              onSave={() => editor.save.mutate()}
              onSubmit={() => editor.submit.mutate()}
            />
          ) : null}
        </div>
      ) : null}
    </section>
  );
}
