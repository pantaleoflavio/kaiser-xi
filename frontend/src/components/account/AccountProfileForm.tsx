import { useMutation, useQueryClient } from '@tanstack/react-query';
import { SubmitEvent, useEffect, useMemo, useState } from 'react';
import { accountApi, type UpdateProfilePayload } from '../../api/account';
import { authKeys } from '../../api/queryKeys';
import { useAuth } from '../../auth/useAuth';
import { useTranslation } from '../../i18n';
import { accountError, fieldErrors, type FieldErrors } from './accountFormErrors';

const inputClassName =
  'w-full rounded-md border border-theme-border px-3 py-2 text-theme-primary-foreground';

export function AccountProfileForm() {
  const { user, setAuthenticatedUser } = useAuth();
  const queryClient = useQueryClient();
  const { t } = useTranslation();
  const [name, setName] = useState(user?.name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [currentPassword, setCurrentPassword] = useState('');
  const [errors, setErrors] = useState<FieldErrors>({});
  const [status, setStatus] = useState<string | null>(null);
  const emailChanged = useMemo(() => email.trim() !== (user?.email ?? ''), [email, user?.email]);
  const profileMutation = useMutation({
    mutationFn: accountApi.updateProfile,
    onSuccess: (updatedUser) => {
      setAuthenticatedUser(updatedUser);

      queryClient.setQueryData(authKeys.currentUser(), updatedUser);

      setCurrentPassword('');
      setStatus(t('account.profile.success'));
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      setStatus(t(accountError(error, 'account.errors.validation')));
    },
  });

  useEffect(() => {
    setName(user?.name ?? '');
    setEmail(user?.email ?? '');
  }, [user]);

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors({});
    setStatus(null);

    const trimmedName = name.trim();
    const trimmedEmail = email.trim();
    const payload: UpdateProfilePayload = { name: trimmedName };

    if (emailChanged) {
      payload.email = trimmedEmail;
      payload.current_password = currentPassword;
    }

    profileMutation.mutate(payload);
  }

  return (
    <section className="rounded-xl border border-theme-border bg-theme-surface p-6 shadow-sm">
      <h2 className="text-xl font-semibold text-theme-primary-foreground">
        {t('account.profile.title')}
      </h2>
      <form className="mt-6 space-y-4" onSubmit={submit} noValidate>
        <label className="block text-sm font-medium text-theme-muted" htmlFor="account-name">
          {t('account.fields.name')}
        </label>
        <input
          id="account-name"
          className={inputClassName}
          value={name}
          onChange={(event) => setName(event.target.value)}
          required
          minLength={2}
          maxLength={100}
        />
        {errors.name && (
          <p className="text-sm text-red-600" role="alert">
            {errors.name}
          </p>
        )}
        <label className="block text-sm font-medium text-theme-muted" htmlFor="account-email">
          {t('account.fields.email')}
        </label>
        <input
          id="account-email"
          className={inputClassName}
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
          type="email"
          autoComplete="email"
        />
        {errors.email && (
          <p className="text-sm text-red-600" role="alert">
            {errors.email}
          </p>
        )}
        {emailChanged && (
          <div>
            <label
              className="block text-sm font-medium text-theme-muted"
              htmlFor="profile-current-password"
            >
              {t('account.fields.currentPassword')}
            </label>
            <p className="mb-2 text-sm text-theme-muted">
              {t('account.profile.currentPasswordHint')}
            </p>
            <input
              id="profile-current-password"
              className={inputClassName}
              value={currentPassword}
              onChange={(event) => setCurrentPassword(event.target.value)}
              required
              type="password"
              autoComplete="current-password"
            />
            {errors.current_password && (
              <p className="text-sm text-red-600" role="alert">
                {errors.current_password}
              </p>
            )}
          </div>
        )}
        {status && (
          <p className="text-sm text-theme-muted" role="status" aria-live="polite">
            {status}
          </p>
        )}
        <button
          className="rounded-md bg-theme-primary px-4 py-2 font-medium text-theme-text disabled:opacity-60"
          disabled={profileMutation.isPending}
          type="submit"
        >
          {profileMutation.isPending ? t('account.profile.saving') : t('account.profile.submit')}
        </button>
      </form>
    </section>
  );
}
