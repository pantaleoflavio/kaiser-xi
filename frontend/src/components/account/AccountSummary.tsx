import { useTranslation } from '../../i18n';
import type { User } from '../../types/auth';

type AccountSummaryProps = {
  user: User;
};

export function AccountSummary({ user }: AccountSummaryProps) {
  const { t } = useTranslation();

  return (
    <section className="rounded-xl border border-theme-border bg-theme-surface p-6 shadow-sm">
      <p className="text-sm font-semibold uppercase tracking-wide text-theme-primary">
        {t('account.eyebrow')}
      </p>
      <h1 className="mt-2 text-3xl font-bold text-theme-primary-foreground">
        {t('account.title')}
      </h1>
      <p className="mt-2 text-slate-600">{t('account.description')}</p>
      <dl className="mt-6 grid gap-4 sm:grid-cols-2">
        <div>
          <dt className="text-sm font-medium text-theme-muted">{t('account.fields.name')}</dt>
          <dd className="mt-1 text-theme-primary-foreground">{user.name}</dd>
        </div>
        <div>
          <dt className="text-sm font-medium text-theme-muted">{t('account.fields.email')}</dt>
          <dd className="mt-1 text-theme-primary-foreground">{user.email}</dd>
        </div>
        {user.email_verified_at !== undefined && (
          <div>
            <dt className="text-sm font-medium text-theme-muted">
              {t('account.fields.emailVerification')}
            </dt>
            <dd className="mt-1 text-theme-primary-foreground">
              {user.email_verified_at ? t('account.email.verified') : t('account.email.unverified')}
            </dd>
          </div>
        )}
      </dl>
    </section>
  );
}
