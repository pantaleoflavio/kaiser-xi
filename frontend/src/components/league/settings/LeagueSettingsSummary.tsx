import type { LeagueSettings } from '../../../types/league';

type Props = {
  settings: LeagueSettings | null;
  locale: string;
  t: (key: string) => string;
};

function formatNumber(value: string | number | null | undefined, fallback: string, locale: string) {
  if (value === null || value === undefined) return fallback;
  return new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(Number(value));
}

export function LeagueSettingsSummary({ settings, locale, t }: Props) {
  const notAvailable = t('leagueDetail.notAvailable');
  return (
    <dl className="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-3">
      <div>
        <dt className="text-slate-500">{t('budget.initialBudget')}</dt>
        <dd>{formatNumber(settings?.initial_budget, notAvailable, locale)}</dd>
      </div>
      <div>
        <dt className="text-slate-500">{t('budget.releaseRefundPercentage')}</dt>
        <dd>{formatNumber(settings?.release_refund_percentage, notAvailable, locale)}%</dd>
      </div>
      <div>
        <dt className="text-slate-500">{t('leagueSettings.maxRosterPlayers')}</dt>
        <dd>{formatNumber(settings?.max_roster_players, notAvailable, locale)}</dd>
      </div>
    </dl>
  );
}