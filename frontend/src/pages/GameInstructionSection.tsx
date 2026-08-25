import { Link } from 'react-router-dom';
import { useTranslation } from '../i18n';
import { GettingStartedSection } from './game-instructions/GettingStartedSection';

const chapters = [
  ['getting-started', 'gettingStarted'],
  ['creating-a-league', 'creatingLeague'],
  ['building-initial-squads', 'buildingSquads'],
  ['understanding-your-league', 'leagueOverview'],
  ['league-rules', 'leagueRules'],
  ['championship-configuration', 'championship'],
  ['initial-squad-setup', 'squadSetup'],
  ['market', 'market'],
  ['your-fantasy-team', 'fantasyTeam'],
  ['formation', 'formation'],
  ['matchdays', 'matchdays'],
  ['scoring', 'scoring'],
  ['results-standings-calendar', 'results'],
  ['calculate-recalculate', 'calculation'],
  ['transfers-trades', 'transfers'],
  ['commissioner-guide', 'commissioner'],
  ['profile-personalization', 'profile'],
  ['faq-troubleshooting', 'faq'],
] as const;

export function GameInstructionsPage() {
  const { t, tGameInstructions: td } = useTranslation();

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
          <GettingStartedSection />

          {/*
            Qui aggiungeremo progressivamente:

            <CreatingLeagueSection />
            <BuildingSquadsSection />
            <LeagueOverviewSection />
            <LeagueRulesSection />
            <ChampionshipSection />
            <SquadSetupSection />
            <MarketSection />
            <FantasyTeamSection />
            <FormationSection />
            <MatchdaysSection />
            <ScoringSection />
            <ResultsSection />
            <CalculationSection />
            <TransfersSection />
            <CommissionerSection />
            <ProfileSection />
            <FaqSection />
          */}

          <nav
            aria-label={td('chapterNavigation')}
            className="flex justify-end border-t border-theme-border pt-5 text-sm font-semibold"
          >
            <Link className="text-theme-accent hover:underline" to="/">
              {td('backHome')}
            </Link>
          </nav>
        </main>
      </div>
    </div>
  );
}

function ChapterLinks() {
  const { tGameInstructions: td } = useTranslation();

  return (
    <ol className="mt-3 space-y-2 text-sm">
      {chapters.map(([anchor, key], index) => (
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
