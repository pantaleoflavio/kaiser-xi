import { Suspense } from 'react';
import { Outlet } from 'react-router-dom';
import { AppNavigation } from '../components/AppNavigation';
import { LoadingState } from '../components/LoadingState';
import { LegalFooter } from '../components/LegalFooter';
import type { FrontendConfig } from '../config/frontend';
import { frontendConfig } from '../config/frontend';

export function AppLayout({ config = frontendConfig }: { config?: FrontendConfig }) {
  return (
    <div className="min-h-screen bg-theme-background text-theme-text">
      <AppNavigation />

      <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-10">
        <Suspense fallback={<LoadingState />}>
          <Outlet />
        </Suspense>
      </main>
      <LegalFooter config={config} />
    </div>
  );
}
