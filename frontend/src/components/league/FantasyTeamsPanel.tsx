import { SubmitEvent, useState } from 'react';
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
  creationLocked = false,
}: {
  league: League;
  initialTeams: FantasyTeam[];
  initialError: string | null;
  creationLocked?: boolean;
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
  async function submit(event: SubmitEvent<HTMLFormElement>) {
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
    <section className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-theme-text">{t('fantasyTeams.list.title')}</h2>
          <p className="mt-1 text-sm text-theme-muted">{t('fantasyTeams.list.description')}</p>
        </div>
        {ownedTeam ? (
          <span className="rounded-full border border-theme-primary/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-theme-accent">
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
              className="rounded-xl border border-theme-border bg-theme-background/60 p-4 transition hover:border-theme-primary/40"
              key={team.id}
              to={`/leagues/${league.id}/fantasy-teams/${team.id}`}
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h3 className="font-semibold text-theme-text">{team.name}</h3>
                  <p className="mt-1 text-sm text-theme-muted">
                    {t('fantasyTeams.list.owner', { name: team.owner.name })}
                  </p>
                </div>
                {team.is_owned_by_current_user ? (
                  <span className="rounded-full border border-theme-primary/30 px-2 py-1 text-xs font-semibold text-theme-accent">
                    {t('fantasyTeams.list.ownedByYou')}
                  </span>
                ) : null}
              </div>
            </Link>
          ))}
        </div>
      ) : null}
      {!ownedTeam && creationLocked ? (
        <p className="mt-6 rounded-xl border border-amber-400/30 bg-amber-950/30 p-4 text-sm text-amber-100">
          {t('h2h.participantsBlocked')}
        </p>
      ) : null}
      {!ownedTeam && !creationLocked ? (
        <form
          className="mt-6 rounded-xl border border-theme-border bg-theme-background/60 p-4"
          onSubmit={submit}
        >
          <h3 className="text-lg font-semibold text-theme-text">
            {t('fantasyTeams.create.title')}
          </h3>
          <p className="mt-1 text-sm text-theme-muted">{t('fantasyTeams.create.description')}</p>
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
            <label className="text-sm text-theme-muted">
              {t('fantasyTeams.create.name')}
              <input
                className="mt-1 w-full rounded-lg border border-theme-border bg-theme-background px-3 py-2 text-theme-text"
                maxLength={100}
                onChange={(e) => setName(e.target.value)}
                placeholder={t('fantasyTeams.create.namePlaceholder')}
                type="text"
                value={name}
              />
            </label>
            <button
              className="self-end rounded-lg bg-theme-primary px-4 py-2 font-semibold text-theme-primary-foreground disabled:opacity-60"
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
