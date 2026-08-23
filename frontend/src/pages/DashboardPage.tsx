import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';
import { Link } from 'react-router-dom';

export function DashboardPage() {
  const { user } = useAuth();
  const { t } = useTranslation();

  return (
    <section className="space-y-6">
      <div>
        <p className="text-sm font-semibold uppercase tracking-wide text-theme-accent">
          {t('dashboard.eyebrow')}
        </p>
        <h1 className="mt-2 text-4xl font-bold text-theme-text">
          {t('dashboard.title', { name: user?.name })}
        </h1>
        <p className="mt-3 max-w-2xl text-theme-muted">{t('dashboard.description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-[1fr_0.8fr]">
        <div className="rounded-xl border border-theme-border bg-theme-surface p-6">
          <h2 className="text-lg font-semibold text-theme-text">{t('dashboard.accountTitle')}</h2>
          <dl className="mt-4 grid gap-3 text-sm text-theme-muted sm:grid-cols-2">
            <div>
              <dt className="text-theme-muted">{t('dashboard.nameLabel')}</dt>
              <dd>{user?.name}</dd>
            </div>
            <div>
              <dt className="text-theme-muted">{t('dashboard.emailLabel')}</dt>
              <dd>{user?.email}</dd>
            </div>
          </dl>
        </div>
        <div className="rounded-xl border border-theme-border bg-theme-surface p-6">
          <h2 className="text-lg font-semibold text-theme-text">{t('dashboard.leaguesTitle')}</h2>
          <p className="mt-3 text-sm leading-6 text-theme-muted">
            {t('dashboard.leaguesDescription')}
          </p>
          <Link
            className="mt-5 inline-flex rounded-md bg-theme-primary px-4 py-2 text-sm font-semibold text-theme-primary-foreground transition hover:bg-theme-primary-hover-hover"
            to="/leagues"
          >
            {t('dashboard.leaguesLink')}
          </Link>
        </div>
      </div>
    </section>
  );
}
