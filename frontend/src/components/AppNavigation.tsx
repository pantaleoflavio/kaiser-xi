// src/components/AppNavigation.tsx

import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';
import { KaiserXiLogo } from './branding/KaiserXiLogo';
import { LanguageSwitcher } from './LanguageSwitcher';

const navLinkClass = 'text-theme-muted transition hover:text-theme-accent';

export function AppNavigation() {
  const { user, logout, isLoading } = useAuth();
  const { t } = useTranslation();
  const navigate = useNavigate();

  async function handleLogout() {
    await logout();
    navigate('/login');
  }

  return (
    <header className="border-b border-theme-border bg-theme-surface/70">
      <nav className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-6 py-3">
        <Link
          aria-label={t('app.name')}
          className="shrink-0 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-theme-accent"
          to="/"
        >
          <KaiserXiLogo className="h-10 w-auto sm:hidden" variant="symbol" />
          <KaiserXiLogo className="hidden h-10 w-auto sm:block" variant="horizontal" />
        </Link>

        <div className="flex min-w-0 items-center gap-3 sm:gap-4">
          <div className="hidden items-center gap-4 text-sm sm:flex">
            <Link className={navLinkClass} to="/">
              {t('nav.home')}
            </Link>

            <Link className={navLinkClass} to="/game-instruction">
              {t('nav.gameInstructions')}
            </Link>

            {user && (
              <>
                <Link className={navLinkClass} to="/dashboard">
                  {t('nav.dashboard')}
                </Link>

                <Link className={navLinkClass} to="/account">
                  {t('nav.account')}
                </Link>

                <Link className={navLinkClass} to="/leagues">
                  {t('nav.leagues')}
                </Link>
                <Link className={navLinkClass} to="/invitations">
                  {t('nav.invitations')}
                </Link>
              </>
            )}
          </div>

          <LanguageSwitcher />

          {user ? (
            <div className="flex items-center gap-4 text-sm">
              <span className="hidden text-theme-muted sm:inline">{user.name}</span>
              <button
                className="rounded-md border border-theme-border bg-theme-muted-surface px-3 py-2 font-medium text-theme-text transition hover:bg-theme-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-focus focus-visible:ring-offset-2 focus-visible:ring-offset-theme-background disabled:cursor-not-allowed"
                disabled={isLoading}
                onClick={handleLogout}
                type="button"
              >
                {t('common.logout')}
              </button>
            </div>
          ) : (
            <div className="flex gap-3 text-sm">
              <Link className={navLinkClass} to="/login">
                {t('nav.login')}
              </Link>

              <Link className={navLinkClass} to="/register">
                {t('nav.register')}
              </Link>
            </div>
          )}
        </div>
      </nav>
    </header>
  );
}
