import { lazy } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from '../layouts/AppLayout';
import { GuestRoute } from './GuestRoute';
import { ProtectedRoute } from './ProtectedRoute';
import { HomePage } from '../pages/HomePage';

const AccountPage = lazy(() =>
  import('../pages/AccountPage').then((module) => ({ default: module.AccountPage })),
);
const ClassicChampionshipPage = lazy(() =>
  import('../pages/ClassicChampionshipPage').then((module) => ({
    default: module.ClassicChampionshipPage,
  })),
);
const CreateLeaguePage = lazy(() =>
  import('../pages/CreateLeaguePage').then((module) => ({ default: module.CreateLeaguePage })),
);
const DashboardPage = lazy(() =>
  import('../pages/DashboardPage').then((module) => ({ default: module.DashboardPage })),
);
const EmailVerifiedPage = lazy(() =>
  import('../pages/EmailVerifiedPage').then((module) => ({ default: module.EmailVerifiedPage })),
);
const FantasyTeamDetailPage = lazy(() =>
  import('../pages/FantasyTeamDetailPage').then((module) => ({
    default: module.FantasyTeamDetailPage,
  })),
);
const ForgotPasswordPage = lazy(() =>
  import('../pages/ForgotPasswordPage').then((module) => ({ default: module.ForgotPasswordPage })),
);
const FormationPage = lazy(() =>
  import('../pages/FormationPage').then((module) => ({ default: module.FormationPage })),
);
const GameInstructionsPage = lazy(() =>
  import('../pages/game-instructions/GameInstructionsPage').then((module) => ({
    default: module.GameInstructionsPage,
  })),
);
const HeadToHeadSchedulePage = lazy(() =>
  import('../pages/HeadToHeadSchedulePage').then((module) => ({
    default: module.HeadToHeadSchedulePage,
  })),
);
const InvitationsPage = lazy(() =>
  import('../pages/InvitationsPage').then((module) => ({ default: module.InvitationsPage })),
);
const LeagueDetailPage = lazy(() =>
  import('../pages/LeagueDetailPage').then((module) => ({ default: module.LeagueDetailPage })),
);
const LeagueFantasyTeamsPage = lazy(() =>
  import('../pages/LeagueFantasyTeamsPage').then((module) => ({
    default: module.LeagueFantasyTeamsPage,
  })),
);
const LeagueMarketPage = lazy(() =>
  import('../pages/LeagueMarketPage').then((module) => ({ default: module.LeagueMarketPage })),
);
const LeagueRulesPage = lazy(() =>
  import('../pages/LeagueRulesPage').then((module) => ({ default: module.LeagueRulesPage })),
);
const LeagueStandingsPage = lazy(() =>
  import('../pages/LeagueStandingsPage').then((module) => ({
    default: module.LeagueStandingsPage,
  })),
);
const LeaguesPage = lazy(() =>
  import('../pages/LeaguesPage').then((module) => ({ default: module.LeaguesPage })),
);
const LoginPage = lazy(() =>
  import('../pages/LoginPage').then((module) => ({ default: module.LoginPage })),
);
const MatchdayDetailPage = lazy(() =>
  import('../pages/MatchdayDetailPage').then((module) => ({ default: module.MatchdayDetailPage })),
);
const MatchdayListPage = lazy(() =>
  import('../pages/MatchdayListPage').then((module) => ({ default: module.MatchdayListPage })),
);
const PlayerProfilePage = lazy(() =>
  import('../pages/PlayerProfilePage').then((module) => ({ default: module.PlayerProfilePage })),
);
const RegisterPage = lazy(() =>
  import('../pages/RegisterPage').then((module) => ({ default: module.RegisterPage })),
);
const ResetPasswordPage = lazy(() =>
  import('../pages/ResetPasswordPage').then((module) => ({ default: module.ResetPasswordPage })),
);
const VerifyEmailPage = lazy(() =>
  import('../pages/VerifyEmailPage').then((module) => ({ default: module.VerifyEmailPage })),
);
const PrivacyPage = lazy(() =>
  import('../pages/PrivacyPage').then((module) => ({ default: module.PrivacyPage })),
);
const ImprintPage = lazy(() =>
  import('../pages/ImprintPage').then((module) => ({ default: module.ImprintPage })),
);
const PrivacyAcknowledgementPage = lazy(() =>
  import('../pages/PrivacyAcknowledgementPage').then((module) => ({
    default: module.PrivacyAcknowledgementPage,
  })),
);

export function AppRoutes() {
  return (
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<HomePage />} />
        <Route path="game-instructions" element={<GameInstructionsPage />} />
        <Route path="rules" element={<Navigate to="/game-instructions" replace />} />
        <Route path="verify-email" element={<VerifyEmailPage />} />
        <Route path="email-verified" element={<EmailVerifiedPage />} />
        <Route path="privacy" element={<PrivacyPage />} />
        <Route path="impressum" element={<ImprintPage />} />
        <Route path="privacy-acknowledgement" element={<PrivacyAcknowledgementPage />} />
        <Route element={<ProtectedRoute />}>
          <Route path="dashboard" element={<DashboardPage />} />
          <Route path="account" element={<AccountPage />} />
          <Route path="leagues" element={<LeaguesPage />} />
          <Route path="invitations" element={<InvitationsPage />} />
          <Route path="players/:playerId" element={<PlayerProfilePage />} />
          <Route path="leagues/create" element={<CreateLeaguePage />} />
          <Route path="leagues/:leagueId" element={<LeagueDetailPage />} />
          <Route path="leagues/:leagueId/rules" element={<LeagueRulesPage />} />
          <Route path="leagues/:leagueId/market" element={<LeagueMarketPage />} />
          <Route path="leagues/:leagueId/fantasy-teams" element={<LeagueFantasyTeamsPage />} />
          <Route path="leagues/:leagueId/matchdays" element={<MatchdayListPage />} />
          <Route path="leagues/:leagueId/standings" element={<LeagueStandingsPage />} />
          <Route
            path="leagues/:leagueId/classic-championship"
            element={<ClassicChampionshipPage />}
          />
          <Route
            path="leagues/:leagueId/formula-one-championship"
            element={<ClassicChampionshipPage />}
          />
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
          <Route path="forgot-password" element={<ForgotPasswordPage />} />
          <Route path="reset-password" element={<ResetPasswordPage />} />
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}
