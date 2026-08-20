import type { HeadToHeadFixture } from '../../types/api';
import type { ChampionshipMatchdayTeamResult } from '../../types/results';
import { useTranslation } from '../../i18n';
import { ClassicTeamResultCard } from './ClassicTeamResultCard';
import { FormulaOneMatchdayResults } from './FormulaOneMatchdayResults';
import { HeadToHeadMatchCard } from './HeadToHeadMatchCard';

type BaseProps = { leagueId: string; matchdayId: number };

export function HeadToHeadMatchdayDetail({
  currentTeamId,
  fixtures,
  leagueId,
  matchdayId,
}: BaseProps & { currentTeamId?: number; fixtures: HeadToHeadFixture[] }) {
  const { t } = useTranslation();
  return (
    <section className="space-y-4" aria-labelledby="fixtures-title">
      <h2 className="text-2xl font-semibold text-white" id="fixtures-title">
        {t('results.matchdayResults')}
      </h2>
      {fixtures.map((fixture) => (
        <HeadToHeadMatchCard
          currentTeamId={currentTeamId}
          fixture={fixture}
          key={fixture.id}
          leagueId={leagueId}
          matchdayId={matchdayId}
        />
      ))}
      {!fixtures.length ? (
        <p className="rounded-xl bg-slate-900/70 p-5 text-slate-300">{t('results.noFixtures')}</p>
      ) : null}
    </section>
  );
}

export function ClassicMatchdayDetail({
  leagueId,
  matchdayId,
  teams,
}: BaseProps & {
  teams: ChampionshipMatchdayTeamResult[];
}) {
  const { t } = useTranslation();
  return (
    <section className="space-y-4" aria-labelledby="classic-results-title">
      <h2 className="text-2xl font-semibold text-white" id="classic-results-title">
        {t('results.matchdayResults')}
      </h2>
      {teams.map((entry) => (
        <ClassicTeamResultCard
          entry={entry}
          key={entry.fantasy_team.id}
          leagueId={leagueId}
          matchdayId={matchdayId}
        />
      ))}
      {!teams.length ? (
        <p className="rounded-xl bg-slate-900/70 p-5 text-slate-300">
          {t('results.noClassicParticipants')}
        </p>
      ) : null}
    </section>
  );
}

export function FormulaOneMatchdayDetail({
  counted,
  leagueId,
  matchdayId,
  teams,
}: BaseProps & {
  counted: boolean;
  teams: ChampionshipMatchdayTeamResult[];
}) {
  const { t } = useTranslation();
  return (
    <section className="space-y-4" aria-labelledby="formula-one-results-title">
      <h2 className="text-2xl font-semibold text-white" id="formula-one-results-title">
        {counted ? t('formulaOne.matchdayPlacements') : t('formulaOne.participantStatus')}
      </h2>
      {teams.length ? (
        <FormulaOneMatchdayResults
          counted={counted}
          leagueId={leagueId}
          matchdayId={matchdayId}
          teams={teams}
        />
      ) : (
        <p className="rounded-xl bg-slate-900/70 p-5 text-slate-300">
          {t('results.noClassicParticipants')}
        </p>
      )}
    </section>
  );
}
