import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { formationsApi } from '../api/formations';
import { leaguesApi } from '../api/leagues';
import { formationKeys, leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { LeagueNavigation } from '../components/league/LeagueNavigation';
import { useTranslation } from '../i18n';
import { formatDate } from '../utils/formatters';

export function MatchdayDetailPage() {
  const { leagueId = '', matchdayId = '' } = useParams();
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
  if (matchdays.isLoading || teams.isLoading) return <LoadingState message={t('common.loading')} />;
  const matchday = matchdays.data?.data.find((item) => item.id === numericId);
  if (!matchday || matchdays.error)
    return (
      <ContentErrorPanel message={t('common.errors.notFound')} title={t('matchdays.notFound')} />
    );
  const myTeam = teams.data?.data.find((team) => team.is_owned_by_current_user);
  const open = Date.now() < new Date(matchday.deadline).getTime();
  return (
    <section className="space-y-6">
      <LeagueNavigation leagueId={leagueId} myTeamId={myTeam?.id} />
      <article className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
        <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
          {open ? t('matchdays.current') : t('matchdays.past')}
        </p>
        <h1 className="mt-2 text-3xl font-bold text-white">
          {matchday.name || t('formation.matchdayNumber', { number: matchday.number })}
        </h1>
        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-sm text-slate-400">{t('formation.deadline')}</dt>
            <dd className="mt-1 text-white">
              {formatDate(matchday.deadline, t('leagueDetail.notAvailable'), language)}
            </dd>
          </div>
        </dl>
        {open && myTeam ? (
          <Link
            className="mt-6 inline-block rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950"
            to={`/leagues/${leagueId}/matchdays/${matchday.id}/fantasy-teams/${myTeam.id}/formation`}
          >
            {t('matchdays.openFormation')}
          </Link>
        ) : null}
      </article>
    </section>
  );
}
