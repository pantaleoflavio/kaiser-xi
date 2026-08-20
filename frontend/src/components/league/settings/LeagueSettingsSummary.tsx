import type { LeagueSettings } from '../../../types/league';
import {
  hasFormulaOnePositionPoints,
  hasHeadToHeadGoalSettings,
} from './leagueSettingsApplicability';
import { positionPointsToRows } from './formulaOnePositionPoints';

type Props = {
  settings: LeagueSettings | null;
  locale: string;
  t: (key: string, params?: Record<string, string | number | undefined>) => string;
  leagueType: string;
};

function formatNumber(value: string | number | null | undefined, fallback: string, locale: string) {
  if (value === null || value === undefined) return fallback;
  return new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(Number(value));
}

export function LeagueSettingsSummary({ settings, locale, t, leagueType }: Props) {
  const notAvailable = t('leagueDetail.notAvailable');
  return (
    <dl className="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-3">
      <div>
        <dt className="text-slate-500">{t('budget.initialBudget')}</dt>
        <dd>{formatNumber(settings?.initial_budget, notAvailable, locale)}</dd>
      </div>
      {hasHeadToHeadGoalSettings(leagueType) ? (
        <>
          <div>
            <dt className="text-slate-500">{t('leagueSettings.scoring.firstGoalThreshold')}</dt>
            <dd>{formatNumber(settings?.first_goal_threshold, notAvailable, locale)}</dd>
          </div>
          <div>
            <dt className="text-slate-500">{t('leagueSettings.scoring.goalInterval')}</dt>
            <dd>{formatNumber(settings?.goal_interval, notAvailable, locale)}</dd>
          </div>
        </>
      ) : null}
      {hasFormulaOnePositionPoints(leagueType) ? (
        <div className="md:col-span-3">
          <dt className="text-slate-500">{t('formulaOne.positionPoints')}</dt>
          <dd className="mt-1 flex flex-wrap gap-2">
            {positionPointsToRows(settings?.formula_one_position_points ?? {}).map(
              (points, index) => {
                const position = index + 1;

                return (
                  <span className="rounded bg-slate-800 px-2 py-1" key={position}>
                    {t('formulaOne.positionLabel', { position })}: {points}
                  </span>
                );
              },
            )}
          </dd>
        </div>
      ) : null}
      <div>
        <dt className="text-slate-500">{t('leagueSettings.scoring.goalkeeperCleanSheetBonus')}</dt>
        <dd>
          {settings?.goalkeeper_clean_sheet_bonus_enabled
            ? t('common.enabled')
            : t('common.disabled')}
        </dd>
      </div>
      <div>
        <dt className="text-slate-500">{t('leagueSettings.scoring.cleanSheetBonusPoints')}</dt>
        <dd>
          +{formatNumber(settings?.goalkeeper_clean_sheet_bonus_points, notAvailable, locale)}
        </dd>
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
