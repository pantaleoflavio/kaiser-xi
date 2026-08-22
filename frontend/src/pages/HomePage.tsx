import { Link } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';

const featureKeys = ['competitions', 'leagues', 'teams', 'roadmap'] as const;

export function HomePage() {
  const { user } = useAuth();
  const { t } = useTranslation();

  return (
    <section className="space-y-10">
      <div className="grid gap-8 rounded-3xl border border-slate-800 bg-slate-900/80 p-8 shadow-2xl shadow-emerald-950/20 md:grid-cols-[1.2fr_0.8fr] md:p-10">
        <div className="space-y-6">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-300">
              {t('home.eyebrow')}
            </p>
            <h1 className="mt-4 max-w-3xl text-4xl font-bold tracking-tight text-white sm:text-5xl">
              {t('home.title')}
            </h1>
            <p className="mt-5 max-w-2xl text-lg leading-8 text-slate-300">{t('home.description')}</p>
          </div>

          <div className="flex flex-col gap-3 sm:flex-row">
            {user ? (
              <Link
                className="rounded-lg bg-emerald-400 px-5 py-3 text-center font-semibold text-slate-950 transition hover:bg-emerald-300"
                to="/dashboard"
              >
                {t('home.cta.dashboard')}
              </Link>
            ) : (
              <>
                <Link
                  className="rounded-lg bg-emerald-400 px-5 py-3 text-center font-semibold text-slate-950 transition hover:bg-emerald-300"
                  to="/login"
                >
                  {t('home.cta.login')}
                </Link>
                <Link
                  className="rounded-lg border border-slate-700 px-5 py-3 text-center font-semibold text-slate-100 transition hover:border-emerald-300 hover:text-emerald-200"
                  to="/register"
                >
                  {t('home.cta.register')}
                </Link>
              </>
            )}
          </div>
        </div>

        <div className="rounded-2xl border border-emerald-400/20 bg-slate-950/70 p-6">
          <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">{t('home.panel.label')}</p>
          <p className="mt-4 text-2xl font-bold text-white">{t('home.panel.title')}</p>
          <p className="mt-3 text-sm leading-6 text-slate-300">{t('home.panel.description')}</p>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        {featureKeys.map((featureKey) => (
          <article className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6" key={featureKey}>
            <h2 className="text-lg font-semibold text-white">{t(`home.features.${featureKey}.title`)}</h2>
            <p className="mt-3 text-sm leading-6 text-slate-300">{t(`home.features.${featureKey}.description`)}</p>
          </article>
        ))}
      </div>
    </section>
  );
}