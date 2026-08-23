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
    <section className="rounded-xl border border-theme-border bg-theme-surface p-5">
      <h2 className="text-xl font-bold text-theme-text">{t('market.status')}</h2>
      <p
        className={`mt-2 text-lg font-semibold ${market.is_open ? 'text-emerald-400' : 'text-rose-400'}`}
      >
        {market.is_open ? t('market.open') : t('market.closed')}
      </p>
      <dl className="mt-4 grid gap-3 sm:grid-cols-3">
        <div>
          <dt className="text-theme-muted">{t('market.settings.enabled')}</dt>
          <dd>{market.enabled ? t('market.enabledLabel') : t('market.disabledLabel')}</dd>
        </div>
        <div>
          <dt className="text-theme-muted">{t('market.opens')}</dt>
          <dd>{date(market.opens_at)}</dd>
        </div>
        <div>
          <dt className="text-theme-muted">{t('market.closes')}</dt>
          <dd>{date(market.closes_at)}</dd>
        </div>
        <div>
          <dt className="text-theme-muted">{t('market.cash')}</dt>
          <dd>{market.cash_adjustment_enabled ? t('market.allowed') : t('market.notAllowed')}</dd>
        </div>
      </dl>
    </section>
  );
}
