import { DocumentationScreenshot } from '../../components/documentation/DocumentationScreenshot';
import { useTranslation } from '../../i18n';

export function LeagueOverviewSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.leagueOverview.details');

  return (
    <article
      id="understanding-your-league"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 4 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">
        {td('chapters.leagueOverview.title')}
      </h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.leagueOverview.overview')}</p>

      <div className="mt-6">
        <DocumentationScreenshot
          src="/game-instructions/04-league-overview/league-overview1.png"
          alt={td('league overview 1')}
        />
      </div>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-8 pl-6 leading-7 text-theme-muted">
        <li className="pl-1">
          <p>{details[0]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/04-league-overview/league-overview2.png"
              alt={td('league overview 2')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[1]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/04-league-overview/league-overview3.png"
              alt={td('league overview 3')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[2]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/04-league-overview/league-overview4.png"
              alt={td('league overview 4')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[3]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/04-league-overview/league-overview5.png"
              alt={td('league overview 5')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[4]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/04-league-overview/league-overview6.png"
              alt={td('league overview 6')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[5]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/04-league-overview/league-overview7.png"
              alt={td('league overview 1')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[6]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/04-league-overview/league-overview8.png"
              alt={td('league overview 8')}
            />
          </div>
          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/04-league-overview/league-overview9.png"
              alt={td('league overview 9')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[7]}</p>
        </li>
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.leagueOverview.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">
          {td('chapters.leagueOverview.callout')}
        </p>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#building-initial-squads">
          ← {t('common.previous')}: {td('chapters.buildingSquads.title')}
        </a>

        <a className="text-right text-theme-accent hover:underline" href="#league-rules">
          {t('common.next')}: {td('chapters.leagueRules.title')} →
        </a>
      </nav>
    </article>
  );
}
