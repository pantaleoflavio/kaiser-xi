import { useEffect, useMemo, useState, type SubmitEvent } from 'react';
import { useTranslation } from '../../i18n';
import type { CreateLeaguePayload, LeagueType, Season } from '../../types/league';

type Props = {
  seasons: Season[];
  leagueTypes: LeagueType[];
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
  const inputClass = 'mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white disabled:opacity-60';

  useEffect(() => {
    const classic = props.leagueTypes.find(type => type.key === 'classic');
    if (classic) setValues(current => current.typeId ? current : { ...current, typeId: String(classic.id) });
  }, [props.leagueTypes]);

  const validation = useMemo(() => {
    const next: Record<string, string> = {};
    const maximum = Number(values.maximum);
    if (!values.name.trim()) next.name = t('leagueCreate.validation.nameRequired');
    else if (values.name.trim().length > 255) next.name = t('leagueCreate.validation.nameLength');
    if (values.description.length > 5000) next.description = t('leagueCreate.validation.descriptionLength');
    if (!values.seasonId) next.season_id = t('leagueCreate.validation.seasonRequired');
    if (!values.typeId) next.league_type_id = t('leagueCreate.validation.typeRequired');
    if (!Number.isInteger(maximum) || maximum < 2 || maximum > 100)
      next.max_participants = t('leagueCreate.validation.participants');
    return next;
  }, [t, values]);

  const fieldError = (field: string) => errors[field] ?? props.serverErrors[field]?.[0];
  const update = (change: Partial<Values>) => {
    setValues(current => ({ ...current, ...change }));
    setErrors({});
  };

  function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors(validation);
    if (Object.keys(validation).length) return;
    props.onSubmit({
      name: values.name.trim(),
      description: values.description.trim() || null,
      season_id: Number(values.seasonId),
      league_type_id: Number(values.typeId),
      max_participants: Number(values.maximum),
    });
  }

  function error(id: string, field: string) {
    const message = fieldError(field);
    return message ? <p className="mt-1 text-sm text-red-300" id={id}>{message}</p> : null;
  }

  return <form className="grid gap-5" noValidate onSubmit={submit}>
  <label className="text-sm text-slate-200">{t('leagueCreate.fields.name')}<input aria-describedby={fieldError('name') ? 'create-name-error' : undefined} className={inputClass} disabled={props.isSubmitting} maxLength={255} onChange={e => update({ name: e.target.value })} value={values.name}/>{error('create-name-error', 'name')}</label>
    <label className="text-sm text-slate-200">{t('leagueCreate.fields.description')}<textarea aria-describedby={fieldError('description') ? 'create-description-error' : undefined} className={inputClass} disabled={props.isSubmitting} maxLength={5000} onChange={e => update({ description: e.target.value })} rows={3} value={values.description}/>{error('create-description-error', 'description')}</label>
    <div className="grid gap-5 sm:grid-cols-2">
        <label className="text-sm text-slate-200">{t('leagueCreate.fields.season')}<select aria-describedby={fieldError('season_id') ? 'create-season-error' : undefined} className={inputClass} disabled={props.isSubmitting} onChange={e => update({ seasonId: e.target.value })} value={values.seasonId}><option value="">{t('leagueCreate.fields.selectSeason')}</option>{props.seasons.map(season => <option key={season.id} value={season.id}>{season.competition.name} — {season.name}</option>)}</select>{error('create-season-error', 'season_id')}</label>
        <label className="text-sm text-slate-200">{t('leagueCreate.fields.type')}<select aria-describedby={fieldError('league_type_id') ? 'create-type-error' : undefined} className={inputClass} disabled={props.isSubmitting} onChange={e => update({ typeId: e.target.value })} value={values.typeId}><option value="">{t('leagueCreate.fields.selectType')}</option>{props.leagueTypes.map(type => <option key={type.id} value={type.id}>{t(`leagueCreate.leagueTypes.${type.key}`) === `leagueCreate.leagueTypes.${type.key}` ? type.label : t(`leagueCreate.leagueTypes.${type.key}`)}</option>)}</select>{error('create-type-error', 'league_type_id')}</label>
    </div>
        <label className="text-sm text-slate-200">{t('leagueCreate.fields.maxParticipants')}<input aria-describedby={fieldError('max_participants') ? 'create-maximum-error' : undefined} className={inputClass} disabled={props.isSubmitting} min="2" max="100" step="1" type="number" onChange={e => update({ maximum: e.target.value })} value={values.maximum}/>{error('create-maximum-error', 'max_participants')}</label>
    <button className="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:cursor-not-allowed disabled:opacity-50" disabled={props.isSubmitting || Object.keys(validation).length > 0} type="submit">{props.isSubmitting ? t('leagueCreate.actions.creating') : t('leagueCreate.actions.create')}</button>
  </form>;
}