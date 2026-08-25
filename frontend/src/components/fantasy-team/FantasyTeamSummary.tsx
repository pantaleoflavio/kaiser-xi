import { useTranslation } from '../../i18n';
import type { FantasyTeam } from '../../types/league';
function formatDate(value: string | null, fallback: string, locale: string) {
  if (!value) return fallback;
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(value),
  );
}
export function FantasyTeamSummary({ team }: { team: FantasyTeam }) {
  const { language, t } = useTranslation();
  return (
    <header className="rounded-2xl border border-theme-border bg-theme-surface/70 p-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p className="text-sm font-semibold uppercase tracking-wide text-theme-accent">
            {t('fantasyTeams.detail.eyebrow')}
          </p>
          <h1 className="mt-2 text-4xl font-bold text-theme-text">{team.name}</h1>
          <p className="mt-3 text-theme-muted">
            {t('fantasyTeams.detail.owner', { name: team.owner.name })}
          </p>
        </div>
        {team.is_owned_by_current_user ? (
          <span className="rounded-full border border-theme-primary/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-theme-accent">
            {t('fantasyTeams.detail.ownedByYou')}
          </span>
        ) : null}
      </div>
      <dl className="mt-6 grid gap-3 text-sm text-theme-muted md:grid-cols-4">
        <div>
          <dt className="text-theme-muted">{t('fantasyTeams.detail.fields.slug')}</dt>
          <dd>{team.slug}</dd>
        </div>
        <div>
          <dt className="text-theme-muted">{t('fantasyTeams.detail.fields.leagueId')}</dt>
          <dd>{team.league_id}</dd>
        </div>
        <div>
          <dt className="text-theme-muted">{t('fantasyTeams.detail.fields.createdAt')}</dt>
          <dd>{formatDate(team.created_at, t('leagueDetail.notAvailable'), language)}</dd>
        </div>
        <div>
          <dt className="text-theme-muted">{t('fantasyTeams.detail.fields.updatedAt')}</dt>
          <dd>{formatDate(team.updated_at, t('leagueDetail.notAvailable'), language)}</dd>
        </div>
      </dl>
    </header>
  );
}
