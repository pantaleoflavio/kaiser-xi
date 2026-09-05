import { useQuery } from '@tanstack/react-query';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { playersApi } from '../api/players';
import { playerKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ContentErrorPanel } from '../components/feedback/ContentErrorPanel';
import { ContentEmptyPanel } from '../components/feedback/ContentEmptyPanel';
import { useTranslation } from '../i18n';

const outfieldSummaryFields = [
  'appearances',
  'average_rating',
  'goals',
  'assists',
  'yellow_cards',
  'red_cards',
  'own_goals',
  'penalties_scored',
  'penalties_missed',
  'captain_appearances',
] as const;
const goalkeeperSummaryFields = [
  'appearances',
  'average_rating',
  'clean_sheets',
  'goals_conceded',
  'penalties_saved',
  'yellow_cards',
  'red_cards',
  'goals',
  'assists',
  'captain_appearances',
] as const;

export function PlayerProfilePage() {
  const { playerId = '' } = useParams();
  const [search] = useSearchParams();
  const seasonId = search.get('season') ?? '';
  const { t } = useTranslation();
  const profile = useQuery({
    queryKey: playerKeys.profile(playerId, seasonId),
    queryFn: () => playersApi.profile(playerId, seasonId),
    enabled: Boolean(playerId && seasonId),
  });

  if (!seasonId)
    return (
      <ContentErrorPanel
        title={t('playerProfile.errorTitle')}
        message={t('playerProfile.seasonRequired')}
      />
    );
  if (profile.isLoading) return <LoadingState message={t('playerProfile.loading')} />;
  if (profile.isError)
    return (
      <ContentErrorPanel title={t('playerProfile.errorTitle')} message={t('playerProfile.error')} />
    );
  const data = profile.data?.data;
  if (!data) return null;
  const isGoalkeeper = data.registration.role.key === 'goalkeeper';
  const fields = isGoalkeeper ? goalkeeperSummaryFields : outfieldSummaryFields;
  const hasPerformances = data.statistics.appearances > 0;

  return (
    <section className="space-y-6">
      <Link to="/dashboard" className="text-sm font-semibold text-theme-accent">
        {t('playerProfile.back')}
      </Link>
      <header className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
        <p className="text-sm font-semibold uppercase tracking-wide text-theme-accent">
          {t('playerProfile.title')}
        </p>
        <h1 className="mt-2 text-3xl font-bold text-theme-text">{data.player.display_name}</h1>
        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
          <Info label={t('playerProfile.club')} value={data.registration.club.name} />
          <Info label={t('playerProfile.role')} value={data.registration.role.label} />
          <Info
            label={t('playerProfile.shirtNumber')}
            value={data.registration.shirt_number?.toString() ?? t('playerProfile.notAvailable')}
          />
          <Info label={t('playerProfile.season')} value={data.season.name} />
        </dl>
      </header>
      <section className="space-y-3">
        <h2 className="text-2xl font-semibold text-theme-text">{t('playerProfile.statistics')}</h2>
        {!hasPerformances ? (
          <ContentEmptyPanel
            title={t('playerProfile.emptyTitle')}
            message={t('playerProfile.empty')}
          />
        ) : null}
        <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          {fields.map((field) => (
            <div
              key={field}
              className="rounded-xl border border-theme-border bg-theme-surface/70 p-4"
            >
              <dt className="text-sm text-theme-muted">{t(`playerProfile.fields.${field}`)}</dt>
              <dd className="mt-1 text-2xl font-bold text-theme-text">
                {data.statistics[field] ?? t('playerProfile.notAvailable')}
              </dd>
            </div>
          ))}
        </dl>
      </section>
      <section className="space-y-3">
        <h2 className="text-2xl font-semibold text-theme-text">{t('playerProfile.history')}</h2>
        <div className="grid gap-3">
          {data.matchdays.map((day) => (
            <article
              key={day.id}
              className="rounded-xl border border-theme-border bg-theme-surface/70 p-4"
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h3 className="font-semibold text-theme-text">
                    {day.name
                      ? `${day.number} — ${day.name}`
                      : t('playerProfile.matchday', { number: day.number })}
                  </h3>
                  <p className="text-sm text-theme-muted">
                    {day.opponent
                      ? `${t('playerProfile.opponent')}: ${day.opponent.name} (${t(`playerProfile.venue.${day.venue}`)})`
                      : t('playerProfile.opponentUnknown')}
                  </p>
                </div>
                <span className="rounded-full bg-theme-background px-3 py-1 text-sm font-semibold text-theme-text">
                  {t(`playerProfile.status.${day.status}`)}
                </span>
              </div>
              {day.status === 'played' ? (
                <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-theme-muted">
                  <span>
                    {t('playerProfile.rating')}:{' '}
                    <b className="text-theme-text">{day.base_rating}</b>
                  </span>
                  {isGoalkeeper ? (
                    <>
                      <span>
                        {t('playerProfile.fields.clean_sheets')}:{' '}
                        <b className="text-theme-text">{day.clean_sheet ? 1 : 0}</b>
                      </span>
                      <span>
                        {t('playerProfile.fields.goals_conceded')}:{' '}
                        <b className="text-theme-text">{day.goals_conceded}</b>
                      </span>
                      <span>
                        {t('playerProfile.fields.penalties_saved')}:{' '}
                        <b className="text-theme-text">{day.penalties_saved}</b>
                      </span>
                    </>
                  ) : (
                    <>
                      <span>
                        {t('playerProfile.fields.goals')}:{' '}
                        <b className="text-theme-text">{day.goals}</b>
                      </span>
                      <span>
                        {t('playerProfile.fields.assists')}:{' '}
                        <b className="text-theme-text">{day.assists}</b>
                      </span>
                    </>
                  )}
                  <span>
                    {t('playerProfile.fields.yellow_cards')}:{' '}
                    <b className="text-theme-text">{day.yellow_cards}</b>
                  </span>
                  <span>
                    {t('playerProfile.fields.red_cards')}:{' '}
                    <b className="text-theme-text">{day.red_cards}</b>
                  </span>
                </div>
              ) : null}
            </article>
          ))}
        </div>
      </section>
    </section>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-theme-muted">{label}</dt>
      <dd className="font-semibold text-theme-text">{value}</dd>
    </div>
  );
}
