import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';
export function EmailVerifiedPage() {
  const { t } = useTranslation();
  const { refreshUser } = useAuth();
  useEffect(() => {
    void refreshUser();
  }, [refreshUser]);
  return (
    <section className="mx-auto max-w-md space-y-4 rounded-xl bg-theme-surface p-6">
      <h1 className="text-2xl font-bold">{t('auth.verified.title')}</h1>
      <p>{t('auth.verified.description')}</p>
      <Link className="font-medium text-theme-primary" to="/dashboard">
        {t('auth.verified.continue')}
      </Link>
    </section>
  );
}
