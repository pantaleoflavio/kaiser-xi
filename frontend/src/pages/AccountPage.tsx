import { useMutation, useQueryClient } from '@tanstack/react-query';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { accountApi, type UpdateProfilePayload } from '../api/account';
import { ApiError } from '../api/client';
import { authKeys } from '../api/queryKeys';
import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';

type FieldErrors = Record<string, string>;

function fieldErrors(error: unknown): FieldErrors {
  if (!(error instanceof ApiError) || !error.errors) return {};
  return Object.fromEntries(
    Object.entries(error.errors).map(([field, messages]) => [field, messages[0] ?? '']),
  );
}

function accountError(error: unknown, fallback: string) {
  if (error instanceof ApiError) {
    if (error.status === 401) return 'account.errors.authentication';
    if (error.status === 403) return 'account.errors.forbidden';
    if (error.status === 409) return 'account.errors.conflict';
    if (error.status === 422) return fallback;
  }

  return 'account.errors.generic';
}

export function AccountPage() {
  const { user } = useAuth();
  const { t } = useTranslation();

  if (!user) return null;

  return (
    <div className="mx-auto max-w-3xl space-y-8">
      <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">
          {t('account.eyebrow')}
        </p>
        <h1 className="mt-2 text-3xl font-bold text-slate-950">{t('account.title')}</h1>
        <p className="mt-2 text-slate-600">{t('account.description')}</p>
        <dl className="mt-6 grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-sm font-medium text-slate-500">{t('account.fields.name')}</dt>
            <dd className="mt-1 text-slate-950">{user.name}</dd>
          </div>
          <div>
            <dt className="text-sm font-medium text-slate-500">{t('account.fields.email')}</dt>
            <dd className="mt-1 text-slate-950">{user.email}</dd>
          </div>
          {user.email_verified_at !== undefined && (
            <div>
              <dt className="text-sm font-medium text-slate-500">
                {t('account.fields.emailVerification')}
              </dt>
              <dd className="mt-1 text-slate-950">
                {user.email_verified_at
                  ? t('account.email.verified')
                  : t('account.email.unverified')}
              </dd>
            </div>
          )}
        </dl>
      </section>
      <AccountProfileForm />
      <ChangePasswordForm />
    </div>
  );
}

function AccountProfileForm() {
  const { user, refreshUser, setAuthenticatedUser } = useAuth();
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
    onSuccess: async (updated) => {
      setAuthenticatedUser(updated);
      queryClient.setQueryData(authKeys.currentUser(), updated);
      await queryClient.invalidateQueries({ queryKey: authKeys.currentUser() });
      await refreshUser();
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

  function submit(event: FormEvent<HTMLFormElement>) {
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
    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-xl font-semibold text-slate-950">{t('account.profile.title')}</h2>
      <form className="mt-6 space-y-4" onSubmit={submit} noValidate>
        <label className="block text-sm font-medium text-slate-700" htmlFor="account-name">
          {t('account.fields.name')}
        </label>
        <input
          id="account-name"
          className="w-full rounded-md border border-slate-300 px-3 py-2"
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
        <label className="block text-sm font-medium text-slate-700" htmlFor="account-email">
          {t('account.fields.email')}
        </label>
        <input
          id="account-email"
          className="w-full rounded-md border border-slate-300 px-3 py-2"
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
              className="block text-sm font-medium text-slate-700"
              htmlFor="profile-current-password"
            >
              {t('account.fields.currentPassword')}
            </label>
            <p className="mb-2 text-sm text-slate-500">
              {t('account.profile.currentPasswordHint')}
            </p>
            <input
              id="profile-current-password"
              className="w-full rounded-md border border-slate-300 px-3 py-2"
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
          <p className="text-sm text-slate-700" role="status" aria-live="polite">
            {status}
          </p>
        )}
        <button
          className="rounded-md bg-emerald-600 px-4 py-2 font-medium text-white disabled:opacity-60"
          disabled={profileMutation.isPending}
          type="submit"
        >
          {profileMutation.isPending ? t('account.profile.saving') : t('account.profile.submit')}
        </button>
      </form>
    </section>
  );
}

function ChangePasswordForm() {
  const { t } = useTranslation();
  const [values, setValues] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors] = useState<FieldErrors>({});
  const [status, setStatus] = useState<string | null>(null);
  const passwordMutation = useMutation({
    mutationFn: accountApi.updatePassword,
    onSuccess: () => {
      setValues({ current_password: '', password: '', password_confirmation: '' });
      setStatus(t('account.password.success'));
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      setStatus(t(accountError(error, 'account.errors.validation')));
    },
  });
  function setField(field: keyof typeof values, value: string) {
    setValues((current) => ({ ...current, [field]: value }));
  }
  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors({});
    setStatus(null);
    passwordMutation.mutate(values);
  }
    return (
    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-xl font-semibold text-slate-950">{t('account.password.title')}</h2>
      <form className="mt-6 space-y-4" onSubmit={submit} noValidate>
        <label className="block text-sm font-medium text-slate-700" htmlFor="password-current">
          {t('account.fields.currentPassword')}
        </label>
        <input
          id="password-current"
          className="w-full rounded-md border border-slate-300 px-3 py-2"
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
        <label className="block text-sm font-medium text-slate-700" htmlFor="password-new">
          {t('account.fields.newPassword')}
        </label>
        <input
          id="password-new"
          className="w-full rounded-md border border-slate-300 px-3 py-2"
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
        <label className="block text-sm font-medium text-slate-700" htmlFor="password-confirmation">
          {t('account.fields.confirmNewPassword')}
        </label>
        <input
          id="password-confirmation"
          className="w-full rounded-md border border-slate-300 px-3 py-2"
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
          <p className="text-sm text-slate-700" role="status" aria-live="polite">
            {status}
          </p>
        )}
        <button
          className="rounded-md bg-emerald-600 px-4 py-2 font-medium text-white disabled:opacity-60"
          disabled={passwordMutation.isPending}
          type="submit"
        >
          {passwordMutation.isPending ? t('account.password.saving') : t('account.password.submit')}
        </button>
      </form>
    </section>
  );
}