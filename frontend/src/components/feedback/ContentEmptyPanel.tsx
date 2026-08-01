export function ContentEmptyPanel({ message, title }: { message: string; title: string }) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-950/60 p-5 text-center">
      <h3 className="font-semibold text-white">{title}</h3>
      <p className="mt-2 text-sm text-slate-300">{message}</p>
    </div>
  );
}