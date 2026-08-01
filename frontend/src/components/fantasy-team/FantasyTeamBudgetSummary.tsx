import { useTranslation } from '../../i18n';
import type { FantasyTeam, RosterPlayerCollectionResponse } from '../../types/league';
import { formatMoney } from '../../utils/formatters';

export function FantasyTeamBudgetSummary({
  team,
  rosterMeta,
}: {
  team: FantasyTeam;
  rosterMeta?: RosterPlayerCollectionResponse['meta'];
}) {
  const { language, t } = useTranslation();
  const fallback = t('leagueDetail.notAvailable');
  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 className="text-2xl font-semibold text-white">{t('budget.title')}</h2>
      <dl className="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-2">
        <div>
          <dt className="text-slate-500">{t('budget.totalBudget')}</dt>
          <dd>
            {formatMoney(
              rosterMeta?.total_budget ?? rosterMeta?.budget ?? team.budget,
              fallback,
              language,
            )}
          </dd>
        </div>
        <div>
          <dt className="text-slate-500">{t('budget.remainingBudget')}</dt>
          <dd>
            {formatMoney(rosterMeta?.remaining_budget ?? team.remaining_budget, fallback, language)}
          </dd>
        </div>
      </dl>
    </section>
  );
}