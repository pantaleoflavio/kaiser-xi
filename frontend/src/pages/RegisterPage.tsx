import { SubmitEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { FormError } from '../components/FormError';
import { KaiserXiLogo } from '../components/branding/KaiserXiLogo';
import { useTranslation } from '../i18n';

export function RegisterPage() {
  const { register, error } = useAuth();
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSubmitting(true);
    setFormError(null);
    const form = new FormData(event.currentTarget);
    const password = String(form.get('password') ?? '');
    const passwordConfirmation = String(form.get('password_confirmation') ?? '');

    if (password !== passwordConfirmation) {
      setFormError(t('auth.register.passwordsMustMatch'));
      setIsSubmitting(false);
      return;
    }

    try {
      await register({
        name: String(form.get('name') ?? ''),
        email: String(form.get('email') ?? ''),
        password,
        password_confirmation: passwordConfirmation,
      });
      navigate('/dashboard', { replace: true });
    } catch (err) {
      setFormError(err instanceof Error ? err.message : t('auth.register.error'));
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
        <h1 className="text-2xl font-bold">{t('auth.register.title')}</h1>
        <p className="text-sm text-slate-600">{t('auth.register.description')}</p>
      </div>
      <FormError message={formError ?? error} />
      <label className="block text-sm font-medium">
        {t('auth.register.name')}
        <input
          className="mt-1 w-full rounded-md border border-theme-border px-3 py-2"
          name="name"
          required
        />
      </label>
      <label className="block text-sm font-medium">
        {t('auth.register.email')}
        <input
          className="mt-1 w-full rounded-md border border-theme-border px-3 py-2"
          name="email"
          required
          type="email"
        />
      </label>
      <label className="block text-sm font-medium">
        {t('auth.register.password')}
        <input
          className="mt-1 w-full rounded-md border border-theme-border px-3 py-2"
          minLength={8}
          name="password"
          required
          type="password"
        />
      </label>
      <label className="block text-sm font-medium">
        {t('auth.register.confirmPassword')}
        <input
          className="mt-1 w-full rounded-md border border-theme-border px-3 py-2"
          minLength={8}
          name="password_confirmation"
          required
          type="password"
        />
      </label>
      <button
        className="w-full rounded-md bg-theme-primary px-4 py-2 font-semibold text-theme-primary-foreground disabled:opacity-60"
        disabled={isSubmitting}
        type="submit"
      >
        {isSubmitting ? t('auth.register.submitting') : t('auth.register.submit')}
      </button>
      <p className="text-sm text-slate-600">
        {t('auth.register.alreadyRegistered')}{' '}
        <Link className="font-medium text-theme-primary" to="/login">
          {t('auth.register.loginLink')}
        </Link>
      </p>
    </form>
  );
}
