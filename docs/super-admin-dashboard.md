# Super Admin dashboard audit

## Scope and access

The Filament panel at `/admin` is the internal **global administration** area. Its provider auto-discovers every Resource under `app/Filament/Resources` and custom Page under `app/Filament/Pages`, and also registers Filament's Dashboard, Account widget, information widget, and locale switcher. This audit describes the discovered resources, not filenames assumed to be registered.

`User::canAccessPanel()` permits the `admin` panel only when the user has global role `super_admin` or `global_admin`. A normal user, League commissioner, or co-commissioner gains no panel access merely from a League role. Within the panel, all resources/pages inherit panel access except the following stricter entries:

| Entry | super_admin | global_admin | Normal / commissioner / co-commissioner |
|---|---:|---:|---:|
| Users, Roles | yes | no | no |
| Player Scores | yes | yes  | no |
| All other resources, Dashboard, Import data | yes | yes | no |

There is no global `LeagueResource`; Leagues and their members, teams, settings, matchday calculation, markets, and trades are managed through API/product workflows rather than this Filament navigation. League policy separately allows commissioners/co-commissioners specific League operations, but does not grant global data administration.

## Navigation map

Labels below are the English translations. All listed resources provide list/create/edit pages, row Edit, and bulk Delete unless noted.

| Group | Entry | Purpose / important relationships and actions |
|---|---|---|
| Dashboard | Dashboard | Filament landing page with account and Filament information widgets. |
| Competitions | Real Competitions | Global competition name/code/country/type/active; parent of Seasons. |
| Competitions | Seasons | Competition season, dates and active flag; belongs to RealCompetition. |
| Competitions | Import data | Select/upload/analyse/preview/confirm the eight CSV types. |
| Real Data | Real Clubs | Persistent clubs, external mappings count, country/logo; parent of SeasonClubs. |
| Real Data | Players | Global player identity/profile, mapping count and active flag. |
| Real Data | Season Clubs | Season + RealClub participation, seasonal external identity/display/active. |
| Real Data | Matchdays | Season rounds, number/name and start/end datetimes. |
| Real Data | Real Matches | Fixture linking Matchday and home/away SeasonClubs, kickoff, score and status. This is manual UI only, not a CSV type. |
| Scores | Player Scores | Global registration + Matchday performance and supplied final score; super_admin and global_admin. |
| Real Data | Player Season Registrations | Player, SeasonClub, role, external identity, quotation/shirt/timestamps/active. |
| System | Users | Accounts, email, global roles and password; super_admin only. |
| System | Roles | Global role name/label/level/system flag; super_admin only. |
| Lookups | League Types | Type key/name/description used by Leagues. |
| Lookups | League Statuses | League lifecycle key/label/sort order. |
| Lookups | League Roles | League role key/label (including commissioner concepts). |
| Lookups | Player Roles | Player-position role key/label/sort order. |
| Lookups | Formation Modules | Formation name/label/active. |
| Lookups | Formation Module Requirements | Formation + PlayerRole required count. |

No dedicated scoring-rules/settings resource is registered. Formation resources are lookup/configuration administration, not a League-specific formation editor.

## Global entity workflows

- **Competition/season:** RealCompetition captures normalized global competition metadata; Season selects its competition, name, required dates and active state.
- **Clubs/participation:** RealClub is persistent. SeasonClub selects a Season and RealClub and optionally holds display name, normalized provider/external pair, and active state.
- **Players/registrations:** Player holds global profile data and external identities. PlayerSeasonRegistration selects Player, SeasonClub and PlayerRole and holds provider mapping, quotation, shirt number, registration/release datetimes and active state. Released records remain visible; the generic resource currently provides no filters or special transfer action.
- **Rounds/fixtures:** Matchday belongs to Season and holds number/name/time window. RealMatch selects its Matchday and two seasonal clubs, kickoff, score, and one of the implemented match statuses.
- **Lookups:** global and League role/status/type and formation configuration are mutable through generic create/edit/delete interfaces. Operators must account for records referenced elsewhere; the UI does not add universal historical-delete warnings.

## Player Score administration

Player Scores is global football data and is available to both super administrators and global administrators.

### Form and invariants

