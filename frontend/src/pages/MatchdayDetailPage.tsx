import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { formationsApi } from '../api/formations';
import { leaguesApi } from '../api/leagues';
import { headToHeadScheduleApi } from '../api/headToHeadSchedule';
import { teamMatchdayResultsApi } from '../api/teamMatchdayResults';
import { formationKeys, leagueKeys, teamMatchdayResultKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import {
  ClassicMatchdayDetail,
  FormulaOneMatchdayDetail,
  HeadToHeadMatchdayDetail,
} from '../components/results/MatchdayResultsSections';
import { useTranslation } from '../i18n';
import { formatDate } from '../utils/formatters';

export function MatchdayDetailPage() {
  const { leagueId = '', matchdayId = '' } = useParams();
  const { language, t } = useTranslation();
  const queryClient = useQueryClient();
  const [calculationFeedback, setCalculationFeedback] = useState<{
    type: 'success' | 'error';
    message: string;
  } | null>(null);
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
  });
  const championshipResults = useQuery({
    queryKey:
      league.data?.data.type.key === 'formula_one'
        ? teamMatchdayResultKeys.formulaOne(leagueId, numericId)
        : teamMatchdayResultKeys.classic(leagueId, numericId),
    queryFn: () =>
      league.data?.data.type.key === 'formula_one'
        ? teamMatchdayResultsApi.championship(leagueId, numericId)
        : teamMatchdayResultsApi.classic(leagueId, numericId),
    enabled:
      ['classic', 'formula_one'].includes(league.data?.data.type.key ?? '') &&
      Number.isInteger(numericId),
  });
  const calculation = useMutation({
    mutationFn: () => formationsApi.calculate(leagueId, numericId),
    onSuccess: async (_, wasRecalculation: boolean) => {
      setCalculationFeedback({
        type: 'success',
        message: t(
          wasRecalculation ? 'matchdays.recalculationSuccess' : 'matchdays.calculationSuccess',
        ),
      });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: formationKeys.matchdays(leagueId) }),
        queryClient.invalidateQueries({ queryKey: leagueKeys.standings(leagueId) }),
        queryClient.invalidateQueries({ queryKey: leagueKeys.headToHeadSchedule(leagueId) }),
        queryClient.invalidateQueries({ queryKey: ['classic-matchday-results', String(leagueId)] }),
        queryClient.invalidateQueries({
          queryKey: ['formula-one-matchday-results', String(leagueId)],
        }),
        queryClient.invalidateQueries({ queryKey: ['team-matchday-result', String(leagueId)] }),
        queryClient.invalidateQueries({ queryKey: ['formation', String(leagueId)] }),
      ]);
    },
    onError: () =>
      setCalculationFeedback({ type: 'error', message: t('matchdays.calculationError') }),
  });
  if (
    matchdays.isLoading ||
    teams.isLoading ||
    league.isLoading ||
    schedule.isLoading ||
    championshipResults.isLoading
  )
    return <LoadingState message={t('common.loading')} />;
  const matchday = matchdays.data?.data.find((item) => item.id === numericId);
  if (!matchday || matchdays.error)
    return (
      <ContentErrorPanel message={t('common.errors.notFound')} title={t('matchdays.notFound')} />
    );
  if (league.data?.data.type.key === 'head_to_head' && schedule.error)
    return (
      <ContentErrorPanel
        message={t('common.errors.unexpected')}
        title={t('results.matchdayResults')}
      />
    );
  if (
    ['classic', 'formula_one'].includes(league.data?.data.type.key ?? '') &&
    championshipResults.error
  )
    return (
      <ContentErrorPanel
        message={t('common.errors.unexpected')}
        title={t('results.matchdayResults')}
      />
    );
  const myTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);
  const open = matchday.championship_state === 'current';
  const scheduled =
    league.data?.data.type.key !== 'head_to_head' ||
    Boolean(
      schedule.data?.data.initialized &&
      schedule.data.data.matchdays.some((group) => group.matchday.id === numericId),
    );
  const fixtures =
    schedule.data?.data.matchdays.find((group) => group.matchday.id === numericId)?.fixtures ?? [];
  const orderedFixtures = myTeam
    ? [...fixtures].sort((left, right) => {
        const includesMyTeam = (fixture: (typeof fixtures)[number]) =>
          fixture.home_fantasy_team.id === myTeam.id || fixture.away_fantasy_team.id === myTeam.id;
        return Number(includesMyTeam(right)) - Number(includesMyTeam(left));
      })
    : fixtures;
  return (
    <section className="space-y-6">
      <LeagueNavigation
        leagueId={leagueId}
        myTeamId={myTeam?.id}
        showSchedule={league.data?.data.type.key === 'head_to_head'}
        showStandings={['head_to_head', 'classic', 'formula_one'].includes(
          league.data?.data.type.key ?? '',
        )}
      />
      <nav className="flex flex-wrap gap-x-5 gap-y-2" aria-label={t('results.matchdayResults')}>
        <Link
          className="text-sm font-semibold text-theme-accent hover:text-theme-accent"
          to={`/leagues/${leagueId}/matchdays`}
        >
          {t('matchdays.backToMatchdays')}
        </Link>
        {league.data?.data.type.key === 'head_to_head' ? (
          <Link
            className="text-sm font-semibold text-theme-accent hover:text-theme-accent"
            to={`/leagues/${leagueId}/standings`}
          >
            {t('standings.title')}
          </Link>
        ) : null}
      </nav>
      <article className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
        <p className="text-sm font-semibold uppercase tracking-wide text-theme-accent">
          {t(`matchdays.${matchday.championship_state ?? (open ? 'current' : 'past')}`)}
        </p>
        <h1 className="mt-2 text-3xl font-bold text-theme-text">
          {matchday.name || t('formation.matchdayNumber', { number: matchday.number })}
        </h1>
        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-sm text-theme-muted">{t('formation.deadline')}</dt>
            <dd className="mt-1 text-theme-text">
              {formatDate(matchday.deadline, t('leagueDetail.notAvailable'), language)}
            </dd>
          </div>
        </dl>
        {open && matchday.formation_allowed && myTeam && scheduled ? (
          <Link
            className="mt-6 inline-block rounded-lg bg-theme-primary px-4 py-2 font-semibold text-theme-primary-foreground"
            to={`/leagues/${leagueId}/matchdays/${matchday.id}/fantasy-teams/${myTeam.id}/formation`}
          >
            {t('matchdays.openFormation')}
          </Link>
        ) : null}
        {calculationFeedback ? (
          <p
            className={`mt-4 rounded-lg p-3 text-sm ${calculationFeedback.type === 'success' ? 'bg-emerald-950 text-theme-accent' : 'bg-red-950 text-red-200'}`}
            role={calculationFeedback.type === 'success' ? 'status' : 'alert'}
          >
            {calculationFeedback.message}
          </p>
        ) : null}
        {matchday.can_calculate || matchday.can_recalculate ? (
          <button
            className="mt-6 rounded-lg bg-amber-400 px-4 py-2 font-semibold text-theme-primary-foreground disabled:opacity-60"
            disabled={calculation.isPending}
            onClick={() => {
              const recalculating = Boolean(matchday.can_recalculate);
              if (recalculating && !window.confirm(t('matchdays.recalculationWarning'))) return;
              setCalculationFeedback(null);
              calculation.mutate(recalculating);
            }}
            type="button"
          >
            {calculation.isPending
              ? t('matchdays.calculating')
              : t(matchday.can_recalculate ? 'matchdays.recalculate' : 'matchdays.calculate')}
          </button>
        ) : null}
      </article>
      {league.data?.data.type.key === 'head_to_head' ? (
        <HeadToHeadMatchdayDetail
          currentTeamId={myTeam?.id}
          fixtures={orderedFixtures}
          leagueId={leagueId}
          matchdayId={numericId}
        />
      ) : null}
      {league.data?.data.type.key === 'classic' ? (
        <ClassicMatchdayDetail
          leagueId={leagueId}
          matchdayId={numericId}
          teams={championshipResults.data?.data.teams ?? []}
        />
      ) : null}
      {league.data?.data.type.key === 'formula_one' ? (
        <FormulaOneMatchdayDetail
          counted={championshipResults.data?.data.matchday.counted ?? false}
          leagueId={leagueId}
          matchdayId={numericId}
          teams={championshipResults.data?.data.teams ?? []}
        />
      ) : null}
    </section>
  );
}
