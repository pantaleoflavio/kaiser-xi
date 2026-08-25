import { useTranslation } from '../../i18n';
import { DocumentationScreenshot } from '../../components/documentation/DocumentationScreenshot';

export function ChampionshipSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.championship.details');

  return (
    <article
      id="championship-configuration"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 6 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">
        {td('chapters.championship.title')}
      </h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.championship.overview')}</p>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-8 pl-6 leading-7 text-theme-muted">
        <li className="pl-1">
          <p>{details[0]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/06-championship-configuration/championship-config.png"
              alt={td('championship-config')}
            />
          </div>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/06-championship-configuration/championship-config1.png"
              alt={td('championship-config 1')}
            />
          </div>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/06-championship-configuration/championship-config4.png"
              alt={td('championship-config 2')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[1]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/06-championship-configuration/championship-config2.png"
              alt={td('championship-config 3')}
            />
          </div>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/06-championship-configuration/championship-config3.png"
              alt={td('championship-config 4')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[2]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/06-championship-configuration/championship-config7.png"
              alt={td('championship-config 5')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[3]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/06-championship-configuration/championship-config6.png"
              alt={td('championship-config 6')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[4]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/06-championship-configuration/championship-config5.png"
              alt={td('championship-config 7')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[5]}</p>

          {/* Screenshot: inizializzazione */}
        </li>
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.championship.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">
          {td('chapters.championship.callout')}
        </p>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#league-rules">
          ← {t('common.previous')}: {td('chapters.leagueRules.title')}
        </a>

        <a className="text-right text-theme-accent hover:underline" href="#initial-squad-setup">
          {t('common.next')}: {td('chapters.squadSetup.title')} →
        </a>
      </nav>
    </article>
  );
}
