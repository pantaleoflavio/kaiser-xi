import { DocumentationScreenshot } from '../../components/documentation/DocumentationScreenshot';
import { useTranslation } from '../../i18n';

export function LeagueRulesSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.leagueRules.details');

  return (
    <article
      id="league-rules"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 5 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">
        {td('chapters.leagueRules.title')}
      </h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.leagueRules.overview')}</p>

      <div className="mt-6">
        <DocumentationScreenshot
          src="/game-instructions/05-league-rules/league-rules.png"
          alt="League Rules overview"
        />
      </div>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-8 pl-6 leading-7 text-theme-muted">
        <li className="pl-1">
          <p>{details[0]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/05-league-rules/league-rules1.png"
              alt="League Rules 1"
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[1]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/05-league-rules/league-rules2.png"
              alt="League Rules 2"
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[2]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/05-league-rules/league-rules3.png"
              alt="League Rules 3"
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[3]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/05-league-rules/league-rules4.png"
              alt="League Rules 4"
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[4]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/05-league-rules/league-rules5.png"
              alt="League Rules 5"
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[5]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/05-league-rules/league-rules6.png"
              alt="League Rules 6"
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[6]}</p>
        </li>

        <li className="pl-1">
          <p>{details[7]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/05-league-rules/league-rules7.png"
              alt="League Rules 7"
            />
          </div>
        </li>
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.leagueRules.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">
          {td('chapters.leagueRules.callout')}
        </p>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#understanding-your-league">
          ← {t('common.previous')}: {td('chapters.leagueOverview.title')}
        </a>

        <a
          className="text-right text-theme-accent hover:underline"
          href="#championship-configuration"
        >
          {t('common.next')}: {td('chapters.championship.title')} →
        </a>
      </nav>
    </article>
  );
}
