import { Link } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { KaiserXiLogo } from '../components/branding/KaiserXiLogo';
import { useTranslation } from '../i18n';

const featureKeys = ['competitions', 'leagues', 'teams', 'roadmap'] as const;

export function HomePage() {
  const { user } = useAuth();
  const { t } = useTranslation();

  return (
    <section className="space-y-10">
      <div className="grid gap-8 rounded-3xl border border-theme-border bg-theme-surface/80 p-8 shadow-2xl shadow-emerald-950/20 md:grid-cols-[1.2fr_0.8fr] md:p-10">
        <div className="space-y-6">
          <div>
            <KaiserXiLogo
              className="h-auto max-h-28 w-full max-w-lg object-contain object-left"
              variant="full"
            />
            <h1 className="mt-4 max-w-3xl text-4xl font-bold tracking-tight text-theme-text sm:text-5xl">
              {t('home.title')}
            </h1>
            <p className="mt-5 max-w-2xl text-lg leading-8 text-theme-muted">
              {t('home.description')}
            </p>
          </div>

          <div className="flex flex-col gap-3 sm:flex-row">
            {user ? (
              <Link
                className="rounded-lg bg-theme-primary px-5 py-3 text-center font-semibold text-theme-primary-foreground transition hover:bg-theme-primary-hover-hover"
                to="/dashboard"
              >
                {t('home.cta.dashboard')}
              </Link>
            ) : (
              <>
                <Link
                  className="rounded-lg bg-theme-primary px-5 py-3 text-center font-semibold text-theme-primary-foreground transition hover:bg-theme-primary-hover-hover"
                  to="/login"
                >
                  {t('home.cta.login')}
                </Link>
                <Link
                  className="rounded-lg border border-theme-border px-5 py-3 text-center font-semibold text-theme-text transition hover:border-theme-accent hover:text-theme-accent"
                  to="/register"
                >
                  {t('home.cta.register')}
                </Link>
              </>
            )}
          </div>
        </div>

        <div className="rounded-2xl border border-theme-primary/20 bg-theme-background/70 p-6">
          <p className="text-sm font-semibold uppercase tracking-wide text-theme-accent">
            {t('home.panel.label')}
          </p>
          <p className="mt-4 text-2xl font-bold text-theme-text">{t('home.panel.title')}</p>
          <p className="mt-3 text-sm leading-6 text-theme-muted">{t('home.panel.description')}</p>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        {featureKeys.map((featureKey) => (
          <article
            className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6"
            key={featureKey}
          >
            <h2 className="text-lg font-semibold text-theme-text">
              {t(`home.features.${featureKey}.title`)}
            </h2>
            <p className="mt-3 text-sm leading-6 text-theme-muted">
              {t(`home.features.${featureKey}.description`)}
            </p>
          </article>
        ))}
      </div>
    </section>
  );
}
