import { NavLink } from 'react-router-dom';
import { useTranslation } from '../../i18n';

const linkClass = ({ isActive }: { isActive: boolean }) =>
  `rounded-lg px-3 py-2 text-sm font-semibold transition ${
    isActive
      ? 'bg-emerald-500 text-slate-950'
      : 'text-slate-300 hover:bg-slate-800 hover:text-white'
  }`;

export function LeagueNavigation({
  leagueId,
  myTeamId,
  showSchedule = false,
}: {
  leagueId: string;
  myTeamId?: number;
  showSchedule?: boolean;
}) {
  const { t } = useTranslation();
  const base = `/leagues/${leagueId}`;
  return (
    <nav
      aria-label={t('leagueNavigation.label')}
      className="flex flex-wrap gap-2 rounded-xl border border-slate-800 bg-slate-900/70 p-2"
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
      <NavLink className={linkClass} to={`${base}/rules`}>
        {t('leagueNavigation.rules')}
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
