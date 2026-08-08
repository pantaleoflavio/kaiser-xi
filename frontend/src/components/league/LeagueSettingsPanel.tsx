import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, type SubmitEvent } from 'react';
import { ApiError } from '../../api/client';
import { leaguesApi } from '../../api/leagues';
import { leagueKeys, leagueMutationKeys } from '../../api/queryKeys';
import { useTranslation } from '../../i18n';
import type {
  League,
  LeagueSettings,
  LeagueSettingsPayload,
  LeagueSettingsResponse,
  PlayerRoleKey,
} from '../../types/league';
import { ContentErrorPanel } from '../feedback/ContentErrorPanel';

import { BenchRulesSection } from './settings/BenchRulesSection';
import { CaptainRulesSection } from './settings/CaptainRulesSection';
import { FormationRulesSection } from './settings/FormationRulesSection';
import { LeagueSettingsSummary } from './settings/LeagueSettingsSummary';
import {
  createLeagueSettingsFormState,
  updateRoleLimit,
  validateLeagueSettingsForm,
  type LeagueSettingsFormState,
  type SettingsFieldErrors,
} from './settings/leagueSettingsForm';
import { RosterRulesSection } from './settings/RosterRulesSection';
import { SubstitutionRulesSection } from './settings/SubstitutionRulesSection';

type Props = {
  league: League;
  initialSettings: LeagueSettings | null;
  initialError: string | null;
};

export function LeagueSettingsPanel({ league, initialSettings, initialError }: Props) {
  const { language, t } = useTranslation();
  const queryClient = useQueryClient();
  const [settings, setSettings] = useState(initialSettings);
  const [form, setForm] = useState(() => createLeagueSettingsFormState(initialSettings));
  const [error, setError] = useState(initialError);
  const [fieldErrors, setFieldErrors] = useState<SettingsFieldErrors>({});
  const [success, setSuccess] = useState<string | null>(null);
 
  const canEdit = settings?.can_update_settings ?? false;

  const updateSettings = useMutation({
    mutationKey: leagueMutationKeys.settings(league.id),
    mutationFn: (payload: LeagueSettingsPayload) => leaguesApi.updateSettings(league.id, payload),
    onSuccess: (response) => {
      queryClient.setQueryData<LeagueSettingsResponse>(leagueKeys.settings(league.id), response);
      setSettings(response.data);
      setForm(createLeagueSettingsFormState(response.data));
      setSuccess(t('leagueSettings.success'));
    },
    onError: (updateError) => {
      if (updateError instanceof ApiError) {
        setFieldErrors(updateError.status === 422 ? (updateError.errors ?? {}) : {});
        if (updateError.status === 403) setError(t('common.errors.forbidden'));
        else if (updateError.status === 409) setError(t('leagueSettings.errors.conflict'));
        else if (updateError.status === 422) setError(t('common.errors.validation'));
        else setError(t('leagueSettings.errors.update'));
      } else {
        setFieldErrors({});
        setError(t('leagueSettings.errors.update'));
      }
    },
  });

  function setField<Key extends keyof LeagueSettingsFormState>(
    key: Key,
    value: LeagueSettingsFormState[Key],
  ) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function setRoleLimit(
    key: 'rosterRoleLimits' | 'benchRoleLimits',
    role: PlayerRoleKey,
    value: string,
  ) {
    setForm((current) => ({ ...current, [key]: updateRoleLimit(current[key], role, value) }));
  }

  function toggleFormation(name: string) {
    setForm((current) => ({
      ...current,
      formationNames: current.formationNames.includes(name)
        ? current.formationNames.filter((value) => value !== name)
        : [...current.formationNames, name],
    }));
  }

  function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!canEdit) return;
    
    const result = validateLeagueSettingsForm(form, t);
    if (!result.payload) {
      setError(t('common.errors.validation'));
      setFieldErrors(result.errors);
      setSuccess(t('leagueSettings.success'));
      return;
    }
    setError(null);
    setFieldErrors({});
    setSuccess(null);
    updateSettings.mutate(result.payload);
  }

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <h2 className="text-2xl font-semibold text-white">{t('leagueSettings.title')}</h2>
      <p className="mt-1 text-sm text-slate-300">{t('leagueSettings.description')}</p>
      {error ? (
        <div className="mt-4">
            <ContentErrorPanel message={error} title={t('leagueSettings.errors.title')} />
        </div>
      ) : null}
      {success ? (
        <div
          className="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-950/30 p-4 text-sm text-emerald-100"
          role="status"
        >
          {success}
        </div>
      ) : null}
      <LeagueSettingsSummary locale={language} settings={settings} t={t} />
      {canEdit ? (
         <form className="mt-6 grid gap-6" noValidate onSubmit={handleSubmit}>
          <RosterRulesSection
            disabled={updateSettings.isPending}
            errors={fieldErrors}
            initialBudget={form.initialBudget}
            maxRosterPlayers={form.maxRosterPlayers}
            onInitialBudgetChange={(value) => setField('initialBudget', value)}
            onMaxRosterPlayersChange={(value) => setField('maxRosterPlayers', value)}
            onRefundChange={(value) => setField('refund', value)}
            onRoleLimitChange={(role, value) => setRoleLimit('rosterRoleLimits', role, value)}
            refund={form.refund}
            roleLimits={form.rosterRoleLimits}
            t={t}
          />
          <FormationRulesSection
            availableNames={settings?.allowed_formation_module_names ?? []}
            disabled={updateSettings.isPending}
            errors={fieldErrors}
            modules={settings?.allowed_formation_modules ?? []}
            onToggle={toggleFormation}
            selectedNames={form.formationNames}
            t={t}
          />
          <BenchRulesSection
            benchSize={form.benchSize}
            disabled={updateSettings.isPending}
            errors={fieldErrors}
            onBenchSizeChange={(value) => setField('benchSize', value)}
            onRoleLimitChange={(role, value) => setRoleLimit('benchRoleLimits', role, value)}
            roleLimits={form.benchRoleLimits}
            t={t}
          />
          <SubstitutionRulesSection
            allowFormationChange={form.allowFormationChange}
            disabled={updateSettings.isPending}
            errors={fieldErrors}
            maxSubstitutions={form.maxSubstitutions}
            mode={form.substitutionMode}
            onAllowFormationChange={(value) => setField('allowFormationChange', value)}
            onMaximumChange={(value) => setField('maxSubstitutions', value)}
            onModeChange={(value) => setField('substitutionMode', value)}
            t={t}
          />
          <CaptainRulesSection
            captainEnabled={form.captainEnabled}
            disabled={updateSettings.isPending}
            errors={fieldErrors}
            onCaptainChange={(value) => setField('captainEnabled', value)}
            t={t}
          />
          <button
            className="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
            disabled={updateSettings.isPending}
            type="submit"
          >
                {updateSettings.isPending ? t('leagueSettings.saving') : t('leagueSettings.save')}
          </button>
        </form>
      ) : (
        <p className="mt-4 text-sm text-slate-400">{t('leagueSettings.readOnly')}</p>
      )}
    </section>
  );
}