import { Link, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { useTranslation } from '../i18n';

export function AppLayout() {
  const { user, logout, isLoading } = useAuth();
  const { t } = useTranslation();
  const navigate = useNavigate();

  async function handleLogout() {
    await logout();
    navigate('/login');
  }

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100">
      <header className="border-b border-slate-800 bg-slate-900/70">
        <nav className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
          <Link to="/" className="text-xl font-bold text-emerald-300">
            {t('app.name')}
          </Link>
          <div className="flex items-center gap-4">
            <LanguageSwitcher />
            {user ? (
              <div className="flex items-center gap-4 text-sm">
                <span className="hidden text-slate-300 sm:inline">{user.email}</span>
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
                <Link to="/login">{t('nav.login')}</Link>
                <Link to="/register">{t('nav.register')}</Link>
              </div>
            )}
          </div>
        </nav>
      </header>
      <main className="mx-auto max-w-5xl px-6 py-10">
        <Outlet />
      </main>
    </div>
  );
}