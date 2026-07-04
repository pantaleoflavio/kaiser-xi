import { FormEvent, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { FormError } from '../components/FormError';
import { useTranslation } from '../i18n';

export function LoginPage() {
  const { login, error } = useAuth();
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const from = (location.state as { from?: { pathname?: string } } | null)?.from?.pathname ?? '/dashboard';
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSubmitting(true);
    setFormError(null);
    const form = new FormData(event.currentTarget);

    try {
      await login({
        email: String(form.get('email') ?? ''),
        password: String(form.get('password') ?? ''),
      });
      navigate(from, { replace: true });
    } catch (err) {
      setFormError(err instanceof Error ? err.message : t('auth.login.error'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form className="mx-auto max-w-md space-y-4 rounded-xl bg-white p-6 text-slate-950" onSubmit={handleSubmit}>
      <div>
        <h1 className="text-2xl font-bold">{t('auth.login.title')}</h1>
        <p className="text-sm text-slate-600">{t('auth.login.description')}</p>
      </div>
      <FormError message={formError ?? error} />
      <label className="block text-sm font-medium">
        {t('auth.login.email')}
        <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" name="email" required type="email" />
      </label>
      <label className="block text-sm font-medium">
        {t('auth.login.password')}
        <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" name="password" required type="password" />
      </label>
      <button className="w-full rounded-md bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60" disabled={isSubmitting} type="submit">
        {isSubmitting ? t('auth.login.submitting') : t('auth.login.submit')}
      </button>
      <p className="text-sm text-slate-600">
        {t('auth.login.noAccount')}{' '}
        <Link className="font-medium text-emerald-700" to="/register">
          {t('auth.login.registerLink')}
        </Link>
      </p>
    </form>
  );
}