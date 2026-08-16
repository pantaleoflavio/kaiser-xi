import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { classicChampionshipApi } from '../api/classicChampionship';
import { formationsApi } from '../api/formations';
import { leaguesApi } from '../api/leagues';
import { formationKeys, leagueKeys } from '../api/queryKeys';
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
  const state = useQuery({
    queryKey: [...leagueKeys.detail(leagueId), 'classic-championship'],
    queryFn: () => classicChampionshipApi.get(leagueId),
  });
  const matchdays = useQuery({
    queryKey: formationKeys.matchdays(leagueId),
    queryFn: () => formationsApi.matchdays(leagueId),
  });
  const initialize = useMutation({
    mutationFn: () => classicChampionshipApi.initialize(leagueId, Number(start)),
    onSuccess: (data) =>
      client.setQueryData([...leagueKeys.detail(leagueId), 'classic-championship'], data),
  });
  if (league.isLoading || state.isLoading || matchdays.isLoading)
    return <LoadingState message={t('common.loading')} />;
  const championship = state.data?.data;
  const canStart = ['commissioner', 'co_commissioner'].includes(league.data?.data.my_role ?? '');
  return (
    <section className="space-y-6">
      <LeagueNavigation leagueId={leagueId} showStandings />
      <h1 className="text-3xl font-bold text-white">{t('classic.title')}</h1>
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
            <p className="mt-3">{t('classic.maxNotice')}</p>
            <p className="mt-2 text-amber-200">{t('classic.freezeWarning')}</p>
            <label className="mt-4 block">
              {t('classic.startingMatchday')}
              <select
                className="mt-2 block rounded bg-slate-800 p-2"
                value={start}
                onChange={(event) => setStart(event.target.value)}
              >
                <option value="">—</option>
                {matchdays.data?.data
                  .filter((item) => new Date(item.starts_at) > new Date())
                  .map((item) => (
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
              {t('classic.start')}
            </button>
            <p className="mt-2 text-sm">{t('classic.missingZero')}</p>
          </>
        )}
      </div>
    </section>
  );
}
