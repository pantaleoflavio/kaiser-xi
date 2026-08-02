import { Link, useParams } from 'react-router-dom';
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
        className="text-sm font-semibold text-emerald-300 hover:text-emerald-200"
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
          <FantasyTeamBudgetSummary team={team} rosterMeta={roster.meta} />
          <FantasyRosterSection
            leagueId={leagueId}
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