import { DocumentationScreenshot } from '../../components/documentation/DocumentationScreenshot';
import { useTranslation } from '../../i18n';

export function FantasyTeamSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.fantasyTeam.details');

  return (
    <article
      id="your-fantasy-team"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 9 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">
        {td('chapters.fantasyTeam.title')}
      </h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.fantasyTeam.overview')}</p>

      <div className="mt-4">
        <DocumentationScreenshot
          src="/game-instructions/09-fantasy-team/team-section.png"
          alt={td('team section')}
        />
      </div>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-8 pl-6 leading-7 text-theme-muted">
        <li className="pl-1">
          <p>{details[0]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/09-fantasy-team/team-section1.png"
              alt={td('team section 1')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[1]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/09-fantasy-team/team-section2.png"
              alt={td('team section 2')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[2]}</p>
        </li>

        <li className="pl-1">
          <p>{details[3]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/09-fantasy-team/team-section3.png"
              alt={td('team section 3')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[4]}</p>
        </li>
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.fantasyTeam.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">
          {td('chapters.fantasyTeam.callout')}
        </p>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#market">
          ← {t('common.previous')}: {td('chapters.market.title')}
        </a>

        <a className="text-right text-theme-accent hover:underline" href="#formation">
          {t('common.next')}: {td('chapters.formation.title')} →
        </a>
      </nav>
    </article>
  );
}
