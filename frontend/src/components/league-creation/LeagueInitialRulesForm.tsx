import { useEffect, useState, type SubmitEvent } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from '../../i18n';
import type { League, LeagueSettings, LeagueSettingsPayload, PlayerRoleKey, RosterRoleLimits } from '../../types/league';

const roles: PlayerRoleKey[] = ['goalkeeper', 'defender', 'midfielder', 'forward'];
type Props = { league: League; settings: LeagueSettings; isSaving: boolean; serverErrors: Record<string, string[]>; saveFailed: boolean; onSave: (payload: LeagueSettingsPayload) => void };

export function LeagueInitialRulesForm({ league, settings, isSaving, serverErrors, saveFailed, onSave }: Props) {
  const { t } = useTranslation();
  const [budget, setBudget] = useState(String(settings.initial_budget ?? ''));
  const [refund, setRefund] = useState(String(settings.release_refund_percentage ?? ''));
  const [maximum, setMaximum] = useState(String(settings.max_roster_players));
  const [limits, setLimits] = useState<Record<PlayerRoleKey, string>>(() => Object.fromEntries(roles.map(role => [role, String(settings.roster_role_limits[role])])) as Record<PlayerRoleKey, string>);
  const [errors, setErrors] = useState<Record<string, string>>({});
  useEffect(() => { setBudget(String(settings.initial_budget ?? '')); setRefund(String(settings.release_refund_percentage ?? '')); setMaximum(String(settings.max_roster_players)); setLimits(Object.fromEntries(roles.map(role => [role, String(settings.roster_role_limits[role])])) as Record<PlayerRoleKey, string>); }, [settings]);
  const message = (field: string) => errors[field] ?? serverErrors[field]?.[0];
  const inputClass = 'mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white disabled:opacity-60';

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    const parsedBudget = Number(budget), parsedRefund = Number(refund), parsedMaximum = Number(maximum);
    const parsedLimits = Object.fromEntries(roles.map(role => [role, Number(limits[role])])) as RosterRoleLimits;
    const next: Record<string, string> = {};
    if (!Number.isInteger(parsedBudget) || parsedBudget < 0) next.initial_budget = t('leagueCreate.validation.budget');
    if (!Number.isInteger(parsedRefund) || parsedRefund < 0 || parsedRefund > 100) next.release_refund_percentage = t('leagueCreate.validation.refund');
    if (!Number.isInteger(parsedMaximum) || parsedMaximum < 1) next.max_roster_players = t('leagueCreate.validation.rosterMaximum');
    roles.forEach(role => { if (!Number.isInteger(parsedLimits[role]) || parsedLimits[role] < 0) next[`roster_role_limits.${role}`] = t('leagueCreate.validation.roleLimit'); });
    if (!next.max_roster_players && roles.every(role => !next[`roster_role_limits.${role}`]) && Object.values(parsedLimits).reduce((sum, value) => sum + value, 0) < parsedMaximum) next.roster_role_limits = t('leagueCreate.validation.roleTotal');
    if (Object.keys(next).length) { setErrors(next); return; }
    onSave({ initial_budget: parsedBudget, release_refund_percentage: parsedRefund, max_roster_players: parsedMaximum, roster_role_limits: parsedLimits });
  }
  const error = (field: string) => message(field) ? <p className="mt-1 text-sm text-red-300" id={`rules-${field.replace('.', '-')}-error`}>{message(field)}</p> : null;

  return <form className="grid gap-5" noValidate onSubmit={submit}>
    <div className="rounded-xl border border-emerald-400/30 bg-emerald-950/30 p-4 text-sm text-emerald-100" role="status">{t('leagueCreate.success.created')}</div>
    {saveFailed ? <div className="rounded-xl border border-amber-400/30 bg-amber-950/30 p-4 text-sm text-amber-100" role="alert"><p className="font-semibold">{t('leagueCreate.partial.title')}</p><p className="mt-1">{t('leagueCreate.partial.message')}</p><Link className="mt-2 inline-flex font-semibold underline" to={`/leagues/${league.id}`}>{t('leagueCreate.partial.viewLeague')}</Link></div> : null}
    <div className="grid gap-5 sm:grid-cols-2">
      <label className="text-sm text-slate-200">{t('leagueCreate.fields.initialBudget')}<input className={inputClass} disabled={isSaving} min="0" step="1" type="number" value={budget} onChange={e => setBudget(e.target.value)}/>{error('initial_budget')}</label>
      <label className="text-sm text-slate-200">{t('leagueCreate.fields.refund')}<input className={inputClass} disabled={isSaving} min="0" max="100" step="1" type="number" value={refund} onChange={e => setRefund(e.target.value)}/>{error('release_refund_percentage')}</label>
      <label className="text-sm text-slate-200 sm:col-span-2">{t('leagueCreate.fields.maxRoster')}<input className={inputClass} disabled={isSaving} min="1" step="1" type="number" value={maximum} onChange={e => setMaximum(e.target.value)}/>{error('max_roster_players')}</label>
    </div>
    <fieldset className="grid gap-4 sm:grid-cols-2"><legend className="mb-2 text-base font-semibold text-white">{t('leagueCreate.fields.roleLimits')}</legend>{roles.map(role => <label className="text-sm text-slate-200" key={role}>{t(`leagueCreate.roles.${role}`)}<input className={inputClass} disabled={isSaving} min="0" step="1" type="number" value={limits[role]} onChange={e => setLimits({...limits, [role]: e.target.value})}/>{error(`roster_role_limits.${role}`)}</label>)}<div className="sm:col-span-2">{error('roster_role_limits')}<p className="text-sm text-slate-400">{t('leagueCreate.fields.roleLimitsHelp')}</p></div></fieldset>
    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between"><Link className="rounded-lg border border-slate-700 px-4 py-2 text-center font-semibold text-slate-200" to="/leagues">{t('leagueCreate.actions.back')}</Link><button className="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-50" disabled={isSaving} type="submit">{isSaving ? t('leagueCreate.actions.saving') : saveFailed ? t('leagueCreate.actions.retry') : t('leagueCreate.actions.saveRules')}</button></div>
  </form>;
}