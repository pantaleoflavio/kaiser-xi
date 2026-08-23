export function ContentEmptyPanel({ message, title }: { message: string; title: string }) {
  return (
    <div className="rounded-xl border border-theme-border bg-theme-background/60 p-5 text-center">
      <h3 className="font-semibold text-theme-text">{title}</h3>
      <p className="mt-2 text-sm text-theme-muted">{message}</p>
    </div>
  );
}
