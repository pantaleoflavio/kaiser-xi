import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { LoadingState } from '../components/LoadingState';
import { useTranslation } from '../i18n';

export function GuestRoute() {
  const { isAuthenticated, isLoading } = useAuth();
  const { t } = useTranslation();

  if (isLoading) return <LoadingState message={t('common.checkingSession')} />;
  if (isAuthenticated) return <Navigate to="/dashboard" replace />;
  return <Outlet />;
}