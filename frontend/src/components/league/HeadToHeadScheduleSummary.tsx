import type { HeadToHeadSchedule } from '../../types/api';
import { useTranslation } from '../../i18n';
import { formatDate } from '../../utils/formatters';

export function HeadToHeadScheduleSummary({ schedule }: { schedule: HeadToHeadSchedule }) {
  const { language, t } = useTranslation();
  const label =
    schedule.start_matchday?.name ||
    (schedule.start_matchday
      ? t('formation.matchdayNumber', { number: schedule.start_matchday.number })
      : t('leagueDetail.notAvailable'));
  return (
    <div className="space-y-6">
      <section className="rounded-2xl border border-theme-primary/30 bg-emerald-950/20 p-6">
        <h1 className="text-3xl font-bold text-theme-text">{t('h2h.title')}</h1>
        <p className="mt-2 font-semibold text-theme-accent" role="status">
          {t('h2h.generated')}
        </p>
        <dl className="mt-5 grid gap-4 sm:grid-cols-3">
          <div>
            <dt className="text-sm text-theme-muted">{t('h2h.generatedAt')}</dt>
            <dd className="mt-1 text-theme-text">
              {formatDate(schedule.generated_at, t('leagueDetail.notAvailable'), language)}
            </dd>
          </div>
          <div>
            <dt className="text-sm text-theme-muted">{t('h2h.startingMatchday')}</dt>
            <dd className="mt-1 text-theme-text">{label}</dd>
          </div>
          <div>
            <dt className="text-sm text-theme-muted">{t('h2h.currentParticipants')}</dt>
            <dd className="mt-1 text-theme-text">{schedule.participant_count}</dd>
          </div>
        </dl>
      </section>
      <section aria-labelledby="fixtures-heading">
        <h2 className="text-2xl font-semibold text-theme-text" id="fixtures-heading">
          {t('h2h.schedule')}
        </h2>
        <div className="mt-4 space-y-4">
          {schedule.matchdays.map((group) => (
            <article
              className="rounded-2xl border border-theme-border bg-theme-surface/70 p-5"
              key={group.matchday.id}
            >
              <h3 className="text-lg font-semibold text-theme-text">
                {group.matchday.name ||
                  t('formation.matchdayNumber', { number: group.matchday.number })}
              </h3>
              <p className="mt-1 text-sm text-theme-muted">
                {formatDate(group.matchday.starts_at, t('leagueDetail.notAvailable'), language)}
              </p>
              <ul className="mt-4 space-y-2">
                {group.fixtures.map((fixture) => (
                  <li
                    className="grid grid-cols-[1fr_auto_1fr] items-center gap-3 rounded-lg bg-theme-background/60 px-3 py-3 text-sm text-theme-text"
                    key={fixture.id}
                  >
                    <span className="min-w-0 break-words text-right">
                      {fixture.home_fantasy_team.name}
                    </span>
                    <span className="text-theme-muted">{t('h2h.versus')}</span>
                    <span className="min-w-0 break-words">{fixture.away_fantasy_team.name}</span>
                  </li>
                ))}
              </ul>
            </article>
          ))}
        </div>
      </section>
    </div>
  );
}
