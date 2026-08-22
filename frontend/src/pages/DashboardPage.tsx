import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';
import { Link } from 'react-router-dom';

export function DashboardPage() {
  const { user } = useAuth();
  const { t } = useTranslation();

  return (
    <section className="space-y-6">
      <div>
        <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
          {t('dashboard.eyebrow')}
        </p>
        <h1 className="mt-2 text-4xl font-bold text-white">
          {t('dashboard.title', { name: user?.name })}
        </h1>
        <p className="mt-3 max-w-2xl text-slate-300">{t('dashboard.description')}</p>
      </div>
            <div className="grid gap-4 md:grid-cols-[1fr_0.8fr]">
        <div className="rounded-xl border border-slate-800 bg-slate-900 p-6">
          <h2 className="text-lg font-semibold text-white">{t('dashboard.accountTitle')}</h2>
          <dl className="mt-4 grid gap-3 text-sm text-slate-300 sm:grid-cols-2">
            <div>
              <dt className="text-slate-500">{t('dashboard.nameLabel')}</dt>
              <dd>{user?.name}</dd>
            </div>
            <div>
              <dt className="text-slate-500">{t('dashboard.emailLabel')}</dt>
              <dd>{user?.email}</dd>
            </div>
          </dl>
        </div>
        <div className="rounded-xl border border-slate-800 bg-slate-900 p-6">
          <h2 className="text-lg font-semibold text-white">{t('dashboard.leaguesTitle')}</h2>
          <p className="mt-3 text-sm leading-6 text-slate-300">
            {t('dashboard.leaguesDescription')}
          </p>
          <Link
            className="mt-5 inline-flex rounded-md bg-emerald-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300"
            to="/leagues"
          >
            {t('dashboard.leaguesLink')}
          </Link>
        </div>
      </div>
    </section>
  );
}