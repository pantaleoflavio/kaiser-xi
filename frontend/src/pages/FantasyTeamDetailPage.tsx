import { FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';
import { LoadingState } from '../components/LoadingState';
import { useTranslation } from '../i18n';
import type { FantasyTeam } from '../types/league';

type ErrorState = {
  message: string;
  details: string[];
};

function ErrorPanel({ error, title }: { error: ErrorState; title: string }) {
  return (
    <div className="rounded-xl border border-red-500/30 bg-red-950/40 p-4 text-sm text-red-100">
      <p className="font-semibold">{title}</p>
      <p className="mt-1 text-red-100/80">{error.message}</p>
      {error.details.length > 0 ? (
        <ul className="mt-3 list-disc space-y-1 pl-5 text-red-100/80">
          {error.details.map((detail) => (
            <li key={detail}>{detail}</li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}

function validationDetails(error: unknown) {
  if (!(error instanceof ApiError) || !error.errors) return [];
  return Object.values(error.errors).flat();
}

function formatDate(value: string | null, fallback: string, locale: string) {
  if (!value) return fallback;
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(value),
  );
}

export function FantasyTeamDetailPage() {
  const { fantasyTeamId, leagueId } = useParams();
  const { language, t } = useTranslation();
  const [team, setTeam] = useState<FantasyTeam | null>(null);
  const [teamName, setTeamName] = useState('');
  const [loadError, setLoadError] = useState<ErrorState | null>(null);
  const [updateError, setUpdateError] = useState<ErrorState | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isUpdating, setIsUpdating] = useState(false);

  useEffect(() => {
    if (!leagueId || !fantasyTeamId) return;
    const currentLeagueId = leagueId;
    const currentFantasyTeamId = fantasyTeamId;
    let isMounted = true;

    async function loadFantasyTeam() {
      try {
        setIsLoading(true);
        setLoadError(null);
        setUpdateError(null);
        setSuccessMessage(null);
        const response = await leaguesApi.fantasyTeam(currentLeagueId, currentFantasyTeamId);
        if (!isMounted) return;
        setTeam(response.data);
        setTeamName(response.data.name);
      } catch (err) {
        if (!isMounted) return;
        setLoadError({
          details: validationDetails(err),
          message: err instanceof Error ? err.message : t('fantasyTeams.errors.detail'),
        });
      } finally {
        if (isMounted) setIsLoading(false);
      }
    }

    void loadFantasyTeam();
    return () => {
      isMounted = false;
    };
  }, [fantasyTeamId, leagueId]);

  async function handleUpdateTeam(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!leagueId || !fantasyTeamId || !team) return;

    const trimmedName = teamName.trim();
    try {
      setIsUpdating(true);
      setUpdateError(null);
      setSuccessMessage(null);
      const response = await leaguesApi.updateFantasyTeam(leagueId, fantasyTeamId, { name: trimmedName });
      setTeam(response.data);
      setTeamName(response.data.name);
      setSuccessMessage(t('fantasyTeams.update.success'));
    } catch (err) {
      setUpdateError({
        details: validationDetails(err),
        message: err instanceof Error ? err.message : t('fantasyTeams.errors.update'),
      });
    } finally {
      setIsUpdating(false);
    }
  }

  if (isLoading) return <LoadingState message={t('fantasyTeams.detail.loading')} />;

  return (
    <section className="space-y-6">
      <Link className="text-sm font-semibold text-emerald-300 hover:text-emerald-200" to={`/leagues/${leagueId}`}>
        {t('fantasyTeams.detail.backToLeague')}
      </Link>

      {loadError ? <ErrorPanel error={loadError} title={t('fantasyTeams.errors.detailTitle')} /> : null}

      {team ? (
        <div className="space-y-6">
          <header className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
                  {t('fantasyTeams.detail.eyebrow')}
                </p>
                <h1 className="mt-2 text-4xl font-bold text-white">{team.name}</h1>
                <p className="mt-3 text-slate-300">
                  {t('fantasyTeams.detail.owner', { name: team.owner.name })}
                </p>
              </div>
              {team.is_owned_by_current_user ? (
                <span className="rounded-full border border-emerald-400/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-200">
                  {t('fantasyTeams.detail.ownedByYou')}
                </span>
              ) : null}
            </div>
            <dl className="mt-6 grid gap-3 text-sm text-slate-300 md:grid-cols-4">
              <div>
                <dt className="text-slate-500">{t('fantasyTeams.detail.fields.slug')}</dt>
                <dd>{team.slug}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('fantasyTeams.detail.fields.leagueId')}</dt>
                <dd>{team.league_id}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('fantasyTeams.detail.fields.createdAt')}</dt>
                <dd>{formatDate(team.created_at, t('leagueDetail.notAvailable'), language)}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('fantasyTeams.detail.fields.updatedAt')}</dt>
                <dd>{formatDate(team.updated_at, t('leagueDetail.notAvailable'), language)}</dd>
              </div>
            </dl>
          </header>

          {team.is_owned_by_current_user ? (
            <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
              <h2 className="text-2xl font-semibold text-white">{t('fantasyTeams.update.title')}</h2>
              <p className="mt-1 text-sm text-slate-300">{t('fantasyTeams.update.description')}</p>
              {successMessage ? (
                <div className="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-950/30 p-4 text-sm text-emerald-100">
                  {successMessage}
                </div>
              ) : null}
              {updateError ? (
                <div className="mt-4">
                  <ErrorPanel error={updateError} title={t('fantasyTeams.errors.updateTitle')} />
                </div>
              ) : null}
              <form className="mt-4 grid gap-3 md:grid-cols-[1fr_auto]" onSubmit={handleUpdateTeam}>
                <label className="text-sm text-slate-300">
                  {t('fantasyTeams.update.name')}
                  <input
                    className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
                    maxLength={100}
                    onChange={(event) => setTeamName(event.target.value)}
                    type="text"
                    value={teamName}
                  />
                </label>
                <button
                  className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
                  disabled={isUpdating}
                  type="submit"
                >
                  {isUpdating ? t('fantasyTeams.update.submitting') : t('fantasyTeams.update.submit')}
                </button>
              </form>
            </section>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}