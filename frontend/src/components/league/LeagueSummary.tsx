import { useTranslation } from '../../i18n';
import type { League } from '../../types/league';

export function LeagueSummary({ league }: { league: League }) {
  const { t } = useTranslation();
  const knownStatuses = ['draft', 'setup', 'active', 'completed', 'archived'];
  const statusLabel = knownStatuses.includes(league.status?.key)
    ? t(`leagueDetail.status.${league.status.key}`)
    : league.status?.label;
  return (
    <header className="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
      <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
        {t('leagueDetail.eyebrow')}
      </p>
      <h1 className="mt-2 text-4xl font-bold text-white">{league.name}</h1>
      <p className="mt-3 max-w-2xl text-slate-300">
        {league.description || t('leagues.noDescription')}
      </p>
      <dl className="mt-6 grid gap-3 text-sm text-slate-300 md:grid-cols-5">
        <div>
          <dt className="text-slate-500">{t('leagues.fields.competition')}</dt>
          <dd>{league.season.competition.name}</dd>
        </div>
        <div>
          <dt className="text-slate-500">{t('leagues.fields.season')}</dt>
          <dd>{league.season.name}</dd>
        </div>
        <div>
          <dt className="text-slate-500">{t('leagues.fields.type')}</dt>
          <dd>{league.type?.label ?? t('leagueDetail.notAvailable')}</dd>
        </div>
        <div>
          <dt className="text-slate-500">{t('leagues.fields.status')}</dt>
          <dd>{statusLabel ?? t('leagueDetail.notAvailable')}</dd>
        </div>
        <div>
          <dt className="text-slate-500">{t('leagueDetail.fields.maxParticipants')}</dt>
          <dd>{league.max_participants ?? t('leagueDetail.unlimited')}</dd>
        </div>
      </dl>
    </header>
  );
}