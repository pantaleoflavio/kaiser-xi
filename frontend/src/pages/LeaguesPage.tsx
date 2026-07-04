import { useEffect, useState } from 'react';
import { leaguesApi } from '../api/leagues';
import { LoadingState } from '../components/LoadingState';
import { useTranslation } from '../i18n';
import type { League } from '../types/league';

export function LeaguesPage() {
  const { language, t } = useTranslation();
  const [leagues, setLeagues] = useState<League[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;

    async function loadLeagues() {
      try {
        setIsLoading(true);
        setError(null);
        const response = await leaguesApi.list();
        if (isMounted) setLeagues(response.data);
      } catch (err) {
        if (isMounted) setError(err instanceof Error ? err.message : t('leagues.error'));
      } finally {
        if (isMounted) setIsLoading(false);
      }
    }

    void loadLeagues();

    return () => {
      isMounted = false;
    };
  }, [language]);

  return (
    <section className="space-y-6">
      <div>
        <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
          {t('leagues.eyebrow')}
        </p>
        <h1 className="mt-2 text-4xl font-bold text-white">{t('leagues.title')}</h1>
        <p className="mt-3 max-w-2xl text-slate-300">{t('leagues.description')}</p>
      </div>

      <div className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
        {isLoading ? <LoadingState message={t('leagues.loading')} /> : null}

        {!isLoading && error ? (
          <div className="rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100">
            <p className="font-semibold">{t('leagues.errorTitle')}</p>
            <p className="mt-1 text-red-100/80">{error}</p>
          </div>
        ) : null}

        {!isLoading && !error && leagues.length === 0 ? (
          <div className="rounded-xl border border-slate-800 bg-slate-950/60 p-6 text-center">
            <h2 className="text-lg font-semibold text-white">{t('leagues.emptyTitle')}</h2>
            <p className="mt-2 text-sm leading-6 text-slate-300">{t('leagues.emptyDescription')}</p>
          </div>
        ) : null}

        {!isLoading && !error && leagues.length > 0 ? (
          <div className="grid gap-4">
            {leagues.map((league) => (
              <article
                className="rounded-xl border border-slate-800 bg-slate-950/60 p-5"
                key={league.id}
              >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <h2 className="text-xl font-semibold text-white">{league.name}</h2>
                    <p className="mt-2 text-sm leading-6 text-slate-300">
                      {league.description || t('leagues.noDescription')}
                    </p>
                  </div>
                  <span className="rounded-full border border-emerald-400/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-200">
                    {league.my_role
                      ? t(`leagues.roles.${league.my_role}`)
                      : t('leagues.roles.unknown')}
                  </span>
                </div>
                <dl className="mt-5 grid gap-3 text-sm text-slate-300 md:grid-cols-4">
                  <div>
                    <dt className="text-slate-500">{t('leagues.fields.competition')}</dt>
                    <dd>{league.season.competition.name}</dd>
                  </div>
                  <div>
                    <dt className="text-slate-500">{t('leagues.fields.season')}</dt>
                    <dd>{league.season.name}</dd>
                  </div>
                  <div>
                    <dt className="text-slate-500">{t('leagues.fields.type')}</dt>
                    <dd>{league.type.label}</dd>
                  </div>
                  <div>
                    <dt className="text-slate-500">{t('leagues.fields.status')}</dt>
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