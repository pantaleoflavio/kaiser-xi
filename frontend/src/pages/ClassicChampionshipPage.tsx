import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { classicChampionshipApi, formulaOneChampionshipApi } from '../api/classicChampionship';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LoadingState } from '../components/LoadingState';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { useTranslation } from '../i18n';

export function ClassicChampionshipPage() {
  const { leagueId = '' } = useParams();
  const { t } = useTranslation();
  const client = useQueryClient();
  const [start, setStart] = useState('');
  const league = useQuery({
    queryKey: leagueKeys.detail(leagueId),
    queryFn: () => leaguesApi.show(leagueId),
  });
  const formulaOne = league.data?.data.type.key === 'formula_one';
  const championshipApi = formulaOne ? formulaOneChampionshipApi : classicChampionshipApi;
  const state = useQuery({
    queryKey: leagueKeys.championship(leagueId, formulaOne ? 'formula_one' : 'classic'),
    queryFn: () => championshipApi.get(leagueId),
    enabled: Boolean(league.data),
  });
  const initialize = useMutation({
    mutationFn: () => championshipApi.initialize(leagueId, Number(start)),
    onSuccess: async (data) => {
      client.setQueryData(
        leagueKeys.championship(leagueId, formulaOne ? 'formula_one' : 'classic'),
        data,
      );
      await Promise.all([
        client.invalidateQueries({ queryKey: leagueKeys.detail(leagueId) }),
        client.invalidateQueries({ queryKey: leagueKeys.settings(leagueId) }),
      ]);
    },
  });
  if (league.isLoading || state.isLoading) return <LoadingState message={t('common.loading')} />;
  if (league.error || state.error)
    return (
      <ContentErrorPanel
        title={t('formulaOne.initializationTitle')}
        message={(league.error ?? state.error)?.message ?? t('common.errors.unexpected')}
      />
    );
  const championship = state.data?.data;
  const canStart = ['commissioner', 'co_commissioner'].includes(league.data?.data.my_role ?? '');
  return (
    <section className="space-y-6">
      <LeagueNavigation leagueId={leagueId} showStandings />
      <h1 className="text-3xl font-bold text-white">
        {formulaOne ? t('formulaOne.initializationTitle') : t('classic.title')}
      </h1>
      <div className="rounded-xl border border-slate-800 bg-slate-900/70 p-5 text-slate-200">
        <p>
          {t('classic.participants', {
            count: championship?.participant_count ?? 0,
            max: championship?.max_participants ?? '—',
          })}
        </p>
        {championship?.initialized ? (
          <p className="mt-3 text-emerald-300">{t('classic.started')}</p>
        ) : (
          <>
            <p className="mt-3">
              {formulaOne ? t('formulaOne.maxNotice') : t('classic.maxNotice')}
            </p>
            <p className="mt-2 text-amber-200">
              {formulaOne ? t('formulaOne.freezeWarning') : t('classic.freezeWarning')}
            </p>
            <label className="mt-4 block">
              {t('classic.startingMatchday')}
              <select
                className="mt-2 block rounded bg-slate-800 p-2"
                value={start}
                onChange={(event) => setStart(event.target.value)}
              >
                <option value="">—</option>
                {championship?.available_start_matchdays.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name ?? item.number}
                  </option>
                ))}
              </select>
            </label>
            <button
              className="mt-4 rounded bg-emerald-600 px-4 py-2 disabled:opacity-50"
              disabled={!canStart || !start || initialize.isPending}
              onClick={() => initialize.mutate()}
            >
              {formulaOne ? t('formulaOne.initializeAction') : t('classic.start')}
            </button>
            {!championship?.available_start_matchdays.length ? (
              <p className="mt-2 text-sm text-amber-200">{t('classic.noFutureMatchdays')}</p>
            ) : null}
          </>
        )}
      </div>
    </section>
  );
}
