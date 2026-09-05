import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { formationsApi } from '../api/formations';
import { formationKeys } from '../api/queryKeys';
import { LoadingState } from '../components/LoadingState';
import { ErrorPanel } from '../components/feedback/ErrorPanel';
import { FantasyTeamBudgetSummary } from '../components/fantasy-team/FantasyTeamBudgetSummary';
import { FantasyTeamNameForm } from '../components/fantasy-team/FantasyTeamNameForm';
import { FantasyRosterSection } from '../components/fantasy-team/FantasyRosterSection';
import { FantasyTeamSummary } from '../components/fantasy-team/FantasyTeamSummary';
import { useFantasyTeamDetail } from '../hooks/useFantasyTeamDetail';
import { useFantasyTeamName } from '../hooks/useFantasyTeamName';
import { useRosterManagement } from '../hooks/useRosterManagement';
import { useTranslation } from '../i18n';
import { errorMessage, validationDetails } from '../utils/apiErrors';

export function FantasyTeamDetailPage() {
  const { fantasyTeamId = '', leagueId = '' } = useParams();
  const { t } = useTranslation();
  const detail = useFantasyTeamDetail(leagueId, fantasyTeamId);
  const league = detail.league.data?.data;
  const settings = detail.settings.data?.data;
  const team = detail.team.data?.data;
  const roster = detail.roster.data;
  const canManageRoster =
    league?.my_role === 'commissioner' || league?.my_role === 'co_commissioner';
  const nameForm = useFantasyTeamName(leagueId, fantasyTeamId, team);
  const rosterManagement = useRosterManagement(leagueId, fantasyTeamId, canManageRoster, settings);
  const matchdays = useQuery({
    queryKey: formationKeys.matchdays(leagueId),
    queryFn: () => formationsApi.matchdays(leagueId),
    enabled: Boolean(team?.is_owned_by_current_user),
  });
  const currentMatchday = matchdays.data?.data.find(
    (item) => new Date(item.deadline).getTime() > Date.now(),
  );
  const queryError = [
    detail.league.error,
    detail.settings.error,
    detail.team.error,
    detail.roster.error,
  ].find(Boolean);

  if (detail.isLoading) return <LoadingState message={t('fantasyTeams.detail.loading')} />;

  return (
    <section className="space-y-6">
      <Link
        className="text-sm font-semibold text-theme-accent hover:text-theme-accent"
        to={`/leagues/${leagueId}`}
      >
        {t('fantasyTeams.detail.backToLeague')}
      </Link>

      {queryError ? (
        <ErrorPanel
          error={{
            details: validationDetails(queryError),
            message: errorMessage(queryError, t('fantasyTeams.errors.detail'), t),
          }}
          title={t('fantasyTeams.errors.detailTitle')}
        />
      ) : null}

      {team && roster ? (
        <div className="space-y-6">
          <FantasyTeamSummary team={team} />
          <FantasyTeamBudgetSummary team={team} />
          <FantasyRosterSection
            leagueId={leagueId}
            seasonId={league!.season.id}
            players={roster.data}
            canManage={canManageRoster}
            success={rosterManagement.success}
            error={rosterManagement.error}
            assignError={rosterManagement.assignError}
            fieldErrors={rosterManagement.fieldErrors}
            setFieldErrors={rosterManagement.setFieldErrors}
            isAssigning={rosterManagement.isAssigning}
            releasingPlayerId={rosterManagement.releasingPlayerId}
            onAssign={rosterManagement.assign}
            onRelease={rosterManagement.release}
          />

          {team.is_owned_by_current_user && currentMatchday ? (
            <section className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
              <h2 className="text-2xl font-semibold text-theme-text">{t('formation.title')}</h2>
              <p className="mt-1 text-sm text-theme-muted">
                {t('fantasyTeams.detail.formationDescription')}
              </p>
              <Link
                className="mt-4 inline-block rounded-lg bg-theme-primary px-4 py-2 font-semibold text-theme-primary-foreground"
                to={`/leagues/${leagueId}/matchdays/${currentMatchday.id}/fantasy-teams/${fantasyTeamId}/formation`}
              >
                {t('matchdays.openFormation')}
              </Link>
            </section>
          ) : null}

          {team.is_owned_by_current_user ? (
            <FantasyTeamNameForm
              error={
                nameForm.error ? (
                  <div className="mt-4">
                    <ErrorPanel
                      error={nameForm.error}
                      title={t('fantasyTeams.errors.updateTitle')}
                    />
                  </div>
                ) : null
              }
              isUpdating={nameForm.isUpdating}
              isEditing={nameForm.isEditing}
              fieldError={nameForm.fieldError}
              name={nameForm.name}
              onCancel={nameForm.cancel}
              onEdit={nameForm.edit}
              onNameChange={nameForm.setName}
              onSubmit={(event) => {
                event.preventDefault();
                nameForm.update();
              }}
              success={nameForm.success}
            />
          ) : null}
        </div>
      ) : null}
    </section>
  );
}
