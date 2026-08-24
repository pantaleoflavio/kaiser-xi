import { Link } from 'react-router-dom';
import { useTranslation } from '../i18n';

const chapters = [
  ['gettingStarted', 'getting-started'],
  ['creatingLeague', 'creating-a-league'],
  ['buildingSquads', 'building-initial-squads'],
  ['leagueOverview', 'understanding-your-league'],
  ['leagueRules', 'league-rules'],
  ['championship', 'championship-configuration'],
  ['squadSetup', 'initial-squad-setup'],
  ['market', 'market'],
  ['fantasyTeam', 'your-fantasy-team'],
  ['formation', 'formation'],
  ['matchdays', 'matchdays'],
  ['scoring', 'scoring'],
  ['results', 'results-standings-calendar'],
  ['calculation', 'calculate-recalculate'],
  ['transfers', 'transfers-trades'],
  ['commissioner', 'commissioner-guide'],
  ['profile', 'profile-personalization'],
  ['faq', 'faq-troubleshooting'],
] as const;

export function GameInstructionsPage() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  return (
    <div className="space-y-8 text-left">
      <header className="rounded-3xl border border-theme-border bg-theme-surface p-6 shadow-xl sm:p-10">
        <p className="text-sm font-semibold uppercase tracking-[0.25em] text-theme-accent">
          {td('eyebrow')}
        </p>
        <h1 className="mt-3 text-4xl font-bold tracking-tight text-theme-text">{td('title')}</h1>
        <p className="mt-4 max-w-3xl leading-7 text-theme-muted">{td('introduction')}</p>
      </header>

      <div className="grid items-start gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside className="lg:sticky lg:top-4">
          <details className="rounded-2xl border border-theme-border bg-theme-surface p-4 lg:hidden">
            <summary className="cursor-pointer font-semibold text-theme-text">
              {td('contents')}
            </summary>
            <ChapterLinks />
          </details>
          <nav
            aria-label={td('contents')}
            className="hidden max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border border-theme-border bg-theme-surface p-4 lg:block"
          >
            <p className="mb-3 font-semibold text-theme-text">{td('contents')}</p>
            <ChapterLinks />
          </nav>
        </aside>

        <main className="min-w-0 space-y-10">
          {chapters.map(([key, anchor], index) => (
            <article
              className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
              id={anchor}
              key={key}
            >
              <p className="text-sm font-semibold text-theme-accent">
                {td('chapter', { number: index + 1 })}
              </p>
              <h2 className="mt-1 text-2xl font-bold text-theme-text">
                {td(`chapters.${key}.title`)}
              </h2>
              <p className="mt-4 leading-7 text-theme-muted">{td(`chapters.${key}.overview`)}</p>
              <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>
              <ol className="mt-3 list-decimal space-y-4 pl-6 leading-7 text-theme-muted">
                {tdList(`chapters.${key}.details`).map((detail, detailIndex) => (
                  <li className="pl-1" key={detailIndex}>
                    {detail}
                  </li>
                ))}
              </ol>
              <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
                <strong className="text-theme-text">{td(`chapters.${key}.calloutLabel`)}</strong>
                <p className="mt-1 text-sm leading-6 text-theme-muted">
                  {td(`chapters.${key}.callout`)}
                </p>
              </aside>
              <nav
                aria-label={td('chapterNavigation')}
                className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
              >
                {index > 0 ? (
                  <a
                    className="text-theme-accent hover:underline"
                    href={`#${chapters[index - 1][1]}`}
                  >
                    ← {t('common.previous')}: {td(`chapters.${chapters[index - 1][0]}.title`)}
                  </a>
                ) : (
                  <span />
                )}
                {index < chapters.length - 1 ? (
                  <a
                    className="text-right text-theme-accent hover:underline"
                    href={`#${chapters[index + 1][1]}`}
                  >
                    {t('common.next')}: {td(`chapters.${chapters[index + 1][0]}.title`)} →
                  </a>
                ) : (
                  <Link className="text-theme-accent hover:underline" to="/">
                    {td('backHome')}
                  </Link>
                )}
              </nav>
            </article>
          ))}
        </main>
      </div>
    </div>
  );
}

function ChapterLinks() {
  const { tGameInstructions: td } = useTranslation();
  return (
    <ol className="mt-3 space-y-2 text-sm">
      {chapters.map(([key, anchor], index) => (
        <li key={key}>
          <a
            className="block rounded px-2 py-1 text-theme-muted transition hover:bg-theme-muted-surface hover:text-theme-accent"
            href={`#${anchor}`}
          >
            {index + 1}. {td(`chapters.${key}.title`)}
          </a>
        </li>
      ))}
    </ol>
  );
}
