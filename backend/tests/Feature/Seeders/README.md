# Demo seeder test audit

Demo seeders build presentation data, not general-purpose test fixtures. Tests in
this directory therefore use one of these classifications:

- **A — focused:** a small seeder contract that is cheap enough for the normal
  suite;
- **B — demo integration:** unique verification of a complete demonstration
  scenario, explicitly placed in the `demo-integration` group;
- **C — redundant:** deleted because focused tests cover the behavior without a
  demo environment.

## Current tests

| Test | Class | Seeders and resulting scope | Unique behavior |
| --- | --- | --- | --- |
| `DemoLeagueSeederTest::test_demo_league_roster_settings_are_seeded_idempotently` | A | Reference lookup seeders from `TestCase`, then `RealCompetitionSeeder` and `DemoLeagueSeeder` twice. Creates one season, one league, four clubs, nine users/teams, and four asserted settings; no players, formations, results, or other demo leagues. | The demo league itself supplies each roster/scoring setting exactly once when rerun. |
| `DemoEnvironmentSeederTest::test_full_demo_environment_is_complete` | B | `DemoEnvironmentSeeder`, which deliberately composes all lookup, player-pool, roster, Classic, Formula One, and both Head-to-Head seeders. It creates four demo leagues and their dependent scenarios. | This is the single full-environment orchestration smoke test: it proves the top-level seeder can compose all scenarios and preserve the shared free-agent pool. |
| `DemoClassicChampionshipSeederTest::test_classic_championship_scenario_is_complete_and_independently_idempotent` | B | The shared lookup/demo league/player foundation, `DemoFantasyRostersSeeder`, and `DemoClassicChampionshipSeeder` twice. It creates one nine-team league, at least 135 active roster assignments, eight matchdays, 28 scenario formations/team scores, their formation players/player scores, and nine standings. | Proves the Classic presentation scenario, its deliberately missing formation, current drafts, calculated table, roster capacity, and independent rerun contract. It does not load Formula One or Head-to-Head data. |
| `DemoFormulaOneChampionshipSeederTest::test_formula_one_scenario_is_complete_and_independently_idempotent` | B | The shared lookup/demo league/player foundation and `DemoFormulaOneChampionshipSeeder` twice. The dedicated scenario contains six teams, 90 assignments, eight matchdays, 19 formations, 17 team scores, formation/player-score details, and six standings. | Proves the Formula One demo's score matrix, tie ordering, standings, missing formation, current drafts, future boundary, and independent rerun contract. It does not load Classic or Head-to-Head result seeders. |
| `DemoHeadToHeadLeagueSeederTest::test_schedule_lab_is_uninitialized_and_independently_idempotent` | B | Lookup/demo league foundation without players, then `DemoHeadToHeadLeagueSeeder` twice. Creates one isolated six-team league and 12 future matchdays, with zero matches/results/formations. | Proves the schedule-lab demo intentionally remains uninitialized and can be rerun independently. No player pool or results scenario is loaded. |
| `DemoHeadToHeadResultsSeederTest::test_results_arena_is_complete_isolated_and_independently_idempotent` | B | Shared lookup/demo league/player foundation and `DemoHeadToHeadResultsSeeder` twice. Creates a dedicated six-team league, 90 assignments, 14 matchdays, 42 matches, 12 completed results, scenario formations/scores/details, and six standings. | Proves the results-arena fixture has past results, current drafts, no premature future data, and an independent rerun contract. It does not invoke the schedule-lab, Classic, or Formula One seeders. |
| `DemoPlayerPoolSeederTest::test_shared_pool_has_required_role_capacity_and_explicit_free_agents` | B | Shared lookup/demo league foundation including `DemoPlayersSeeder` and `DemoExtendedPlayerPoolSeeder`. Creates 140 registered players (18 goalkeepers, 47 defenders, 47 midfielders, and 28 forwards) for the shared demo season, but no rosters/results. | Proves the presentation pool has the exact cross-scenario capacity and registrations required by downstream demo seeders. |

## Deleted redundant tests (C)

- `DemoFormulaOneChampionshipSeederTest::test_formula_one_api_supports_the_manual_matchday_and_formation_contracts` loaded a complete six-team, 90-player-assignment scenario to test HTTP contracts. Focused factory coverage already exists in `ClassicMatchdayApiTest` for championship matchday scoping, past missing-formation results, and current result visibility; `FormationApiTest` covers formation access and championship boundaries; `TeamMatchdayScoreApiTest` covers score retrieval; and `CalculateFormulaOneStandingsTest` plus `RankFormulaOneMatchdayTest` cover Formula One placement/points behavior.
- `DemoPlayerPoolSeederTest::test_eligible_player_endpoint_uses_reserved_dolomiti_midfielders_without_heavy_results_seeders` loaded 140 registered players and nine full rosters for an API query. `EligiblePlayerApiTest` uses minimal factories to cover season eligibility, active-assignment exclusion, released/cross-league players, role and club filters, availability payloads, pagination, and ordering. The retained player-pool integration test uniquely verifies that the named free-agent/pool seed data exists; the endpoint need not know those demo identities.

Run focused development tests with
`php artisan test --exclude-group=demo-integration` and opt into these scenario
checks with `php artisan test --group=demo-integration`. The default PHPUnit configuration
continues to run both groups so CI does not silently omit the smoke tests.