import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { LeagueBasicInformationForm } from '../components/league-creation/LeagueBasicInformationForm';
import { LeagueInitialRulesForm } from '../components/league-creation/LeagueInitialRulesForm';
import { LoadingState } from '../components/LoadingState';
import { useTranslation } from '../i18n';
import type { League } from '../types/league';

const STORAGE_KEY = 'fantameister_created_league_id';
const errorsOf = (error: unknown) => error instanceof ApiError && error.status === 422 ? error.errors ?? {} : {};

export function CreateLeaguePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [createdLeague, setCreatedLeague] = useState<League | null>(null);
  const [createdId, setCreatedId] = useState<number | null>(() => { const value = sessionStorage.getItem(STORAGE_KEY); return value ? Number(value) : null; });
  const [notice, setNotice] = useState<string | null>(null);
  const [saveFailed, setSaveFailed] = useState(false);
  const resumedLeague = useQuery({ queryKey: leagueKeys.detail(createdId ?? ''), queryFn: () => leaguesApi.show(createdId!), enabled: createdId !== null && !createdLeague });
  const settings = useQuery({ queryKey: leagueKeys.settings(createdId ?? ''), queryFn: () => leaguesApi.settings(createdId!), enabled: createdId !== null });
  useEffect(() => { if (resumedLeague.data) setCreatedLeague(resumedLeague.data.data); }, [resumedLeague.data]);

  function statusMessage(error: unknown, action: 'create' | 'settings') {
    if (error instanceof ApiError) {
      if (error.status === 401) return t('leagueCreate.errors.authentication');
      if (error.status === 403) return t(`leagueCreate.errors.${action}Forbidden`);
      if (error.status === 404) return t('leagueCreate.errors.notFound');
      if (error.status === 409) return t('leagueCreate.errors.conflict');
      if (error.status === 422) return t('leagueCreate.errors.validation');
    }
    return t('leagueCreate.errors.unexpected');
  }
  const create = useMutation({ mutationFn: leaguesApi.create, onSuccess: async response => { const league = response.data; setCreatedLeague(league); setCreatedId(league.id); sessionStorage.setItem(STORAGE_KEY, String(league.id)); setNotice(league.my_role === 'commissioner' ? null : t('leagueCreate.errors.commissionerUnverified')); await queryClient.invalidateQueries({ queryKey: leagueKeys.lists() }); }, onError: error => setNotice(statusMessage(error, 'create')) });
  const save = useMutation({ mutationFn: (payload: Parameters<typeof leaguesApi.updateSettings>[1]) => leaguesApi.updateSettings(createdId!, payload), onSuccess: async () => { setSaveFailed(false); sessionStorage.removeItem(STORAGE_KEY); await Promise.all([queryClient.invalidateQueries({ queryKey: leagueKeys.lists() }), queryClient.invalidateQueries({ queryKey: leagueKeys.detail(createdId!) }), queryClient.invalidateQueries({ queryKey: leagueKeys.settings(createdId!) })]); navigate(`/leagues/${createdId}`, { replace: true, state: { success: t('leagueCreate.success.settings') } }); }, onError: error => { setSaveFailed(true); setNotice(statusMessage(error, 'settings')); } });
  const loadError = resumedLeague.error ?? settings.error;

  return <section className="mx-auto max-w-3xl space-y-6"><header><p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">{t('leagueCreate.eyebrow')}</p><h1 className="mt-2 text-3xl font-bold text-white sm:text-4xl">{t('leagueCreate.title')}</h1><p className="mt-3 text-slate-300">{t('leagueCreate.description')}</p></header><ol aria-label={t('leagueCreate.progress.label')} className="grid grid-cols-2 gap-2 text-sm"><li aria-current={!createdId ? 'step' : undefined} className={`rounded-lg border p-3 ${!createdId ? 'border-emerald-400 text-emerald-200' : 'border-slate-700 text-slate-400'}`}>1. {t('leagueCreate.steps.basic')}</li><li aria-current={createdId ? 'step' : undefined} className={`rounded-lg border p-3 ${createdId ? 'border-emerald-400 text-emerald-200' : 'border-slate-700 text-slate-400'}`}>2. {t('leagueCreate.steps.rules')}</li></ol>{notice ? <div className="rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100" role="alert">{notice}</div> : null}<div className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-7"><h2 className="mb-5 text-2xl font-semibold text-white">{t(createdId ? 'leagueCreate.steps.rules' : 'leagueCreate.steps.basic')}</h2>{!createdId ? <LeagueBasicInformationForm seasons={[]} leagueTypes={[]} optionsUnavailable isSubmitting={create.isPending} serverErrors={errorsOf(create.error)} onSubmit={payload => { setNotice(null); create.mutate(payload); }}/> : resumedLeague.isLoading || settings.isLoading ? <LoadingState message={t('leagueCreate.loading.defaults')}/> : loadError || !createdLeague || !settings.data ? <div className="rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100" role="alert">{statusMessage(loadError, 'settings')}</div> : <LeagueInitialRulesForm league={createdLeague} settings={settings.data.data} isSaving={save.isPending} serverErrors={errorsOf(save.error)} saveFailed={saveFailed} onSave={payload => { setNotice(null); save.mutate(payload); }}/>}</div></section>;
}