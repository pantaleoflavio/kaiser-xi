import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from '../layouts/AppLayout';
import { DashboardPage } from '../pages/DashboardPage';
import { LoginPage } from '../pages/LoginPage';
import { RegisterPage } from '../pages/RegisterPage';
import { GuestRoute } from './GuestRoute';
import { ProtectedRoute } from './ProtectedRoute';
import { HomePage } from '../pages/HomePage';
import { LeagueDetailPage } from '../pages/LeagueDetailPage';
import { LeaguesPage } from '../pages/LeaguesPage';
import { RulesPage } from '../pages/RulesPage';
import { FantasyTeamDetailPage } from '../pages/FantasyTeamDetailPage';
import { CreateLeaguePage } from '../pages/CreateLeaguePage';
import { InvitationsPage } from '../pages/InvitationsPage';
import { AccountPage } from '../pages/AccountPage';
import { LeagueRulesPage } from '../pages/LeagueRulesPage';
import { LeagueFantasyTeamsPage } from '../pages/LeagueFantasyTeamsPage';
import { MatchdayListPage } from '../pages/MatchdayListPage';
import { MatchdayDetailPage } from '../pages/MatchdayDetailPage';
import { FormationPage } from '../pages/FormationPage';
import { HeadToHeadSchedulePage } from '../pages/HeadToHeadSchedulePage';
import { LeagueStandingsPage } from '../pages/LeagueStandingsPage';

export function AppRoutes() {
  return (
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="rules" element={<RulesPage />} />
        <Route element={<ProtectedRoute />}>
          <Route path="dashboard" element={<DashboardPage />} />
          <Route path="account" element={<AccountPage />} />
          <Route path="leagues" element={<LeaguesPage />} />
          <Route path="invitations" element={<InvitationsPage />} />
          <Route path="leagues/create" element={<CreateLeaguePage />} />
          <Route path="leagues/:leagueId" element={<LeagueDetailPage />} />
          <Route path="leagues/:leagueId/rules" element={<LeagueRulesPage />} />
          <Route path="leagues/:leagueId/fantasy-teams" element={<LeagueFantasyTeamsPage />} />
          <Route path="leagues/:leagueId/matchdays" element={<MatchdayListPage />} />
          <Route path="leagues/:leagueId/standings" element={<LeagueStandingsPage />} />
          <Route
            path="leagues/:leagueId/head-to-head-schedule"
            element={<HeadToHeadSchedulePage />}
          />
          <Route path="leagues/:leagueId/matchdays/:matchdayId" element={<MatchdayDetailPage />} />
          <Route
            path="leagues/:leagueId/matchdays/:matchdayId/fantasy-teams/:fantasyTeamId/formation"
            element={<FormationPage />}
          />
          <Route
            path="leagues/:leagueId/fantasy-teams/:fantasyTeamId"
            element={<FantasyTeamDetailPage />}
          />
        </Route>
        <Route element={<GuestRoute />}>
          <Route path="login" element={<LoginPage />} />
          <Route path="register" element={<RegisterPage />} />
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}
