import { useTranslation } from '../../i18n';

export function FaqSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.faq.details');

  return (
    <article
      id="faq-troubleshooting"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 17 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">{td('chapters.faq.title')}</h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.faq.overview')}</p>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-8 pl-6 leading-7 text-theme-muted">
        <li className="pl-1">
          <p>{details[0]}</p>
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
        </li>

        <li className="pl-1">
          <p>{details[6]}</p>
        </li>

        <li className="pl-1">
          <p>{details[7]}</p>
        </li>

        <li className="pl-1">
          <p>{details[8]}</p>
        </li>

        <li className="pl-1">
          <p>{details[9]}</p>
        </li>

        <li className="pl-1">
          <p>{details[10]}</p>
        </li>
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.faq.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">{td('chapters.faq.callout')}</p>

        <a
          className="mt-3 inline-block font-semibold text-theme-accent hover:underline"
          href="https://kaiserxi.forumfree.it/"
          target="_blank"
          rel="noreferrer"
        >
          {td('chapters.faq.forumLink')}
        </a>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#profile-personalization">
          ← {t('common.previous')}: {td('chapters.profile.title')}
        </a>

        <a className="text-theme-accent hover:underline" href="#getting-started">
          ↑ {td('backToTop')}
        </a>
      </nav>
    </article>
  );
}
