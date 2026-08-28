import type { ReactNode } from 'react';

export function LegalPage({
  title,
  updated,
  children,
}: {
  title: string;
  updated: string;
  children: ReactNode;
}) {
  return (
    <article className="mx-auto max-w-3xl space-y-8 rounded-xl bg-theme-surface p-6 text-left text-theme-primary-foreground sm:p-10">
      <header>
        <h1 className="text-3xl font-bold">{title}</h1>
        <p className="text-sm text-theme-muted">{updated}</p>
      </header>
      {children}
    </article>
  );
}

export function LegalSection({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="space-y-3">
      <h2 className="text-xl font-semibold">{title}</h2>
      <div className="space-y-3 leading-7 text-theme-muted">{children}</div>
    </section>
  );
}
