type Props = {
  message: string;
  title: string;
  details?: string[];
};

export function ContentErrorPanel({ details = [], message, title }: Props) {
  return (
    <div className="rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100">
      <p className="font-semibold">{title}</p>
      <p className="mt-1 text-red-100/80">{message}</p>
      {details.length > 0 ? (
        <ul className="mt-3 list-disc space-y-1 pl-5 text-red-100/80">
          {details.map((detail) => (
            <li key={detail}>{detail}</li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}