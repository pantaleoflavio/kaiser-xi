import { useState } from 'react';
import { authApi } from '../api/auth';
import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';

export function VerifyEmailPage() {
  const { user, logout } = useAuth();
  const { t } = useTranslation();
  const [message, setMessage] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  async function resend() {
    setPending(true);
    try {
      await authApi.resendVerification();
      setMessage(t('auth.verify.sent'));
    } catch (e) {
      setMessage(e instanceof Error ? e.message : t('auth.verify.error'));
    } finally {
      setPending(false);
    }
  }
  return (
    <section className="mx-auto max-w-md space-y-4 rounded-xl bg-theme-surface p-6">
      <h1 className="text-2xl font-bold">{t('auth.verify.title')}</h1>
      <p>
        {t('auth.verify.description')} <strong>{user?.email}</strong>
      </p>
      {message && <p role="status">{message}</p>}
      <button
        className="rounded-md bg-theme-primary px-4 py-2"
        disabled={pending}
        onClick={() => void resend()}
      >
        {pending ? t('auth.verify.sending') : t('auth.verify.resend')}
      </button>
      <button className="ml-3 underline" onClick={() => void logout()}>
        {t('common.logout')}
      </button>
    </section>
  );
}
