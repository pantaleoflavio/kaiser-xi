import { SubmitEvent, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { FormError } from '../components/FormError';
import { KaiserXiLogo } from '../components/branding/KaiserXiLogo';
import { useTranslation } from '../i18n';

export function LoginPage() {
  const { login, error } = useAuth();
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const from =
    (location.state as { from?: { pathname?: string } } | null)?.from?.pathname ?? '/dashboard';
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
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
    <form
      className="mx-auto max-w-md space-y-4 rounded-xl bg-theme-surface p-6 text-theme-primary-foreground"
      onSubmit={handleSubmit}
    >
      <KaiserXiLogo className="mx-auto h-auto max-h-24 w-full object-contain" variant="full" />
      <div>
        <h1 className="text-2xl font-bold">{t('auth.login.title')}</h1>
        <p className="text-sm text-slate-600">{t('auth.login.description')}</p>
      </div>
      <FormError message={formError ?? error} />
      <label className="block text-sm font-medium">
        {t('auth.login.email')}
        <input
          className="mt-1 w-full rounded-md border border-theme-border px-3 py-2"
          name="email"
          required
          type="email"
        />
      </label>
      <label className="block text-sm font-medium">
        {t('auth.login.password')}
        <input
          className="mt-1 w-full rounded-md border border-theme-border px-3 py-2"
          name="password"
          required
          type="password"
        />
      </label>
      <button
        className="w-full rounded-md bg-theme-primary px-4 py-2 font-semibold text-theme-primary-foreground disabled:opacity-60"
        disabled={isSubmitting}
        type="submit"
      >
        {isSubmitting ? t('auth.login.submitting') : t('auth.login.submit')}
      </button>
      <p className="text-sm text-slate-600">
        {t('auth.login.noAccount')}{' '}
        <Link className="font-medium text-theme-primary" to="/register">
          {t('auth.login.registerLink')}
        </Link>
      </p>
    </form>
  );
}
