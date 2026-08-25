import { Outlet } from 'react-router-dom';
import { AppNavigation } from '../components/AppNavigation';

export function AppLayout() {
  return (
    <div className="min-h-screen bg-theme-background text-theme-text">
      <AppNavigation />

      <main className="mx-auto max-w-5xl px-6 py-10">
        <Outlet />
      </main>
    </div>
  );
}
