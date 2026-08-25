import { Suspense } from 'react';
import { Outlet } from 'react-router-dom';
import { AppNavigation } from '../components/AppNavigation';
import { LoadingState } from '../components/LoadingState';

export function AppLayout() {
  return (
    <div className="min-h-screen bg-theme-background text-theme-text">
      <AppNavigation />

      <main className="mx-auto max-w-5xl px-6 py-10">
        <Suspense fallback={<LoadingState />}>
          <Outlet />
        </Suspense>
      </main>
    </div>
  );
}
