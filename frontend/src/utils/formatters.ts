export function formatMoney(
  value: string | number | null | undefined,
  fallback: string,
  locale: string,
) {
  if (value === null || value === undefined) return fallback;
  return new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(Number(value));
}

export function formatDate(value: string | null, fallback: string, locale: string) {
  if (!value) return fallback;
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(value),
  );
}