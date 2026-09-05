import { SubmitEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { accountApi } from '../../api/account';
import { useAuth } from '../../auth/useAuth';
import { FormError } from '../FormError';
import { useTranslation } from '../../i18n';
export function DeleteAccountForm() {
  const { t } = useTranslation();
  const { logout } = useAuth();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  async function submit(e: SubmitEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!window.confirm(t('account.delete.confirm'))) return;
    setPending(true);
    setError(null);
    const f = new FormData(e.currentTarget);
    try {
      await accountApi.deleteAccount(String(f.get('current_password') ?? ''));
      await logout();
      navigate('/login', { replace: true, state: { accountDeleted: true } });
    } catch (x) {
      setError(x instanceof Error ? x.message : t('account.delete.error'));
      setPending(false);
    }
  }
  return (
    <section className="rounded-xl border-2 border-red-600 bg-theme-surface p-6">
      <h2 className="text-xl font-semibold text-red-700">{t('account.delete.title')}</h2>
      <p className="mt-2">{t('account.delete.description')}</p>
      <form className="mt-4 space-y-3" onSubmit={submit}>
        <FormError message={error} />
        <label className="block text-sm font-medium">
          {t('account.fields.currentPassword')}
          <input
            className="mt-1 w-full rounded-md border px-3 py-2"
            name="current_password"
            type="password"
            required
          />
        </label>
        <button
          className="rounded-md bg-red-700 px-4 py-2 font-semibold text-white"
          disabled={pending}
        >
          {pending ? t('account.delete.deleting') : t('account.delete.submit')}
        </button>
      </form>
    </section>
  );
}
