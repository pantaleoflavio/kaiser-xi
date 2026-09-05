import { SubmitEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { authApi } from '../api/auth';
import { FormError } from '../components/FormError';
import { useTranslation } from '../i18n';

export function ForgotPasswordPage() {
  const { t } = useTranslation();
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  async function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setError(null);
    setMessage(null);
    const email = String(new FormData(event.currentTarget).get('email') ?? '');
    try {
      await authApi.forgotPassword(email);
      setMessage(t('auth.forgot.success'));
    } catch (e) {
      setError(e instanceof Error ? e.message : t('auth.forgot.error'));
    } finally {
      setPending(false);
    }
  }
  return (
    <form className="mx-auto max-w-md space-y-4 rounded-xl bg-theme-surface p-6" onSubmit={submit}>
      <h1 className="text-2xl font-bold">{t('auth.forgot.title')}</h1>
      <p>{t('auth.forgot.description')}</p>
      <FormError message={error} />
      {message && (
        <p role="status" className="text-sm text-green-700">
          {message}
        </p>
      )}
      <label className="block text-sm font-medium">
        {t('auth.login.email')}
        <input
          className="mt-1 w-full rounded-md border px-3 py-2"
          name="email"
          type="email"
          required
        />
      </label>
      <button
        className="w-full rounded-md bg-theme-primary px-4 py-2 font-semibold"
        disabled={pending}
      >
        {pending ? t('auth.forgot.submitting') : t('auth.forgot.submit')}
      </button>
      <Link className="text-sm font-medium text-theme-primary" to="/login">
        {t('auth.forgot.back')}
      </Link>
    </form>
  );
}
