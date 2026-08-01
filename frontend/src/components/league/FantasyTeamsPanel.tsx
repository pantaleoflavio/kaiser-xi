import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiError } from '../../api/client';
import { leaguesApi } from '../../api/leagues';
import { useTranslation } from '../../i18n';
import type { FantasyTeam, League } from '../../types/league';
import { ContentEmptyPanel } from '../feedback/ContentEmptyPanel';
import { ContentErrorPanel } from '../feedback/ContentErrorPanel';

function validationDetails(error: unknown) {
  return error instanceof ApiError && error.errors ? Object.values(error.errors).flat() : [];
}
export function FantasyTeamsPanel({
  league,
  initialTeams,
  initialError,
}: {
  league: League;
  initialTeams: FantasyTeam[];
  initialError: string | null;
}) {
  const { t } = useTranslation();
  const [teams, setTeams] = useState(initialTeams);
  const [listError, setListError] = useState(initialError);
  const [createError, setCreateError] = useState<string | null>(null);
  const [details, setDetails] = useState<string[]>([]);
  const [name, setName] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const ownedTeam = teams.find((team) => team.is_owned_by_current_user) ?? null;
  async function reload() {
    try {
      setListError(null);
      setTeams((await leaguesApi.fantasyTeams(league.id)).data);
    } catch (error) {
      setListError(error instanceof Error ? error.message : t('fantasyTeams.errors.list'));
    }
  }
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    try {
      setIsCreating(true);
      setCreateError(null);
      setDetails([]);
      const response = await leaguesApi.createFantasyTeam(league.id, { name: name.trim() });
      setName('');
      setTeams((current) =>
        [...current.filter((team) => team.id !== response.data.id), response.data].sort((a, b) =>
          a.name.localeCompare(b.name),
        ),
      );
    } catch (error) {
      setCreateError(error instanceof Error ? error.message : t('fantasyTeams.errors.create'));
      setDetails(validationDetails(error));
      if (error instanceof ApiError && error.status === 409) void reload();
    } finally {
      setIsCreating(false);
    }
  }
  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-white">{t('fantasyTeams.list.title')}</h2>
          <p className="mt-1 text-sm text-slate-300">{t('fantasyTeams.list.description')}</p>
        </div>
        {ownedTeam ? (
          <span className="rounded-full border border-emerald-400/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-200">
            {t('fantasyTeams.list.youOwnTeam')}
          </span>
        ) : null}
      </div>
      {listError ? (
        <div className="mt-4">
          <ContentErrorPanel message={listError} title={t('fantasyTeams.errors.listTitle')} />
        </div>
      ) : null}
      {!listError && teams.length === 0 ? (
        <div className="mt-4">
          <ContentEmptyPanel
            message={t('fantasyTeams.list.emptyDescription')}
            title={t('fantasyTeams.list.emptyTitle')}
          />
        </div>
      ) : null}
      {!listError && teams.length > 0 ? (
        <div className="mt-4 grid gap-3 md:grid-cols-2">
          {teams.map((team) => (
            <Link
              className="rounded-xl border border-slate-800 bg-slate-950/60 p-4 transition hover:border-emerald-400/40"
              key={team.id}
              to={`/leagues/${league.id}/fantasy-teams/${team.id}`}
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h3 className="font-semibold text-white">{team.name}</h3>
                  <p className="mt-1 text-sm text-slate-400">
                    {t('fantasyTeams.list.owner', { name: team.owner.name })}
                  </p>
                </div>
                {team.is_owned_by_current_user ? (
                  <span className="rounded-full border border-emerald-400/30 px-2 py-1 text-xs font-semibold text-emerald-200">
                    {t('fantasyTeams.list.ownedByYou')}
                  </span>
                ) : null}
              </div>
            </Link>
          ))}
        </div>
      ) : null}
      {!ownedTeam ? (
        <form
          className="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4"
          onSubmit={submit}
        >
          <h3 className="text-lg font-semibold text-white">{t('fantasyTeams.create.title')}</h3>
          <p className="mt-1 text-sm text-slate-300">{t('fantasyTeams.create.description')}</p>
          {createError ? (
            <div className="mt-4">
              <ContentErrorPanel
                details={details}
                message={createError}
                title={t('fantasyTeams.errors.createTitle')}
              />
            </div>
          ) : null}
          <div className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
            <label className="text-sm text-slate-300">
              {t('fantasyTeams.create.name')}
              <input
                className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
                maxLength={100}
                onChange={(e) => setName(e.target.value)}
                placeholder={t('fantasyTeams.create.namePlaceholder')}
                type="text"
                value={name}
              />
            </label>
            <button
              className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
              disabled={isCreating}
              type="submit"
            >
              {isCreating ? t('fantasyTeams.create.submitting') : t('fantasyTeams.create.submit')}
            </button>
          </div>
        </form>
      ) : null}
    </section>
  );
}