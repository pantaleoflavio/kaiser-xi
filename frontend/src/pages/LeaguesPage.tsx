import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { leaguesApi } from '../api/leagues';
import { leagueKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';

export function LeaguesPage() {
  const { language, t } = useTranslation();
  const { isAuthenticated } = useAuth();
  const { data, error, isLoading } = useQuery({
    queryKey: [...leagueKeys.lists(), language],
    queryFn: leaguesApi.list,
  });
  const leagues = data?.data ?? [];

  return (
    <section className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-sm font-semibold uppercase tracking-wide text-theme-accent">
            {t('leagues.eyebrow')}
          </p>
          <h1 className="mt-2 text-4xl font-bold text-theme-text">{t('leagues.title')}</h1>
          <p className="mt-3 max-w-2xl text-theme-muted">{t('leagues.description')}</p>
        </div>
        {isAuthenticated ? (
          <Link
            className="inline-flex justify-center rounded-lg bg-theme-primary px-4 py-2 font-semibold text-theme-primary-foreground transition hover:bg-theme-primary-hover"
            to="/leagues/create"
          >
            {t('leagueCreate.actions.createLeague')}
          </Link>
        ) : null}
      </div>

      <div className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
        {isLoading ? <LoadingState message={t('leagues.loading')} /> : null}

        {!isLoading && error ? (
          <div className="rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100">
            <p className="font-semibold">{t('leagues.errorTitle')}</p>
            <p className="mt-1 text-red-100/80">
              {error instanceof Error ? error.message : t('leagues.error')}
            </p>
          </div>
        ) : null}

        {!isLoading && !error && leagues.length === 0 ? (
          <div className="rounded-xl border border-theme-border bg-theme-background/60 p-6 text-center">
            <h2 className="text-lg font-semibold text-theme-text">{t('leagues.emptyTitle')}</h2>
            <p className="mt-2 text-sm leading-6 text-theme-muted">
              {t('leagues.emptyDescription')}
            </p>
          </div>
        ) : null}

        {!isLoading && !error && leagues.length > 0 ? (
          <div className="grid gap-4">
            {leagues.map((league) => (
              <article
                className="rounded-xl border border-theme-border bg-theme-background/60 p-5"
                key={league.id}
              >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <h2 className="text-xl font-semibold text-theme-text">
                      <Link
                        className="transition hover:text-theme-accent"
                        to={`/leagues/${league.id}`}
                      >
                        {league.name}
                      </Link>
                    </h2>
                    <p className="mt-2 text-sm leading-6 text-theme-muted">
                      {league.description || t('leagues.noDescription')}
                    </p>
                  </div>
                  <span className="rounded-full border border-theme-primary/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-theme-accent">
                    {league.my_role
                      ? t(`leagues.roles.${league.my_role}`)
                      : t('leagues.roles.unknown')}
                  </span>
                </div>
                <Link
                  className="mt-4 inline-flex text-sm font-semibold text-theme-accent transition hover:text-theme-accent"
                  to={`/leagues/${league.id}`}
                >
                  {t('leagues.viewDetails')}
                </Link>
                <dl className="mt-5 grid gap-3 text-sm text-theme-muted md:grid-cols-4">
                  <div>
                    <dt className="text-theme-muted">{t('leagues.fields.competition')}</dt>
                    <dd>{league.season.competition.name}</dd>
                  </div>
                  <div>
                    <dt className="text-theme-muted">{t('leagues.fields.season')}</dt>
                    <dd>{league.season.name}</dd>
                  </div>
                  <div>
                    <dt className="text-theme-muted">{t('leagues.fields.type')}</dt>
                    <dd>{league.type.label}</dd>
                  </div>
                  <div>
                    <dt className="text-theme-muted">{t('leagues.fields.status')}</dt>
                    <dd>{league.status.label}</dd>
                  </div>
                </dl>
              </article>
            ))}
          </div>
        ) : null}
      </div>
    </section>
  );
}
