import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';
import { leagueKeys, leagueMutationKeys } from '../api/queryKeys';
import { LeagueBasicInformationForm } from '../components/league-creation/LeagueBasicInformationForm';
import { LeagueInitialRulesForm } from '../components/league-creation/LeagueInitialRulesForm';
import { LoadingState } from '../components/LoadingState';
import { useActiveSeasons, useLeagueTypes } from '../hooks/useLeagueCreationLookups';
import { useTranslation } from '../i18n';
import type { League } from '../types/league';

const STORAGE_KEY = 'fantameister_created_league_id';
const errorsOf = (error: unknown) => error instanceof ApiError && error.status === 422 ? error.errors ?? {} : {};

function storedLeagueId() {
  const parsed = Number(sessionStorage.getItem(STORAGE_KEY));
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

export function CreateLeaguePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [createdLeague, setCreatedLeague] = useState<League | null>(null);
  const [createdId, setCreatedId] = useState<number | null>(storedLeagueId);
  const [notice, setNotice] = useState<string | null>(null);
  const [saveFailed, setSaveFailed] = useState(false);
  const seasons = useActiveSeasons(createdId === null);
  const leagueTypes = useLeagueTypes(createdId === null);
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
      if (error.status >= 500) return t('leagueCreate.errors.server');
    }
     return t('leagueCreate.errors.network');
  }
  const create = useMutation({
    mutationKey: leagueMutationKeys.create,
    mutationFn: leaguesApi.create,
    onSuccess: async response => {
      const league = response.data;
      setCreatedLeague(league);
      setCreatedId(league.id);
      sessionStorage.setItem(STORAGE_KEY, String(league.id));
      setNotice(null);
      await queryClient.invalidateQueries({ queryKey: leagueKeys.lists() });
    },
    onError: error => setNotice(statusMessage(error, 'create')),
  });
  const save = useMutation({
    mutationKey: leagueMutationKeys.settings(createdId ?? ''),
    mutationFn: (payload: Parameters<typeof leaguesApi.updateSettings>[1]) => leaguesApi.updateSettings(createdId!, payload),
    onSuccess: async () => {
      setSaveFailed(false);
      sessionStorage.removeItem(STORAGE_KEY);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: leagueKeys.lists() }),
        queryClient.invalidateQueries({ queryKey: leagueKeys.detail(createdId!) }),
        queryClient.invalidateQueries({ queryKey: leagueKeys.settings(createdId!) }),
      ]);
      navigate(`/leagues/${createdId}`, { replace: true, state: { success: t('leagueCreate.success.settings') } });
    },
    onError: error => { setSaveFailed(true); setNotice(statusMessage(error, 'settings')); },
  });

  const lookupLoading = seasons.isLoading || leagueTypes.isLoading;
  const seasonOptions = seasons.data?.data ?? [];
  const typeOptions = leagueTypes.data?.data ?? [];
  const loadError = resumedLeague.error ?? settings.error;

    function lookupContent() {
    if (lookupLoading) return <div className="min-h-48"><LoadingState message={t(seasons.isLoading ? 'leagueCreate.loading.seasons' : 'leagueCreate.loading.leagueTypes')}/></div>;
    if (seasons.isError || leagueTypes.isError) return <div className="min-h-48 rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100" role="alert"><p className="font-semibold">{t(seasons.isError ? 'leagueCreate.lookups.seasonsUnavailable' : 'leagueCreate.lookups.typesUnavailable')}</p><button className="mt-3 rounded-lg border border-red-300/40 px-3 py-2 font-semibold" onClick={() => { if (seasons.isError) void seasons.refetch(); if (leagueTypes.isError) void leagueTypes.refetch(); }} type="button">{t('leagueCreate.actions.retryLoading')}</button></div>;
    if (!seasonOptions.length || !typeOptions.length) return <div className="min-h-48 rounded-xl border border-amber-400/30 bg-amber-950/30 p-4 text-sm text-amber-100" role="status"><p className="font-semibold">{t(!seasonOptions.length ? 'leagueCreate.lookups.noActiveSeasons' : 'leagueCreate.lookups.noLeagueTypes')}</p><button className="mt-3 rounded-lg border border-amber-300/40 px-3 py-2 font-semibold" onClick={() => void (!seasonOptions.length ? seasons.refetch() : leagueTypes.refetch())} type="button">{t('leagueCreate.actions.retryLoading')}</button></div>;
    return <LeagueBasicInformationForm seasons={seasonOptions} leagueTypes={typeOptions} isSubmitting={create.isPending} serverErrors={errorsOf(create.error)} onSubmit={payload => { setNotice(null); create.mutate(payload); }}/>;
  }

  function rulesContent() {
    if (resumedLeague.isLoading || settings.isLoading) return <div className="min-h-48"><LoadingState message={t('leagueCreate.loading.defaults')}/></div>;
    if (loadError || !createdLeague || !settings.data) return <div className="rounded-xl border border-amber-400/30 bg-amber-950/30 p-4 text-sm text-amber-100" role="alert"><p className="font-semibold">{t('leagueCreate.partial.loadTitle')}</p><p className="mt-1">{statusMessage(loadError, 'settings')}</p><div className="mt-3 flex flex-wrap gap-3"><button className="rounded-lg border border-amber-300/40 px-3 py-2 font-semibold" onClick={() => { void resumedLeague.refetch(); void settings.refetch(); }} type="button">{t('leagueCreate.actions.retryLoading')}</button>{createdId ? <Link className="px-3 py-2 font-semibold underline" to={`/leagues/${createdId}`}>{t('leagueCreate.partial.viewLeague')}</Link> : null}</div></div>;
    return <LeagueInitialRulesForm league={createdLeague} settings={settings.data.data} isSaving={save.isPending} serverErrors={errorsOf(save.error)} saveFailed={saveFailed} onSave={payload => { setNotice(null); save.mutate(payload); }}/>;
  }

  return <section className="mx-auto max-w-3xl space-y-6"><header><p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">{t('leagueCreate.eyebrow')}</p><h1 className="mt-2 text-3xl font-bold text-white sm:text-4xl">{t('leagueCreate.title')}</h1><p className="mt-3 text-slate-300">{t('leagueCreate.description')}</p></header><ol aria-label={t('leagueCreate.progress.label')} className="grid grid-cols-2 gap-2 text-sm"><li aria-current={!createdId ? 'step' : undefined} className={`rounded-lg border p-3 ${!createdId ? 'border-emerald-400 text-emerald-200' : 'border-slate-700 text-slate-400'}`}>1. {t('leagueCreate.steps.basic')}</li><li aria-current={createdId ? 'step' : undefined} className={`rounded-lg border p-3 ${createdId ? 'border-emerald-400 text-emerald-200' : 'border-slate-700 text-slate-400'}`}>2. {t('leagueCreate.steps.rules')}</li></ol>{notice ? <div className="rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100" role="alert">{notice}</div> : null}<div className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-7"><h2 className="mb-5 text-2xl font-semibold text-white">{t(createdId ? 'leagueCreate.steps.rules' : 'leagueCreate.steps.basic')}</h2>{createdId ? rulesContent() : lookupContent()}</div></section>;
}