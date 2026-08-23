import { NavLink } from 'react-router-dom';
import { useTranslation } from '../../i18n';

const linkClass = ({ isActive }: { isActive: boolean }) =>
  `rounded-lg px-3 py-2 text-sm font-semibold transition ${
    isActive
      ? 'bg-theme-primary text-theme-primary-foreground'
      : 'text-theme-muted hover:bg-theme-muted-surface hover:text-theme-text'
  }`;

export function LeagueNavigation({
  leagueId,
  myTeamId,
  showSchedule = false,
  showStandings = false,
}: {
  leagueId: string;
  myTeamId?: number;
  showSchedule?: boolean;
  showStandings?: boolean;
}) {
  const { t } = useTranslation();
  const base = `/leagues/${leagueId}`;
  return (
    <nav
      aria-label={t('leagueNavigation.label')}
      className="flex flex-wrap gap-2 rounded-xl border border-theme-border bg-theme-surface/70 p-2"
    >
      <NavLink className={linkClass} end to={base}>
        {t('leagueNavigation.overview')}
      </NavLink>
      <NavLink className={linkClass} to={`${base}/matchdays`}>
        {t('leagueNavigation.matchdays')}
      </NavLink>
      {showSchedule ? (
        <NavLink className={linkClass} to={`${base}/head-to-head-schedule`}>
          {t('h2h.schedule')}
        </NavLink>
      ) : null}
      {showStandings ? (
        <NavLink className={linkClass} to={`${base}/standings`}>
          {t('standings.title')}
        </NavLink>
      ) : null}
      <NavLink className={linkClass} to={`${base}/rules`}>
        {t('leagueNavigation.rules')}
      </NavLink>
      <NavLink className={linkClass} to={`${base}/market`}>
        {t('market.title')}
      </NavLink>
      <NavLink className={linkClass} to={`${base}/fantasy-teams`}>
        {t('leagueNavigation.fantasyTeams')}
      </NavLink>
      {myTeamId ? (
        <NavLink className={linkClass} to={`${base}/fantasy-teams/${myTeamId}`}>
          {t('leagueNavigation.myTeam')}
        </NavLink>
      ) : null}
    </nav>
  );
}
