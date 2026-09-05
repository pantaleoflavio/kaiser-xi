import { useMutation } from '@tanstack/react-query';
import { SubmitEvent, useState } from 'react';
import { accountApi } from '../../api/account';
import { useAuth } from '../../auth/useAuth';
import { useTranslation } from '../../i18n';
import { accountError, fieldErrors, type FieldErrors } from './accountFormErrors';

const inputClassName =
  'w-full rounded-md border border-theme-border px-3 py-2 text-theme-primary-foreground';

const initialPasswordValues = {
  current_password: '',
  password: '',
  password_confirmation: '',
};

export function ChangePasswordForm() {
  const { t } = useTranslation();
  const { logout } = useAuth();
  const [values, setValues] = useState(initialPasswordValues);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [status, setStatus] = useState<string | null>(null);
  const passwordMutation = useMutation({
    mutationFn: accountApi.updatePassword,
    onSuccess: () => {
      setValues(initialPasswordValues);
      setStatus(t('account.password.success'));
      void logout();
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      setStatus(t(accountError(error, 'account.errors.validation')));
    },
  });

  function setField(field: keyof typeof values, value: string) {
    setValues((current) => ({ ...current, [field]: value }));
  }

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors({});
    setStatus(null);
    passwordMutation.mutate(values);
  }

  return (
    <section className="rounded-xl border border-theme-border bg-theme-surface p-6 shadow-sm">
      <h2 className="text-xl font-semibold text-theme-primary-foreground">
        {t('account.password.title')}
      </h2>
      <form className="mt-6 space-y-4" onSubmit={submit} noValidate>
        <label className="block text-sm font-medium text-theme-muted" htmlFor="password-current">
          {t('account.fields.currentPassword')}
        </label>
        <input
          id="password-current"
          className={inputClassName}
          type="password"
          autoComplete="current-password"
          value={values.current_password}
          onChange={(e) => setField('current_password', e.target.value)}
          required
        />
        {errors.current_password && (
          <p className="text-sm text-red-600" role="alert">
            {errors.current_password}
          </p>
        )}
        <label className="block text-sm font-medium text-theme-muted" htmlFor="password-new">
          {t('account.fields.newPassword')}
        </label>
        <input
          id="password-new"
          className={inputClassName}
          type="password"
          autoComplete="new-password"
          value={values.password}
          onChange={(e) => setField('password', e.target.value)}
          required
          minLength={8}
        />
        {errors.password && (
          <p className="text-sm text-red-600" role="alert">
            {errors.password}
          </p>
        )}
        <label
          className="block text-sm font-medium text-theme-muted"
          htmlFor="password-confirmation"
        >
          {t('account.fields.confirmNewPassword')}
        </label>
        <input
          id="password-confirmation"
          className={inputClassName}
          type="password"
          autoComplete="new-password"
          value={values.password_confirmation}
          onChange={(e) => setField('password_confirmation', e.target.value)}
          required
          minLength={8}
        />
        {errors.password_confirmation && (
          <p className="text-sm text-red-600" role="alert">
            {errors.password_confirmation}
          </p>
        )}
        {status && (
          <p className="text-sm text-theme-muted" role="status" aria-live="polite">
            {status}
          </p>
        )}
        <button
          className="rounded-md bg-theme-primary px-4 py-2 font-medium text-theme-text disabled:opacity-60"
          disabled={passwordMutation.isPending}
          type="submit"
        >
          {passwordMutation.isPending ? t('account.password.saving') : t('account.password.submit')}
        </button>
      </form>
    </section>
  );
}
