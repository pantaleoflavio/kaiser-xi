import { SubmitEvent, useState } from 'react';
import { ApiError } from '../../api/client';
import { leaguesApi } from '../../api/leagues';
import { useTranslation } from '../../i18n';
import type { League, LeagueSettings, PlayerRoleKey, RosterRoleLimits } from '../../types/league';
import { ContentErrorPanel } from '../feedback/ContentErrorPanel';

function formatNumber(value: string | number | null | undefined, fallback: string, locale: string) {
  if (value === null || value === undefined) return fallback;
  return new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(Number(value));
}
function validationDetails(error: unknown) {
  return error instanceof ApiError && error.errors ? Object.values(error.errors).flat() : [];
}
function errorMessage(error: unknown, fallback: string, t: (key: string) => string) {
  if (error instanceof ApiError) {
    if (error.status === 403) return t('common.errors.forbidden');
    if (error.status === 404) return t('common.errors.notFound');
    if (error.status === 422) return t('common.errors.validation');
    return error.message;
  }
  return error instanceof Error ? error.message : fallback;
}

type Props = {
  league: League;
  initialSettings: LeagueSettings | null;
  initialError: string | null;
};

const roleKeys: PlayerRoleKey[] = ['goalkeeper', 'defender', 'midfielder', 'forward'];

export function LeagueSettingsPanel({ league, initialSettings, initialError }: Props) {
  const { language, t } = useTranslation();
  const [settings, setSettings] = useState(initialSettings);
  const [error, setError] = useState(initialError);
  const [details, setDetails] = useState<string[]>([]);
  const [success, setSuccess] = useState<string | null>(null);
  const [isUpdating, setIsUpdating] = useState(false);
  const [initialBudget, setInitialBudget] = useState(String(initialSettings?.initial_budget ?? ''));
  const [refund, setRefund] = useState(String(initialSettings?.release_refund_percentage ?? ''));
  const [maxRosterPlayers, setMaxRosterPlayers] = useState(
    String(initialSettings?.max_roster_players ?? ''),
  );
  const [roleLimits, setRoleLimits] = useState<Record<PlayerRoleKey, string>>(
    Object.fromEntries(
      roleKeys.map((role) => [role, String(initialSettings?.roster_role_limits[role] ?? '')]),
    ) as Record<PlayerRoleKey, string>,
  );
  const canEdit = league.my_role === 'commissioner' || league.my_role === 'co_commissioner';

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!canEdit) return;
    const parsedMaximum = Number(maxRosterPlayers);
    const parsedLimits = Object.fromEntries(
      roleKeys.map((role) => [role, Number(roleLimits[role])]),
    ) as RosterRoleLimits;
    if (
      !Number.isInteger(parsedMaximum) ||
      parsedMaximum < 1 ||
      roleKeys.some((role) => !Number.isInteger(parsedLimits[role]) || parsedLimits[role] < 0) ||
      Object.values(parsedLimits).reduce((sum, limit) => sum + limit, 0) < parsedMaximum
    ) {
      setError(t('leagueSettings.errors.rosterValidation'));
      setDetails([]);
      return;
    }
    try {
      setIsUpdating(true);
      setError(null);
      setDetails([]);
      setSuccess(null);
      const response = await leaguesApi.updateSettings(league.id, {
        initial_budget: Number(initialBudget),
        release_refund_percentage: Number(refund),
        max_roster_players: parsedMaximum,
        roster_role_limits: parsedLimits,
      });
      setSettings(response.data);
      setInitialBudget(String(response.data.initial_budget ?? ''));
      setRefund(String(response.data.release_refund_percentage ?? ''));
      setMaxRosterPlayers(String(response.data.max_roster_players));
      setRoleLimits(
        Object.fromEntries(
          roleKeys.map((role) => [role, String(response.data.roster_role_limits[role])]),
        ) as Record<PlayerRoleKey, string>,
      );
      setSuccess(t('leagueSettings.success'));
    } catch (err) {
      setError(errorMessage(err, t('leagueSettings.errors.update'), t));
      setDetails(validationDetails(err));
    } finally {
      setIsUpdating(false);
    }
  }

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 className="text-2xl font-semibold text-white">{t('leagueSettings.title')}</h2>
      <p className="mt-1 text-sm text-slate-300">{t('leagueSettings.description')}</p>
      {error ? (
        <div className="mt-4">
          <ContentErrorPanel
            details={details}
            message={error}
            title={t('leagueSettings.errors.title')}
          />
        </div>
      ) : null}
      {success ? (
        <div className="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-950/30 p-4 text-sm text-emerald-100">
          {success}
        </div>
      ) : null}
      <dl className="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-3">
        <div>
          <dt className="text-slate-500">{t('budget.initialBudget')}</dt>
          <dd>
            {formatNumber(settings?.initial_budget, t('leagueDetail.notAvailable'), language)}
          </dd>
        </div>
        <div>
          <dt className="text-slate-500">{t('budget.releaseRefundPercentage')}</dt>
          <dd>
            {formatNumber(
              settings?.release_refund_percentage,
              t('leagueDetail.notAvailable'),
              language,
            )}
            %
          </dd>
        </div>
        <div>
          <dt className="text-slate-500">{t('leagueSettings.maxRosterPlayers')}</dt>
          <dd>
            {formatNumber(settings?.max_roster_players, t('leagueDetail.notAvailable'), language)}
          </dd>
        </div>
        {roleKeys.map((role) => (
          <div key={role}>
            <dt className="text-slate-500">{t(`leagueSettings.roles.${role}`)}</dt>
            <dd>
              {formatNumber(
                settings?.roster_role_limits[role],
                t('leagueDetail.notAvailable'),
                language,
              )}
            </dd>
          </div>
        ))}
      </dl>
      {canEdit ? (
        <form className="mt-5 grid gap-3 md:grid-cols-2" onSubmit={handleSubmit}>
          <label className="text-sm text-slate-300">
            {t('budget.initialBudget')}
            <input
              className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
              min="0"
              onChange={(e) => setInitialBudget(e.target.value)}
              step="0.01"
              type="number"
              value={initialBudget}
            />
          </label>
          <label className="text-sm text-slate-300">
            {t('budget.releaseRefundPercentage')}
            <input
              className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
              max="100"
              min="0"
              onChange={(e) => setRefund(e.target.value)}
              step="0.01"
              type="number"
              value={refund}
            />
          </label>
          <label className="text-sm text-slate-300">
            {t('leagueSettings.maxRosterPlayers')}
            <input
              className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
              min="1"
              onChange={(e) => setMaxRosterPlayers(e.target.value)}
              step="1"
              type="number"
              value={maxRosterPlayers}
            />
          </label>
          {roleKeys.map((role) => (
            <label className="text-sm text-slate-300" key={role}>
              {t(`leagueSettings.roles.${role}`)}
              <input
                className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
                min="0"
                onChange={(e) =>
                  setRoleLimits((current) => ({ ...current, [role]: e.target.value }))
                }
                step="1"
                type="number"
                value={roleLimits[role]}
              />
            </label>
          ))}
          <p className="text-sm text-slate-400 md:col-span-2">
            {t('leagueSettings.rosterRuleHelp')}
          </p>
          <button
            className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60 md:col-span-2"
            disabled={isUpdating}
            type="submit"
          >
            {isUpdating ? t('leagueSettings.saving') : t('leagueSettings.save')}
          </button>
        </form>
      ) : (
        <p className="mt-4 text-sm text-slate-400">{t('leagueSettings.readOnly')}</p>
      )}
    </section>
  );
}