import { useTranslation } from '../../i18n';
import type { FantasyTeam } from '../../types/league';
import { formatMoney } from '../../utils/formatters';

export function FantasyTeamBudgetSummary({ team }: { team: FantasyTeam }) {
  const { language, t } = useTranslation();
  const fallback = t('leagueDetail.notAvailable');
  return (
    <section className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
      <h2 className="text-2xl font-semibold text-theme-text">{t('budget.title')}</h2>
      <dl className="mt-4 grid gap-3 text-sm text-theme-muted md:grid-cols-2">
        <div>
          <dt className="text-theme-muted">{t('budget.totalBudget')}</dt>
          <dd>{formatMoney(team.budget, fallback, language)}</dd>
        </div>
        <div>
          <dt className="text-theme-muted">{t('budget.remainingBudget')}</dt>
          <dd>{formatMoney(team.remaining_budget, fallback, language)}</dd>
        </div>
      </dl>
    </section>
  );
}
