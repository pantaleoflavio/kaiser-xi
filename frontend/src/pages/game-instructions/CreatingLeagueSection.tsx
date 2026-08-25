import { DocumentationScreenshot } from '../../components/documentation/DocumentationScreenshot';
import { useTranslation } from '../../i18n';

export function CreatingLeagueSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.creatingLeague.details');

  return (
    <article
      id="creating-a-league"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 2 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">
        {td('chapters.creatingLeague.title')}
      </h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.creatingLeague.overview')}</p>

      <div className="mt-6">
        <DocumentationScreenshot
          src="/game-instructions/02-creating-league/create-league-1.png"
          alt={td('chapters.creatingLeague.images.createLeague1.alt')}
        />
      </div>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-6 pl-6 leading-7 text-theme-muted">
        {details.map((detail, index) => (
          <li className="pl-1" key={index}>
            <p>{detail}</p>

            {index === 3 && (
              <div className="mt-4">
                <DocumentationScreenshot
                  src="/game-instructions/02-creating-league/create-league-2.png"
                  alt={td('chapters.creatingLeague.images.createLeague2.alt')}
                />
              </div>
            )}

            {index === 4 && (
              <div className="mt-4">
                <DocumentationScreenshot
                  src="/game-instructions/02-creating-league/create-league-3.png"
                  alt={td('chapters.creatingLeague.images.createLeague3.alt')}
                />
              </div>
            )}
          </li>
        ))}
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.creatingLeague.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">
          {td('chapters.creatingLeague.callout')}
        </p>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#getting-started">
          ← {t('common.previous')}: {td('chapters.gettingStarted.title')}
        </a>

        <a className="text-right text-theme-accent hover:underline" href="#building-initial-squads">
          {t('common.next')}: {td('chapters.buildingSquads.title')} →
        </a>
      </nav>
    </article>
  );
}
