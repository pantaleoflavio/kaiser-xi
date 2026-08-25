import { DocumentationScreenshot } from '../../components/documentation/DocumentationScreenshot';
import { useTranslation } from '../../i18n';

export function ScoringSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.scoring.details');

  return (
    <article
      id="scoring"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 12 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">{td('chapters.scoring.title')}</h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.scoring.overview')}</p>

      <div className="mt-4">
        <DocumentationScreenshot src="/game-instructions/12-scoring/score.png" alt={td('score')} />
      </div>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-8 pl-6 leading-7 text-theme-muted">
        <li className="pl-1">
          <p>{details[0]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/12-scoring/score1.png"
              alt={td('score 1')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[1]}</p>
        </li>

        <li className="pl-1">
          <p>{details[2]}</p>
        </li>

        <li className="pl-1">
          <p>{details[3]}</p>
        </li>

        <li className="pl-1">
          <p>{details[4]}</p>
        </li>

        <li className="pl-1">
          <p>{details[5]}</p>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/12-scoring/score2.png"
              alt={td('score 2')}
            />
          </div>

          <div className="mt-4">
            <DocumentationScreenshot
              src="/game-instructions/12-scoring/score3.png"
              alt={td('score 3')}
            />
          </div>
        </li>

        <li className="pl-1">
          <p>{details[6]}</p>
        </li>

        <li className="pl-1">
          <p>{details[7]}</p>

          {/* Screenshot: configurazione modificatore difesa */}
        </li>

        <li className="pl-1">
          <p>{details[8]}</p>

          {/* Screenshot opzionale: esempio modificatore difesa */}
        </li>

        <li className="pl-1">
          <p>{details[9]}</p>

          {/* Screenshot: totale finale della Fantasquadra */}
        </li>

        <li className="pl-1">
          <p>{details[10]}</p>

          {/* Screenshot opzionale: riepilogo Regole di punteggio */}
        </li>
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.scoring.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">{td('chapters.scoring.callout')}</p>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#matchdays">
          ← {t('common.previous')}: {td('chapters.matchdays.title')}
        </a>

        <a
          className="text-right text-theme-accent hover:underline"
          href="#results-standings-calendar"
        >
          {t('common.next')}: {td('chapters.results.title')} →
        </a>
      </nav>
    </article>
  );
}
