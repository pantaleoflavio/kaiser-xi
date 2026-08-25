import { useTranslation } from '../../i18n';
import { DocumentationScreenshot } from '../../components/documentation/DocumentationScreenshot';

export function SquadSetupSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.squadSetup.details');

  return (
    <article
      id="initial-squad-setup"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 7 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">{td('chapters.squadSetup.title')}</h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.squadSetup.overview')}</p>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-8 pl-6 leading-7 text-theme-muted">
        <li className="pl-1">
          <p>{details[0]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/06-championship-configuration/championship-config4.png"
              alt={td('championship-config')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[1]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/07-squad-setup/squad-setup.png"
              alt={td('squad-setup')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[2]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/07-squad-setup/squad-setup1.png"
              alt={td('squad-setup1')}
            />
          </div>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/07-squad-setup/squad-setup2.png"
              alt={td('squad-setup2')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[3]}</p>
        </li>

        <li className="pl-1">
          <p>{details[4]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/07-squad-setup/squad-setup4.png"
              alt={td('squad-setup3')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[5]}</p>

          <div className="mt-6">
            <DocumentationScreenshot
              src="/game-instructions/07-squad-setup/squad-setup3.png"
              alt={td('squad-setup4')}
            />
          </div>
        </li>
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.squadSetup.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">
          {td('chapters.squadSetup.callout')}
        </p>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#championship-configuration">
          ← {t('common.previous')}: {td('chapters.championship.title')}
        </a>

        <a className="text-right text-theme-accent hover:underline" href="#market">
          {t('common.next')}: {td('chapters.market.title')} →
        </a>
      </nav>
    </article>
  );
}
