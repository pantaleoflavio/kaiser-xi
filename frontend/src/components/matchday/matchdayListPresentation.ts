import type { HeadToHeadFixture, HeadToHeadSchedule } from '../../types/api';
import type { Matchday } from '../../types/formation';
import type { FantasyTeam } from '../../types/league';

export type MatchdayState = 'past' | 'current' | 'upcoming';

export type MatchdayListItemPresentation = {
  item: Matchday;
  state: MatchdayState;
  fixture?: HeadToHeadFixture;
  scheduleInitialized: boolean;
  formationAllowed: boolean;
};

function findTeamFixture(
  schedule: HeadToHeadSchedule | undefined,
  matchdayId: number,
  teamId: number | undefined,
) {
  if (!teamId || !schedule?.initialized) return undefined;

  return schedule.matchdays
    .find((group) => group.matchday.id === matchdayId)
    ?.fixtures.find(
      (fixture) =>
        fixture.home_fantasy_team.id === teamId || fixture.away_fantasy_team.id === teamId,
    );
}

export function buildMatchdayListPresentation(
  matchdays: Matchday[],
  myTeam: FantasyTeam | undefined,
  schedule: HeadToHeadSchedule | undefined,
  isHeadToHead: boolean,
  now: number,
): MatchdayListItemPresentation[] {
  const current = matchdays.find((item) => new Date(item.deadline).getTime() > now);
  const scheduleInitialized = Boolean(schedule?.initialized);
  const scheduledMatchdayIds = new Set(schedule?.matchdays.map((group) => group.matchday.id) ?? []);

  return matchdays.map((item) => ({
    item,
    state:
      new Date(item.deadline).getTime() <= now
        ? 'past'
        : item.id === current?.id
          ? 'current'
          : 'upcoming',
    fixture: findTeamFixture(schedule, item.id, myTeam?.id),
    scheduleInitialized: Boolean(
      myTeam && schedule?.initialized && scheduledMatchdayIds.has(item.id),
    ),
    formationAllowed: !isHeadToHead || (scheduleInitialized && scheduledMatchdayIds.has(item.id)),
  }));
}
