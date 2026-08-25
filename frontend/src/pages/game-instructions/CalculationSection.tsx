import { useTranslation } from '../../i18n';
import { DocumentationScreenshot } from '../../components/documentation/DocumentationScreenshot';

export function CalculationSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.calculation.details');

  return (
    <article
      id="calculate-recalculate"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 14 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">
        {td('chapters.calculation.title')}
      </h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.calculation.overview')}</p>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-8 pl-6 leading-7 text-theme-muted">
        <li className="pl-1">
          <p>{details[0]}</p>
        </li>

        <li className="pl-1">
          <p>{details[1]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/14-calculation/calculate.png"
              alt={td('calculation')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[2]}</p>
        </li>

        <li className="pl-1">
          <p>{details[3]}</p>
        </li>

        <li className="pl-1">
          <p>{details[4]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/14-calculation/calculate1.png"
              alt={td('calculation 1')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[5]}</p>
        </li>

        <li className="pl-1">
          <p>{details[6]}</p>
        </li>
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.calculation.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">
          {td('chapters.calculation.callout')}
        </p>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#results-standings-calendar">
          ← {t('common.previous')}: {td('chapters.results.title')}
        </a>

        <a className="text-right text-theme-accent hover:underline" href="#transfers-trades">
          {t('common.next')}: {td('chapters.transfers.title')} →
        </a>
      </nav>
    </article>
  );
}
