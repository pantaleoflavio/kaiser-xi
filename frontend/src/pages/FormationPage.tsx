import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { formationsApi } from '../api/formations';
import { formationKeys } from '../api/queryKeys';
import { FormationEditor } from '../components/formation/FormationEditor';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { useFantasyTeamDetail } from '../hooks/useFantasyTeamDetail';
import { useTranslation } from '../i18n';
import { formatDate } from '../utils/formatters';

export function FormationPage() {
  const { leagueId = '', matchdayId = '', fantasyTeamId = '' } = useParams();
  const { language, t } = useTranslation();
  const numericId = Number(matchdayId);
  const detail = useFantasyTeamDetail(leagueId, fantasyTeamId);
  const matchdays = useQuery({
    queryKey: formationKeys.matchdays(leagueId),
    queryFn: () => formationsApi.matchdays(leagueId),
    enabled: Number.isInteger(numericId),
  });
  if (detail.isLoading || matchdays.isLoading)
    return <LoadingState message={t('common.loading')} />;
  const team = detail.team.data?.data;
  const roster = detail.roster.data?.data;
  const settings = detail.settings.data?.data;
  const matchday = matchdays.data?.data.find((item) => item.id === numericId);
  if (!team || !roster || !settings || !matchday)
    return (
      <ContentErrorPanel message={t('common.errors.notFound')} title={t('formation.errors.load')} />
    );
  if (!team.is_owned_by_current_user)
    return (
      <ContentErrorPanel
        message={t('common.errors.forbidden')}
        title={t('formation.errors.load')}
      />
    );
  const locked = Date.now() >= new Date(matchday.deadline).getTime();
  return (
    <section className="space-y-6">
      <Link
        className="text-sm font-semibold text-emerald-300 hover:text-emerald-200"
        to={`/leagues/${leagueId}/matchdays/${matchday.id}`}
      >
        {t('matchdays.backToMatchday')}
      </Link>
      <header className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
        <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
          {team.name}
        </p>
        <h1 className="mt-2 text-3xl font-bold text-white">
          {matchday.name || t('formation.matchdayNumber', { number: matchday.number })}
        </h1>
        <p className="mt-3 text-slate-300">
          {t('formation.deadline')}:{' '}
          {formatDate(matchday.deadline, t('leagueDetail.notAvailable'), language)}
        </p>
      </header>
      <FormationEditor
        fantasyTeamId={fantasyTeamId}
        leagueId={leagueId}
        locked={locked}
        matchdayId={numericId}
        roster={roster}
        settings={settings}
      />
    </section>
  );
}
