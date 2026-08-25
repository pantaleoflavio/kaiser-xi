import { SubmitEvent, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { authApi } from '../api/auth';
import { FormError } from '../components/FormError';
import { useTranslation } from '../i18n';

export function ResetPasswordPage() {
  const { t } = useTranslation();
  const [params] = useSearchParams();
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  async function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const password = String(form.get('password') ?? '');
    const confirmation = String(form.get('password_confirmation') ?? '');
    if (password !== confirmation) {
      setError(t('auth.register.passwordsMustMatch'));
      return;
    }
    setPending(true);
    setError(null);
    try {
      await authApi.resetPassword({
        token: params.get('token') ?? '',
        email: params.get('email') ?? '',
        password,
        password_confirmation: confirmation,
      });
      setMessage(t('auth.reset.success'));
    } catch (e) {
      setError(e instanceof Error ? e.message : t('auth.reset.error'));
    } finally {
      setPending(false);
    }
  }
  return (
    <form className="mx-auto max-w-md space-y-4 rounded-xl bg-theme-surface p-6" onSubmit={submit}>
      <h1 className="text-2xl font-bold">{t('auth.reset.title')}</h1>
      <FormError message={error} />
      {message && (
        <p role="status" className="text-green-700">
          {message}
        </p>
      )}
      <label className="block text-sm font-medium">
        {t('auth.register.password')}
        <input
          className="mt-1 w-full rounded-md border px-3 py-2"
          name="password"
          type="password"
          minLength={8}
          required
        />
      </label>
      <label className="block text-sm font-medium">
        {t('auth.register.confirmPassword')}
        <input
          className="mt-1 w-full rounded-md border px-3 py-2"
          name="password_confirmation"
          type="password"
          minLength={8}
          required
        />
      </label>
      <button
        className="w-full rounded-md bg-theme-primary px-4 py-2 font-semibold"
        disabled={pending}
      >
        {pending ? t('auth.reset.submitting') : t('auth.reset.submit')}
      </button>
      {message && <Link to="/login">{t('auth.reset.login')}</Link>}
    </form>
  );
}
