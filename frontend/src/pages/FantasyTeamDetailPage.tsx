import { FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import { leaguesApi } from '../api/leagues';
import { LoadingState } from '../components/LoadingState';
import { useTranslation } from '../i18n';
import type { FantasyTeam, LeagueSettings, RosterPlayer } from '../types/league';

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

function errorMessage(error: unknown, fallback: string, t: (key: string) => string) {
  if (error instanceof ApiError) {
    if (error.status === 403) return t('common.errors.forbidden');
    if (error.status === 404) return t('common.errors.notFound');
    if (error.status === 409) return t('common.errors.conflict');
    if (error.status === 422) return t('common.errors.validation');
    return error.message;
  }
  return error instanceof Error ? error.message : fallback;
}

function formatMoney(value: string | number | null | undefined, fallback: string, locale: string) {
  if (value === null || value === undefined) return fallback;
  return new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(Number(value));
}

function rosterPlayerName(rosterPlayer: RosterPlayer, fallback: string) {
  return (
    rosterPlayer.player?.display_name ||
    [rosterPlayer.player?.first_name, rosterPlayer.player?.last_name].filter(Boolean).join(' ') ||
    rosterPlayer.player?.slug ||
    fallback
  );
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
  const [settings, setSettings] = useState<LeagueSettings | null>(null);
  const [rosterPlayers, setRosterPlayers] = useState<RosterPlayer[]>([]);
  const [rosterMeta, setRosterMeta] = useState<{
    budget?: string | number | null;
    total_budget?: string | number | null;
    remaining_budget?: string | number | null;
  } | null>(null);
  const [teamName, setTeamName] = useState('');
  const [loadError, setLoadError] = useState<ErrorState | null>(null);
  const [updateError, setUpdateError] = useState<ErrorState | null>(null);
  const [rosterError, setRosterError] = useState<ErrorState | null>(null);
  const [assignError, setAssignError] = useState<ErrorState | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isUpdating, setIsUpdating] = useState(false);
  const [isRosterLoading, setIsRosterLoading] = useState(true);
  const [isAssigning, setIsAssigning] = useState(false);
  const [releasingPlayerId, setReleasingPlayerId] = useState<number | null>(null);
  const [playerId, setPlayerId] = useState('');
  const [purchasePrice, setPurchasePrice] = useState('');

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
        const [response, settingsResponse, rosterResponse] = await Promise.all([
          leaguesApi.fantasyTeam(currentLeagueId, currentFantasyTeamId),
          leaguesApi.settings(currentLeagueId),
          leaguesApi.rosterPlayers(currentLeagueId, currentFantasyTeamId),
        ]);
        if (!isMounted) return;
        setTeam(response.data);
        setSettings(settingsResponse.data);
        setRosterPlayers(rosterResponse.data);
        setRosterMeta(rosterResponse.meta ?? null);
        setTeamName(response.data.name);
      } catch (err) {
        if (!isMounted) return;
        setLoadError({
          details: validationDetails(err),
          message: errorMessage(err, t('fantasyTeams.errors.detail'), t),
        });
      } finally {
        if (isMounted) {
          setIsLoading(false);
          setIsRosterLoading(false);
        }
      }
    }

    void loadFantasyTeam();
    return () => {
      isMounted = false;
    };
  }, [fantasyTeamId, leagueId]);

  async function reloadRoster() {
    if (!leagueId || !fantasyTeamId) return;
    const [teamResponse, rosterResponse] = await Promise.all([
      leaguesApi.fantasyTeam(leagueId, fantasyTeamId),
      leaguesApi.rosterPlayers(leagueId, fantasyTeamId),
    ]);
    setTeam(teamResponse.data);
    setRosterPlayers(rosterResponse.data);
    setRosterMeta(rosterResponse.meta ?? null);
  }

  async function handleAssignPlayer(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!leagueId || !fantasyTeamId || !team?.is_owned_by_current_user) return;

    try {
      setIsAssigning(true);
      setAssignError(null);
      await leaguesApi.assignPlayer(leagueId, fantasyTeamId, {
        player_id: Number(playerId),
        purchase_price: Number(purchasePrice),
      });
      setPlayerId('');
      setPurchasePrice('');
      await reloadRoster();
    } catch (err) {
      setAssignError({
        details: validationDetails(err),
        message: errorMessage(err, t('roster.errors.assign'), t),
      });
    } finally {
      setIsAssigning(false);
    }
  }

  async function handleReleasePlayer(rosterPlayer: RosterPlayer) {
    if (!leagueId || !fantasyTeamId || !team?.is_owned_by_current_user) return;
    const confirmed = window.confirm(
      t('common.confirmations.releasePlayer', {
        percent: formatMoney(
          settings?.release_refund_percentage,
          t('leagueDetail.notAvailable'),
          language,
        ),
      }),
    );
    if (!confirmed) return;

    try {
      setReleasingPlayerId(rosterPlayer.id);
      setRosterError(null);
      await leaguesApi.releasePlayer(leagueId, fantasyTeamId, rosterPlayer.player_id);
      await reloadRoster();
    } catch (err) {
      setRosterError({
        details: validationDetails(err),
        message: errorMessage(err, t('roster.errors.release'), t),
      });
    } finally {
      setReleasingPlayerId(null);
    }
  }

  async function handleUpdateTeam(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!leagueId || !fantasyTeamId || !team) return;

    const trimmedName = teamName.trim();
    try {
      setIsUpdating(true);
      setUpdateError(null);
      setSuccessMessage(null);
      const response = await leaguesApi.updateFantasyTeam(leagueId, fantasyTeamId, {
        name: trimmedName,
      });
      setTeam(response.data);
      setTeamName(response.data.name);
      setSuccessMessage(t('fantasyTeams.update.success'));
    } catch (err) {
      setUpdateError({
        details: validationDetails(err),
        message: errorMessage(err, t('fantasyTeams.errors.update'), t),
      });
    } finally {
      setIsUpdating(false);
    }
  }

  if (isLoading) return <LoadingState message={t('fantasyTeams.detail.loading')} />;

  return (
    <section className="space-y-6">
      <Link
        className="text-sm font-semibold text-emerald-300 hover:text-emerald-200"
        to={`/leagues/${leagueId}`}
      >
        {t('fantasyTeams.detail.backToLeague')}
      </Link>

      {loadError ? (
        <ErrorPanel error={loadError} title={t('fantasyTeams.errors.detailTitle')} />
      ) : null}

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

          <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h2 className="text-2xl font-semibold text-white">{t('budget.title')}</h2>
            <dl className="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-2">
              <div>
                <dt className="text-slate-500">{t('budget.totalBudget')}</dt>
                <dd>
                  {formatMoney(
                    rosterMeta?.total_budget ?? rosterMeta?.budget ?? team.budget,
                    t('leagueDetail.notAvailable'),
                    language,
                  )}
                </dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('budget.remainingBudget')}</dt>
                <dd>
                  {formatMoney(
                    rosterMeta?.remaining_budget ?? team.remaining_budget,
                    t('leagueDetail.notAvailable'),
                    language,
                  )}
                </dd>
              </div>
            </dl>
          </section>

          <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h2 className="text-2xl font-semibold text-white">{t('roster.title')}</h2>
            <p className="mt-1 text-sm text-slate-300">{t('roster.description')}</p>
            {isRosterLoading ? (
              <p className="mt-4 text-sm text-slate-300">{t('roster.loading')}</p>
            ) : null}
            {rosterError ? (
              <div className="mt-4">
                <ErrorPanel error={rosterError} title={t('roster.errors.title')} />
              </div>
            ) : null}
            {!isRosterLoading && !rosterError && rosterPlayers.length === 0 ? (
              <div className="mt-4 rounded-xl border border-slate-800 bg-slate-950/60 p-5 text-center text-sm text-slate-300">
                <p className="font-semibold text-white">{t('roster.emptyTitle')}</p>
                <p className="mt-2">{t('roster.emptyDescription')}</p>
              </div>
            ) : null}
            {!isRosterLoading && rosterPlayers.length > 0 ? (
              <div className="mt-4 grid gap-3">
                {rosterPlayers.map((rosterPlayer) => (
                  <div
                    className="rounded-xl border border-slate-800 bg-slate-950/60 p-4"
                    key={rosterPlayer.id}
                  >
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                      <div>
                        <h3 className="font-semibold text-white">
                          {rosterPlayerName(rosterPlayer, t('roster.unknownPlayer'))}
                        </h3>
                        <p className="mt-1 text-sm text-slate-400">
                          {t('roster.playerId', { id: rosterPlayer.player_id })}
                        </p>
                      </div>
                      {team.is_owned_by_current_user && !rosterPlayer.released_at ? (
                        <button
                          className="rounded-lg border border-red-400/40 px-3 py-2 text-sm font-semibold text-red-100 disabled:opacity-60"
                          disabled={releasingPlayerId === rosterPlayer.id}
                          onClick={() => void handleReleasePlayer(rosterPlayer)}
                          type="button"
                        >
                          {releasingPlayerId === rosterPlayer.id
                            ? t('roster.release.releasing')
                            : t('roster.release.submit')}
                        </button>
                      ) : null}
                    </div>
                    <dl className="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-3">
                      <div>
                        <dt className="text-slate-500">{t('roster.purchasePrice')}</dt>
                        <dd>
                          {formatMoney(
                            rosterPlayer.purchase_price,
                            t('leagueDetail.notAvailable'),
                            language,
                          )}
                        </dd>
                      </div>
                      <div>
                        <dt className="text-slate-500">{t('roster.assignedAt')}</dt>
                        <dd>
                          {formatDate(
                            rosterPlayer.assigned_at,
                            t('leagueDetail.notAvailable'),
                            language,
                          )}
                        </dd>
                      </div>
                      <div>
                        <dt className="text-slate-500">{t('roster.releasedAt')}</dt>
                        <dd>
                          {formatDate(
                            rosterPlayer.released_at,
                            t('leagueDetail.notAvailable'),
                            language,
                          )}
                        </dd>
                      </div>
                    </dl>
                  </div>
                ))}
              </div>
            ) : null}

            {team.is_owned_by_current_user ? (
              <form
                className="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4"
                onSubmit={handleAssignPlayer}
              >
                <h3 className="text-lg font-semibold text-white">{t('roster.assign.title')}</h3>
                <p className="mt-1 text-sm text-amber-200">{t('roster.assign.endpointBlocked')}</p>
                {assignError ? (
                  <div className="mt-4">
                    <ErrorPanel error={assignError} title={t('roster.errors.assignTitle')} />
                  </div>
                ) : null}
                <div className="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                  <label className="text-sm text-slate-300">
                    {t('roster.assign.player')}
                    <input
                      className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
                      min="1"
                      onChange={(event) => setPlayerId(event.target.value)}
                      placeholder={t('roster.assign.playerPlaceholder')}
                      type="number"
                      value={playerId}
                    />
                  </label>
                  <label className="text-sm text-slate-300">
                    {t('roster.assign.purchasePrice')}
                    <input
                      className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
                      min="0"
                      onChange={(event) => setPurchasePrice(event.target.value)}
                      step="0.01"
                      type="number"
                      value={purchasePrice}
                    />
                  </label>
                  <button
                    className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60"
                    disabled={isAssigning}
                    type="submit"
                  >
                    {isAssigning ? t('roster.assign.submitting') : t('roster.assign.submit')}
                  </button>
                </div>
              </form>
            ) : (
              <p className="mt-4 text-sm text-slate-400">{t('roster.managementReadOnly')}</p>
            )}
          </section>

          {team.is_owned_by_current_user ? (
            <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
              <h2 className="text-2xl font-semibold text-white">
                {t('fantasyTeams.update.title')}
              </h2>
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
                  {isUpdating
                    ? t('fantasyTeams.update.submitting')
                    : t('fantasyTeams.update.submit')}
                </button>
              </form>
            </section>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}