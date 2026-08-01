import { useState, type FormEvent } from 'react';
import { useTranslation } from '../../i18n';
import type { CreateLeaguePayload, LeagueTypeOption, SeasonOption } from '../../types/league';

type Props = {
  seasons: SeasonOption[];
  leagueTypes: LeagueTypeOption[];
  optionsUnavailable: boolean;
  isSubmitting: boolean;
  serverErrors: Record<string, string[]>;
  onSubmit: (payload: CreateLeaguePayload) => void;
};

type Values = { name: string; description: string; seasonId: string; typeId: string; maximum: string };
const initialValues: Values = { name: '', description: '', seasonId: '', typeId: '', maximum: '10' };

export function LeagueBasicInformationForm(props: Props) {
  const { t } = useTranslation();
  const [values, setValues] = useState(initialValues);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const fieldError = (field: string) => errors[field] ?? props.serverErrors[field]?.[0];
  const inputClass = 'mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white disabled:opacity-60';

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const next: Record<string, string> = {};
    const maximum = Number(values.maximum);
    if (!values.name.trim()) next.name = t('leagueCreate.validation.nameRequired');
    if (!values.seasonId) next.season_id = t('leagueCreate.validation.seasonRequired');
    if (!values.typeId) next.league_type_id = t('leagueCreate.validation.typeRequired');
    if (!Number.isInteger(maximum) || maximum < 2 || maximum > 100)
      next.max_participants = t('leagueCreate.validation.participants');
    setErrors(next);
    if (Object.keys(next).length || props.optionsUnavailable) return;
    props.onSubmit({
      name: values.name.trim(),
      description: values.description.trim() || null,
      season_id: Number(values.seasonId),
      league_type_id: Number(values.typeId),
      max_participants: maximum,
    });
  }

  function error(id: string, field: string) {
    const message = fieldError(field);
    return message ? <p className="mt-1 text-sm text-red-300" id={id}>{message}</p> : null;
  }

  return <form className="grid gap-5" noValidate onSubmit={submit}>
    {props.optionsUnavailable ? <div className="rounded-xl border border-amber-400/30 bg-amber-950/30 p-4 text-sm text-amber-100" role="alert"><p className="font-semibold">{t('leagueCreate.options.title')}</p><p className="mt-1">{t('leagueCreate.options.unavailable')}</p></div> : null}
    <label className="text-sm text-slate-200">{t('leagueCreate.fields.name')}<input aria-describedby={fieldError('name') ? 'create-name-error' : undefined} className={inputClass} disabled={props.isSubmitting} onChange={(e) => setValues({...values, name: e.target.value})} value={values.name}/>{error('create-name-error', 'name')}</label>
    <label className="text-sm text-slate-200">{t('leagueCreate.fields.description')}<textarea className={inputClass} disabled={props.isSubmitting} maxLength={5000} onChange={(e) => setValues({...values, description: e.target.value})} rows={3} value={values.description}/>{error('create-description-error', 'description')}</label>
    <div className="grid gap-5 sm:grid-cols-2">
      <label className="text-sm text-slate-200">{t('leagueCreate.fields.season')}<select aria-describedby={fieldError('season_id') ? 'create-season-error' : undefined} className={inputClass} disabled={props.isSubmitting || props.optionsUnavailable} onChange={(e) => setValues({...values, seasonId: e.target.value})} value={values.seasonId}><option value="">{t('leagueCreate.fields.selectSeason')}</option>{props.seasons.map(option => <option key={option.id} value={option.id}>{option.competition.name} — {option.name}</option>)}</select>{error('create-season-error', 'season_id')}</label>
      <label className="text-sm text-slate-200">{t('leagueCreate.fields.type')}<select aria-describedby={fieldError('league_type_id') ? 'create-type-error' : undefined} className={inputClass} disabled={props.isSubmitting || props.optionsUnavailable} onChange={(e) => setValues({...values, typeId: e.target.value})} value={values.typeId}><option value="">{t('leagueCreate.fields.selectType')}</option>{props.leagueTypes.map(option => <option key={option.id} value={option.id}>{option.label}</option>)}</select>{error('create-type-error', 'league_type_id')}</label>
    </div>
    <label className="text-sm text-slate-200">{t('leagueCreate.fields.maxParticipants')}<input aria-describedby={fieldError('max_participants') ? 'create-maximum-error' : undefined} className={inputClass} disabled={props.isSubmitting} min="2" max="100" step="1" type="number" onChange={(e) => setValues({...values, maximum: e.target.value})} value={values.maximum}/>{error('create-maximum-error', 'max_participants')}</label>
    <button className="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:cursor-not-allowed disabled:opacity-50" disabled={props.isSubmitting || props.optionsUnavailable} type="submit">{props.isSubmitting ? t('leagueCreate.actions.creating') : t('leagueCreate.actions.create')}</button>
  </form>;
}