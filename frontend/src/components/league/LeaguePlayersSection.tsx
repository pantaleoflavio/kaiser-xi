import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { leaguesApi } from '../../api/leagues';
import { leagueKeys } from '../../api/queryKeys';
import { useTranslation } from '../../i18n';
import { ContentEmptyPanel } from '../feedback/ContentEmptyPanel';
import { ContentErrorPanel } from '../feedback/ContentErrorPanel';
import { LoadingState } from '../LoadingState';

export function LeaguePlayersSection({
  leagueId,
  seasonId,
}: {
  leagueId: string;
  seasonId: number;
}) {
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const [clubId, setClubId] = useState('');
  const [position, setPosition] = useState('');
  const [assignmentState, setAssignmentState] = useState('');
  const [fantasyTeamId, setFantasyTeamId] = useState('');
  const [page, setPage] = useState(1);
  const filters = {
    search,
    club_id: clubId ? Number(clubId) : undefined,
    role: position || undefined,
    assignment_state: assignmentState || undefined,
    fantasy_team_id: fantasyTeamId ? Number(fantasyTeamId) : undefined,
    page,
    per_page: 20,
  };
  const players = useQuery({
    queryKey: leagueKeys.players(leagueId, filters),
    queryFn: () => leaguesApi.players(leagueId, filters),
    placeholderData: keepPreviousData,
  });
  const teams = useQuery({
    queryKey: leagueKeys.fantasyTeams(leagueId),
    queryFn: () => leaguesApi.fantasyTeams(leagueId),
  });
  const resetPage = () => setPage(1);
  const selectClass = 'rounded-lg border border-theme-border bg-theme-surface p-2 text-sm';

  return (
    <section className="space-y-4 rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
      <div>
        <h2 className="text-2xl font-semibold text-theme-text">{t('leaguePlayers.title')}</h2>
        <p className="mt-1 text-sm text-theme-muted">
          {t('leaguePlayers.count', { count: players.data?.meta.total ?? 0 })}
        </p>
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input
          className={selectClass}
          aria-label={t('leaguePlayers.search')}
          placeholder={t('leaguePlayers.search')}
          value={search}
          onChange={(event) => {
            setSearch(event.target.value);
            resetPage();
          }}
        />
        <select
          className={selectClass}
          aria-label={t('leaguePlayers.club')}
          value={clubId}
          onChange={(event) => {
            setClubId(event.target.value);
            resetPage();
          }}
        >
          <option value="">{t('leaguePlayers.allClubs')}</option>
          {players.data?.filter_options.clubs.map((club) => (
            <option key={club.id} value={club.id}>
              {club.name}
            </option>
          ))}
        </select>
        <select
          className={selectClass}
          aria-label={t('leaguePlayers.position')}
          value={position}
          onChange={(event) => {
            setPosition(event.target.value);
            resetPage();
          }}
        >
          <option value="">{t('leaguePlayers.allPositions')}</option>
          {players.data?.filter_options.positions.map((role) => (
            <option key={role.key} value={role.key}>
              {role.label}
            </option>
          ))}
        </select>
        <select
          className={selectClass}
          aria-label={t('leaguePlayers.status')}
          value={assignmentState}
          onChange={(event) => {
            setAssignmentState(event.target.value);
            resetPage();
          }}
        >
          <option value="">{t('leaguePlayers.allStatuses')}</option>
          <option value="unassigned">{t('leaguePlayers.freeAgent')}</option>
          <option value="assigned">{t('leaguePlayers.assigned')}</option>
        </select>
        <select
          className={selectClass}
          aria-label={t('leaguePlayers.fantasyTeam')}
          value={fantasyTeamId}
          onChange={(event) => {
            setFantasyTeamId(event.target.value);
            resetPage();
          }}
        >
          <option value="">{t('leaguePlayers.allFantasyTeams')}</option>
          {teams.data?.data.map((team) => (
            <option key={team.id} value={team.id}>
              {team.name}
            </option>
          ))}
        </select>
      </div>
      {players.isLoading ? (
        <LoadingState message={t('common.loading')} />
      ) : players.isError ? (
        <ContentErrorPanel title={t('leaguePlayers.title')} message={players.error.message} />
      ) : players.data?.data.length ? (
        <div className="divide-y divide-theme-border overflow-hidden rounded-xl border border-theme-border">
          {players.data.data.map((player) => (
            <Link
              key={player.id}
              to={`/players/${player.id}?season=${seasonId}`}
              className="grid gap-1 bg-theme-surface/40 p-4 transition hover:bg-theme-muted-surface sm:grid-cols-4 sm:items-center"
            >
              <strong className="text-theme-text">{player.name}</strong>
              <span className="text-sm text-theme-muted">{player.club.name}</span>
              <span className="text-sm text-theme-muted">{player.position.label}</span>
              <span className="text-sm font-semibold text-theme-accent">
                {player.is_free_agent ? t('leaguePlayers.freeAgent') : player.fantasy_team?.name}
              </span>
            </Link>
          ))}
        </div>
      ) : (
        <ContentEmptyPanel
          title={t('leaguePlayers.emptyTitle')}
          message={t('leaguePlayers.empty')}
        />
      )}
      <div className="flex justify-end gap-2">
        <button
          className={selectClass}
          disabled={page <= 1}
          onClick={() => setPage((value) => value - 1)}
        >
          {t('common.previous')}
        </button>
        <button
          className={selectClass}
          disabled={!players.data?.links.next}
          onClick={() => setPage((value) => value + 1)}
        >
          {t('common.next')}
        </button>
      </div>
    </section>
  );
}
