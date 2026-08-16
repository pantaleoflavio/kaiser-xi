import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { formationsApi } from '../api/formations';
import { leaguesApi } from '../api/leagues';
import { teamMatchdayResultsApi } from '../api/teamMatchdayResults';
import { headToHeadScheduleApi } from '../api/headToHeadSchedule';
import { formationKeys, leagueKeys, teamMatchdayResultKeys } from '../api/queryKeys';
import { FormationEditor } from '../components/formation/FormationEditor';
import { SubmittedFormationView } from '../components/formation/SubmittedFormationView';
import { HistoricalFormationView } from '../components/results/HistoricalFormationView';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { useFantasyTeamDetail } from '../hooks/useFantasyTeamDetail';
import { useTranslation } from '../i18n';
import { formatDate } from '../utils/formatters';

function OwnedFormationEditor({
  leagueId,
  teamId,
  matchdayId,
  locked = false,
}: {
  leagueId: string;
  teamId: string;
  matchdayId: number;
  locked?: boolean;
}) {
  const { t } = useTranslation();
  const detail = useFantasyTeamDetail(leagueId, teamId);
  if (detail.isLoading) return <LoadingState message={t('common.loading')} />;
  const roster = detail.roster.data?.data;
  const settings = detail.settings.data?.data;
  if (!roster || !settings)
    return (
      <ContentErrorPanel message={t('common.errors.notFound')} title={t('formation.errors.load')} />
    );
  return (
    <FormationEditor
      fantasyTeamId={teamId}
      leagueId={leagueId}
      locked={locked}
      matchdayId={matchdayId}
      roster={roster}
      settings={settings}
    />
  );
}

export function FormationPage() {
  const { leagueId = '', matchdayId = '', fantasyTeamId = '' } = useParams();
  const { language, t } = useTranslation();
  const numericId = Number(matchdayId);
  const matchdays = useQuery({
    queryKey: formationKeys.matchdays(leagueId),
    queryFn: () => formationsApi.matchdays(leagueId),
    enabled: Number.isInteger(numericId),
  });
  const teams = useQuery({
    queryKey: leagueKeys.fantasyTeams(leagueId),
    queryFn: () => leaguesApi.fantasyTeams(leagueId),
  });
  const league = useQuery({
    queryKey: leagueKeys.detail(leagueId),
    queryFn: () => leaguesApi.show(leagueId),
  });
  const schedule = useQuery({
    queryKey: leagueKeys.headToHeadSchedule(leagueId),
    queryFn: () => headToHeadScheduleApi.getSchedule(leagueId),
    enabled: league.data?.data.type.key === 'head_to_head',
    retry: false,
  });
  const matchday = matchdays.data?.data.find((item) => item.id === numericId);
  const team = teams.data?.data.find((item) => String(item.id) === fantasyTeamId);
  const historical = Boolean(matchday && Date.now() >= new Date(matchday.deadline).getTime());
  const owned = Boolean(team?.is_owned_by_current_user);
  const formationAllowed = matchday?.formation_allowed === true;
  const visibleFormation = useQuery({
    queryKey: formationKeys.detail(leagueId, numericId, fantasyTeamId),
    queryFn: () => formationsApi.show(leagueId, numericId, fantasyTeamId),
    enabled: formationAllowed && Boolean(team && !owned),
    retry: false,
  });
  const submittedFormation = visibleFormation.data?.data;
  const result = useQuery({
    queryKey: teamMatchdayResultKeys.detail(leagueId, numericId, fantasyTeamId),
    queryFn: () => teamMatchdayResultsApi.show(leagueId, numericId, fantasyTeamId),
    enabled: historical && Boolean(team),
  });
  if (
    matchdays.isLoading ||
    teams.isLoading ||
    league.isLoading ||
    schedule.isLoading ||
    (historical && result.isLoading) ||
    visibleFormation.isLoading
  )
    return <LoadingState message={t('common.loading')} />;
  if (!team || !matchday)
    return (
      <ContentErrorPanel message={t('common.errors.notFound')} title={t('formation.errors.load')} />
    );
  const showHistorical = historical;
  const showOwnerEditor = !historical && owned && formationAllowed;
  const showSubmittedFormation = !historical && !owned && Boolean(submittedFormation);

  if (!historical && !formationAllowed && !submittedFormation)
    return (
      <ContentErrorPanel
        message={t('formation.errors.scheduleNotInitialized')}
        title={t('formation.errors.load')}
      />
    );
  if (!historical && !owned && !submittedFormation)
    return (
      <ContentErrorPanel
        message={t('common.errors.forbidden')}
        title={t('formation.errors.load')}
      />
    );
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
      {showHistorical && result.data ? <HistoricalFormationView data={result.data.data} /> : null}
      {showSubmittedFormation && submittedFormation ? (
        <SubmittedFormationView formation={submittedFormation} />
      ) : null}
      {showOwnerEditor ? (
        <OwnedFormationEditor leagueId={leagueId} matchdayId={numericId} teamId={fantasyTeamId} />
      ) : null}
    </section>
  );
}
