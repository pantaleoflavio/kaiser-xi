import { useTranslation } from '../../i18n';

export function BuildingSquadsSection() {
  const { t, tGameInstructions: td, tGameInstructionsList: tdList } = useTranslation();

  const details = tdList('chapters.buildingSquads.details');

  return (
    <article
      id="building-initial-squads"
      className="scroll-mt-5 rounded-2xl border border-theme-border bg-theme-surface p-5 sm:p-8"
    >
      <p className="text-sm font-semibold text-theme-accent">{td('chapter', { number: 3 })}</p>

      <h2 className="mt-1 text-2xl font-bold text-theme-text">
        {td('chapters.buildingSquads.title')}
      </h2>

      <p className="mt-4 leading-7 text-theme-muted">{td('chapters.buildingSquads.overview')}</p>

      <h3 className="mt-6 font-semibold text-theme-text">{td('details')}</h3>

      <ol className="mt-3 list-decimal space-y-4 pl-6 leading-7 text-theme-muted">
        {details.map((detail, index) => (
          <li className="pl-1" key={index}>
            {detail}
          </li>
        ))}
      </ol>

      <aside className="mt-6 rounded-xl border border-theme-accent/50 bg-theme-muted-surface p-4">
        <strong className="text-theme-text">{td('chapters.buildingSquads.calloutLabel')}</strong>

        <p className="mt-1 text-sm leading-6 text-theme-muted">
          {td('chapters.buildingSquads.callout')}
        </p>
      </aside>

      <nav
        aria-label={td('chapterNavigation')}
        className="mt-8 flex justify-between gap-4 border-t border-theme-border pt-5 text-sm font-semibold"
      >
        <a className="text-theme-accent hover:underline" href="#creating-a-league">
          ← {t('common.previous')}: {td('chapters.creatingLeague.title')}
        </a>

        <a
          className="text-right text-theme-accent hover:underline"
          href="#understanding-your-league"
        >
          {t('common.next')}: {td('chapters.leagueOverview.title')} →
        </a>
      </nav>
    </article>
  );
}
