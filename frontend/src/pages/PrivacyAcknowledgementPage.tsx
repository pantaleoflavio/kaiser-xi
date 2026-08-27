import { SubmitEvent, useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { authApi } from '../api/auth';
import { useAuth } from '../auth/useAuth';
import { FormError } from '../components/FormError';
import { useTranslation } from '../i18n';

export function PrivacyAcknowledgementPage() {
  const { user, isAuthenticated, isLoading, setAuthenticatedUser } = useAuth();
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!isLoading && !isAuthenticated) return <Navigate to="/login" replace />;
  if (!isLoading && user?.privacy_acknowledged) return <Navigate to="/dashboard" replace />;

  async function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      const updatedUser = await authApi.acknowledgePrivacy();
      setAuthenticatedUser(updatedUser);
      navigate(updatedUser.email_verified_at ? '/dashboard' : '/verify-email', { replace: true });
    } catch (err) {
      setError(err instanceof Error ? err.message : t('privacyAcknowledgement.error'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form
      className="mx-auto max-w-lg space-y-5 rounded-xl bg-theme-surface p-6 text-left"
      onSubmit={submit}
    >
      <h1 className="text-2xl font-bold">{t('privacyAcknowledgement.title')}</h1>
      <p className="text-theme-muted">{t('privacyAcknowledgement.description')}</p>
      <FormError message={error} />
      <label className="flex items-start gap-3 text-sm">
        <input className="mt-1" name="privacy_acknowledged" required type="checkbox" />
        <span>
          {t('privacyAcknowledgement.checkboxPrefix')}{' '}
          <Link className="font-medium text-theme-primary underline" to="/privacy">
            {t('legal.privacyTitle')}
          </Link>
          {t('privacyAcknowledgement.checkboxSuffix')}
        </span>
      </label>
      <button
        className="w-full rounded-md bg-theme-primary px-4 py-2 font-semibold text-theme-primary-foreground disabled:opacity-60"
        disabled={submitting}
        type="submit"
      >
        {submitting ? t('privacyAcknowledgement.submitting') : t('privacyAcknowledgement.submit')}
      </button>
    </form>
  );
}
