import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { accountApi } from '../../api/account';
import { authKeys } from '../../api/queryKeys';
import { useAuth } from '../../auth/useAuth';
import { useTranslation } from '../../i18n';
import { applyTheme, resolveThemeId, themes, type ThemeId } from '../../theme/themes';

export function ThemeSelector() {
  const { user, setAuthenticatedUser } = useAuth();
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [selected, setSelected] = useState(() => resolveThemeId(user?.theme));
  useEffect(() => setSelected(resolveThemeId(user?.theme)), [user?.theme]);
  const mutation = useMutation({
    mutationFn: (theme: ThemeId) => accountApi.updateProfile({ theme }),
    onSuccess: (updatedUser) => {
      setAuthenticatedUser(updatedUser);
      queryClient.setQueryData(authKeys.currentUser(), updatedUser);
    },
    onError: () => {
      const previous = applyTheme(user?.theme);
      setSelected(previous);
    },
  });

  function select(theme: ThemeId) {
    applyTheme(theme);
    setSelected(theme);
    mutation.mutate(theme);
  }

  return (
    <section className="rounded-xl border border-theme-border bg-theme-surface p-6 shadow-sm">
      <h2 className="text-xl font-semibold text-theme-text">{t('account.theme.title')}</h2>
      <p className="mt-2 text-sm text-theme-muted">{t('account.theme.description')}</p>
      <div
        className="mt-5 grid gap-3 sm:grid-cols-2"
        role="radiogroup"
        aria-label={t('account.theme.title')}
      >
        {themes.map((theme) => {
          const checked = selected === theme.id;
          return (
            <button
              aria-checked={checked}
              className="flex min-h-12 items-center gap-3 rounded-lg border border-theme-border bg-theme-muted-surface p-3 text-left text-theme-text transition hover:border-theme-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-focus disabled:opacity-60"
              disabled={mutation.isPending}
              key={theme.id}
              onClick={() => select(theme.id)}
              role="radio"
              type="button"
            >
              <span className="flex gap-1" aria-hidden="true">
                {theme.swatches.map((color) => (
                  <span
                    className="h-5 w-5 rounded-full border border-white/40"
                    key={color}
                    style={{ backgroundColor: color }}
                  />
                ))}
              </span>
              <span className="flex-1 text-sm font-semibold">{theme.name}</span>
              <span className="text-sm" aria-hidden="true">
                {checked ? '✓' : ''}
              </span>
              <span className="sr-only">{checked ? t('account.theme.selected') : ''}</span>
            </button>
          );
        })}
      </div>
      {mutation.isError ? (
        <p className="mt-3 text-sm text-red-600" role="alert">
          {t('account.theme.error')}
        </p>
      ) : null}
    </section>
  );
}