- Identity has searchable **Player registration** and **Matchday** selectors. Searching registrations matches display name/external ID, returns at most 50 richly labelled results, and scopes to the selected Matchday's Season. Searching Matchdays matches name/number, returns at most 50, and scopes to the selected registration's Season. Both identity fields are disabled on edit.
- Model validation independently rejects a registration whose Season differs from the Matchday, and database/service validation prevents duplicate registration+matchday scores.
- Raw performance fields are `base_rating` (-99.99..99.99, two decimals), `clean_sheet`, and `is_captain`. The captain toggle means **real-club captain**, not fantasy captain.
- Required non-negative integer event fields are goals, assists, yellow/red cards, own goals, penalties scored/missed/saved, and goals conceded.
- Status options are `pending`, `confirmed`, `did_not_play`; final score is -999.99..999.99/two decimals and required for confirmed status.
- `PlayerScoreService::prepare()` applies `did_not_play` normalization: null rating/final score, every event zero, and both booleans false. `final_score` is entered, not calculated.

Opening a confirmed score's edit page emits a persistent warning that changes do not recalculate League Matchdays. Record deletion requires confirmation and warns about historical traceability; bulk deletion has the same warning. Deletion still remains available and does not recalculate.

### Table

Columns: Matchday label, player display name, real club, player role, base rating, final score, status badge, captain, clean sheet. Search/sort support follows those columns. Filters: Season, Matchday, status, PlayerRole, and ternary missing-final-score. Row edit and warned bulk delete are available.

## Import data administration

Import data is available to both global roles under **Competitions**.

1. Select one of Real competitions, Real clubs, Players, Seasons, Season clubs, Matchdays, Player season registrations, or Player scores.
2. Upload a `.csv` (CSV/plain MIME), maximum **10 MiB**.
3. Read the runtime contract guide: record identifier, create requirements, optional columns, dependency, formats, empty/create/update behavior, canonical header, example, and caveats. Download a header-only template or example generated from that same contract.
4. **Analyse** synchronously parses and resolves every row, persists an Import/source, and shows counts and per-row identifier/action/changed fields/warnings/errors.
5. Any error blocks confirmation and persists row errors. Warnings do not block.
6. **Confirm Import** changes a ready record to queued and dispatches one asynchronous job. A duplicate confirm reports that it was already queued/not ready.

The page does **not** display an import-history table, job progress, completed results, row-error history, or a retry action. Operational monitoring therefore uses the queue/log/database tooling; correction means uploading and analysing a new file. See [CSV import system](csv-import-system.md) for execution and failure details.

## Global versus League-specific data

| Category | Current domain examples | Administration consequence |
|---|---|---|
| Global real-football | RealCompetition, Season, RealClub, SeasonClub, Player, PlayerSeasonRegistration, MatchDay, RealMatch, PlayerScore, PlayerRole | Managed globally in Filament (with PlayerScore restricted further). Shared by all Leagues. |
| Global platform lookup/security | User, Role, LeagueType, LeagueStatus, LeagueRole, FormationModule, FormationModuleRequirement | Managed in Filament; User/Role are super-admin-only. |
| League-specific | League, memberships/roles, FantasyTeam, FantasyTeamPlayer, Formation/FormationPlayer, settings, League matchday calculations and team scores, trades/assignments | Not exposed as global Filament resources in this panel; access is handled by League API/policies. |

## Historical-data safety

- Confirmed PlayerScore edits show an explicit persistent non-recalculation warning.
- Single and bulk PlayerScore deletion require confirmation and warn that traceability may be weakened; calculated results remain unchanged.
- PlayerScore identity cannot be changed on edit, and registration/Matchday Season agreement is enforced.
- Registrations retain `released_at` and `is_active`, supporting historical/released rows; CSV refuses to repurpose a direct identity or create a second active same-Season registration.
- FormationPlayer and team-score-detail records preserve relationships used by historical calculation, while trade assignment records represent League history. The generic related-resource deletes do not promise cascade-safe historical repair.
- Neither admin edits nor CSV score imports automatically rerun `CalculateTeamMatchdayScore`; commissioner/co-commissioner recalculation is explicit through the League workflow.

## Current limitations and audit findings

 RealMatch uses the shared CSV importer. There is no automatic registration-transfer workflow, automatic PlayerScore computation, or automatic fantasy recalculation.- Import execution is whole-file atomic and has no partial/chunked success or null-clear sentinel.
- Import history exists in models/storage but is not browsable on the Import data page.
- There is no global League, scoring-rule, or settings resource in Filament.
- PlayerScore deletion retains its explicit traceability warning. Bulk deletion is disabled for PlayerSeasonRegistration, Matchday, Season, SeasonClub, Player and RealClub; single deletion is denied when the record owns or contains PlayerScore history. Released/deactivated records without score history remain removable.