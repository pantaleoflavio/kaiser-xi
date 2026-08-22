// src/components/AppNavigation.tsx

import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { useTranslation } from '../i18n';
import { LanguageSwitcher } from './LanguageSwitcher';

const navLinkClass = 'text-slate-300 transition hover:text-emerald-200';

export function AppNavigation() {
  const { user, logout, isLoading } = useAuth();
  const { t } = useTranslation();
  const navigate = useNavigate();

  async function handleLogout() {
    await logout();
    navigate('/login');
  }

  return (
    <header className="border-b border-slate-800 bg-slate-900/70">
      <nav className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
        <Link to="/" className="text-xl font-bold text-emerald-300">
          {t('app.name')}
        </Link>

        <div className="flex items-center gap-4">
          <div className="hidden items-center gap-4 text-sm sm:flex">
            <Link className={navLinkClass} to="/">
              {t('nav.home')}
            </Link>

            <Link className={navLinkClass} to="/rules">
              {t('nav.rules')}
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
              <span className="hidden text-slate-300 sm:inline">{user.name}</span>
              <button
                className="rounded-md bg-slate-100 px-3 py-2 font-medium text-slate-950 disabled:opacity-60"
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