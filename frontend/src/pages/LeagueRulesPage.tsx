import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { LeagueSettingsPanel } from '../components/league/LeagueSettingsPanel';
import { useTranslation } from '../i18n';

export function LeagueRulesPage() {
  const { leagueId = '' } = useParams();
  const { t } = useTranslation();
  const league = useQuery({
    queryKey: leagueKeys.detail(leagueId),
    queryFn: () => leaguesApi.show(leagueId),
  });
  const settings = useQuery({
    queryKey: leagueKeys.settings(leagueId),
    queryFn: () => leaguesApi.settings(leagueId),
  });
  if (league.isLoading || settings.isLoading) return <LoadingState message={t('common.loading')} />;
  if (!league.data?.data)
    return (
      <ContentErrorPanel message={t('leagueDetail.error')} title={t('leagueDetail.errorTitle')} />
    );
  return (
    <section className="space-y-6">
      <LeagueNavigation leagueId={leagueId} />
      <h1 className="text-3xl font-bold text-white">{t('leagueNavigation.rules')}</h1>
      <LeagueSettingsPanel
        league={league.data.data}
        initialSettings={settings.data?.data ?? null}
        initialError={settings.error?.message ?? null}
      />
    </section>
  );
}
