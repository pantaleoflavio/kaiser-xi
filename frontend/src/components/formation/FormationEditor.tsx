import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ApiError } from '../../api/client';
import { formationsApi } from '../../api/formations';
import { formationKeys } from '../../api/queryKeys';
import { useFormationEditor } from '../../hooks/useFormationEditor';
import { useTranslation } from '../../i18n';
import type { LeagueSettings, RosterPlayer } from '../../types/league';
import { formatDate } from '../../utils/formatters';
import { CaptainSelectionSection } from './CaptainSelectionSection';
import { BenchSelectionSection } from './BenchSelectionSection';
import { FormationModuleSelector } from './FormationModuleSelector';
import { StarterSelectionSection } from './StarterSelectionSection';

const fieldError = (error: unknown, fields: string[], fallback: string) =>
  error instanceof ApiError &&
  error.status === 422 &&
  Object.keys(error.errors ?? {}).some((field) => fields.some((name) => field.startsWith(name)))
    ? fallback
    : undefined;

export function FormationEditor({
  leagueId,
  fantasyTeamId,
  settings,
  roster,
}: {
  leagueId: string;
  fantasyTeamId: string;
  settings: LeagueSettings;
  roster: RosterPlayer[];
}) {
  const { language, t } = useTranslation();
  const matchdaysQuery = useQuery({
    queryKey: formationKeys.matchdays(leagueId),
    queryFn: () => formationsApi.matchdays(leagueId),
    retry: (count, error) => !(error instanceof ApiError && error.status < 500) && count < 2,
  });
  const matchdays = matchdaysQuery.data?.data ?? [];
  const [matchdayId, setMatchdayId] = useState<number | null>(null);
  useEffect(() => {
    if (matchdayId === null && matchdays.length) {
      setMatchdayId(
        matchdays.find((item) => new Date(item.deadline).getTime() > Date.now())?.id ??
          matchdays[matchdays.length - 1].id,
      );
    }
  }, [matchdayId, matchdays]);
  const matchday = matchdays.find((item) => item.id === matchdayId);
  const editor = useFormationEditor({
    leagueId,
    fantasyTeamId,
    matchdayId,
    modules: settings.allowed_formation_modules,
    roster,
    benchSize: settings.bench_size,
    benchRoleLimits: settings.bench_role_limits,
  });
  const deadlinePassed = Boolean(matchday && Date.now() >= new Date(matchday.deadline).getTime());
  const readOnly = deadlinePassed || editor.deadlineConflict;
  const mutationError = editor.save.error ?? editor.submit.error;
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
      : null;
  const formationMissing =
    editor.formationQuery.error instanceof ApiError && editor.formationQuery.error.status === 404;
  const loadError =
    editor.formationQuery.error && !formationMissing ? t('formation.errors.load') : null;

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 className="text-2xl font-semibold text-white">{t('formation.title')}</h2>
      <p className="mt-1 text-sm text-slate-300">{t('formation.description')}</p>
      {matchdaysQuery.isError ? (
        <p className="mt-4 text-red-300" role="alert">
          {t('formation.errors.matchdays')}
        </p>
      ) : null}
      {matchdays.length ? (
        <div className="mt-5">
          <label className="font-semibold text-white" htmlFor="formation-matchday">
            {t('formation.matchday')}
          </label>
          <select
            className="mt-2 w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-white"
            id="formation-matchday"
            onChange={(event) => setMatchdayId(Number(event.target.value))}
            value={matchdayId ?? ''}
          >
            {matchdays.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name || t('formation.matchdayNumber', { number: item.number })}
              </option>
            ))}
          </select>
          {matchday ? (
            <div className="mt-3 rounded-lg bg-slate-950/60 p-3 text-sm text-slate-300">
              <p>
                {t('formation.deadline')}:{' '}
                {formatDate(matchday.deadline, t('leagueDetail.notAvailable'), language)}
              </p>
              <p className={readOnly ? 'text-amber-300' : 'text-emerald-300'}>
                {readOnly ? t('formation.locked') : t('formation.editable')}
              </p>
            </div>
          ) : null}
        </div>
      ) : matchdaysQuery.isSuccess ? (
        <p className="mt-4 text-slate-300">{t('formation.noMatchdays')}</p>
      ) : null}
      {loadError ? (
        <p className="mt-4 text-red-300" role="alert">
          {loadError}
        </p>
      ) : null}
      {formationMissing && !editor.formationQuery.isLoading ? (
        <p className="mt-4 text-slate-400">{t('formation.noneYet')}</p>
      ) : null}
      {editor.formation?.submitted ? (
        <p className="mt-4 rounded-lg bg-emerald-950/50 p-3 text-emerald-200" role="status">
          {t('formation.submitted')}
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
      {matchdayId !== null && !editor.formationQuery.isLoading ? (
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
            roster={roster.filter((item) => !item.released_at)}
            selected={editor.draft.starters}
          />
          <BenchSelectionSection
            benchSize={settings.bench_size}
            disabled={readOnly}
            error={fieldError(mutationError, ['bench', 'players'], t('formation.errors.bench'))}
            onMove={editor.moveBench}
            onToggle={editor.toggleBench}
            roleLimits={settings.bench_role_limits}
            roster={roster.filter((item) => !item.released_at)}
            selected={editor.draft.bench}
            starters={editor.draft.starters}
          />
          {settings.captain_enabled ? (
            <CaptainSelectionSection
              disabled={readOnly}
              error={fieldError(
                mutationError,
                ['captain_fantasy_team_player_id'],
                t('formation.errors.captain'),
              )}
              onSelect={editor.selectCaptain}
              roster={roster}
              selectedId={editor.draft.captainId}
              starters={editor.draft.starters}
            />
          ) : null}
          {!readOnly ? (
            <div className="flex flex-wrap gap-3">
              <button
                className="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-50"
                disabled={!editor.locallyValid || editor.save.isPending}
                onClick={() => editor.save.mutate()}
                type="button"
              >
                {editor.save.isPending ? t('formation.saving') : t('formation.save')}
              </button>
              <button
                className="rounded-lg border border-emerald-400 px-4 py-2 font-semibold text-emerald-200 disabled:opacity-50"
                disabled={!editor.formation || editor.submit.isPending}
                onClick={() => editor.submit.mutate()}
                type="button"
              >
                {editor.submit.isPending ? t('formation.submitting') : t('formation.submit')}
              </button>
            </div>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}
