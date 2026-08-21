import type { Market } from '../../types/league';
import { useTranslation } from '../../i18n';

export function MarketStatus({ market }: { market: Market }) {
  const { t } = useTranslation();
  const date = (value: string | null) =>
    value
      ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(
          new Date(value),
        )
      : t('market.notConfigured');
  return (
    <section className="rounded-xl border border-slate-800 bg-slate-900 p-5">
      <h2 className="text-xl font-bold text-white">{t('market.status')}</h2>
      <p
        className={`mt-2 text-lg font-semibold ${market.is_open ? 'text-emerald-400' : 'text-rose-400'}`}
      >
        {market.is_open ? t('market.open') : t('market.closed')}
      </p>
      <dl className="mt-4 grid gap-3 sm:grid-cols-3">
        <div>
          <dt className="text-slate-400">{t('market.opens')}</dt>
          <dd>{date(market.opens_at)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">{t('market.closes')}</dt>
          <dd>{date(market.closes_at)}</dd>
        </div>
        <div>
          <dt className="text-slate-400">{t('market.cash')}</dt>
          <dd>{market.cash_adjustment_enabled ? t('market.allowed') : t('market.notAllowed')}</dd>
        </div>
      </dl>
    </section>
  );
}
