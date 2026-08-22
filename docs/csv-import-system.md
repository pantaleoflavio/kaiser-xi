# CSV import system

## Scope and source of truth

The CSV subsystem loads **global real-football data** into the normalized domain model. Competitions, seasons, persistent clubs, seasonal club entries, players, seasonal registrations, matchdays, and scores are separate because they have different lifetimes and identities. It does not import fantasy Leagues or mutate League settings, markets, formations, trades, or calculated results.

This document describes the current implementation. `CsvImportType` and each importer's `contract()` are authoritative; use the **Import data** page's current guide/template download before producing a file. RealMatch has an admin resource but **no CSV import type**.

## File and row rules

Files must be UTF-8 (a BOM is accepted), comma-delimited CSV with an exact lowercase `snake_case` header. Quoting follows PHP's RFC-4180-compatible CSV parser. Unknown, duplicate, invalid, or missing required header columns fail parsing; blank physical rows are ignored; every other row must have the header's column count. At least one data row is required. The UI accepts `.csv` files reported as CSV/plain text, up to **10 MiB**.

Only columns present in the uploaded header are supplied. Unless an importer says otherwise, an optional empty cell means **not supplied**: updates preserve the stored value and creates use an importer/database default where one exists. CSV has no generic null/delete sentinel. Exceptions are Matchday `name` (an included empty value clears it) and PlayerScore `did_not_play` normalization.

### Actions and messages

| Preview action | Meaning |
|---|---|
| `create` | A new domain record will be written. |
| `update` | The resolved record has supplied semantic changes (including a new external mapping). |
| `unchanged` | The record already matches; execution performs no write. |
| `error` | A blocking row. Confirmation is disabled and no domain rows execute. |

Warnings are non-blocking. Currently, changed/new confirmed PlayerScores warn that fantasy results are not recalculated. Errors block the whole import.

## Identity conventions

Providers are trimmed and lowercased. External IDs are opaque: their case, punctuation, and otherwise exact text are retained and compared exactly. A provider and external ID must be supplied as a complete pair; partial pairs are errors. Names are descriptive, not stable identities. RealClub and Player imports prefer external identity and permit their normalized slug fallback. Competition code is normalized by trimming, slugging with `_`, and lowercasing (for example `Serie A` becomes `serie_a`). Country codes are uppercased where imported.

## Architecture and lifecycle

```text
synchronous: upload -> parse -> analyse -> preview -> save Import + immutable source
                                                     | blocked (errors)
                                                     v
              Confirm -> ready -> queued + dispatch
                                      |
asynchronous:                          v
 ExecuteCsvImportJob -> importing -> reload/check checksum -> fresh analysis
     -> one DB transaction for all domain writes -> completed
     -> exception/rollback -> failed + ImportRowError history
```

`pending`, `ready`, `blocked`, `queued`, `importing`, `completed`, and `failed` are the defined statuses. In the UI, analysis immediately creates history as `ready`; validation errors change it to `blocked`. (`pending` exists but this workflow does not assign it.) Confirm locks the row and changes only `ready` to `queued`, preventing duplicate dispatch. The one-attempt queued job changes only `queued` to `importing`, so redelivery is a no-op. It reloads the saved source, verifies its SHA-256 checksum, and performs a fresh analysis. Errors discovered then fail execution. All importer writes share one database transaction: there is no chunking or partial success.

On success, all analysed rows count as successful (including unchanged rows). On failure, domain writes roll back, status becomes `failed`, and row errors retain row number/data and validation or execution text. A pre-analysis/source error is stored at row 1. The Import data screen shows the current preview but has no history browser or retry control; correct the source and analyse it as a new import. Do not edit stored source files: checksum mismatch fails them.

All importers re-resolve key records during execution and reject identities that changed since analysis. This protects against stale previews. CSV duplicates of a natural/provider identity are errors.

## Dependency map and order

```text
RealCompetition -> Season -> Matchday --------------------------+
                         -> SeasonClub <- RealClub               |
                                               |                |
Player ----------------------------------------+                |
  + SeasonClub + PlayerRole -> PlayerSeasonRegistration --------+-> PlayerScore
```

Recommended order: **(1)** RealCompetition, **(2)** RealClub, **(3)** Player, **(4)** Season, **(5)** SeasonClub, **(6)** Matchday, **(7)** RealMatch, **(8)** PlayerSeasonRegistration, **(9)** PlayerScore. RealMatch and PlayerSeasonRegistration are both downstream of Season/SeasonClub; neither strictly depends on the other. Player does not depend on RealClub; steps 2 and 3 may be exchanged. PlayerRole is a lookup managed outside CSV and must exist before registrations.

## Import contracts

In every section, “duplicate” means a blocking duplicate within the file; execution also checks that the analysed identity still resolves to the same record.

### RealCompetition

- **Purpose/model/dependencies:** global competition (`RealCompetition`); no parent, and required by Season.
- **Canonical header:** `code,name,type,country_code,is_active`; required header: `code`.
- **Create:** requires `code`, `name`, `type`; `is_active` defaults true. Optional `country_code`, `is_active`.
- **Identity/normalization:** normalized `code`; names never match. `country_code` becomes uppercase. Type must be `domestic_league`, `domestic_cup`, `international_club`, or `custom`; boolean is `true`/`false`.
- **Updates/empty cells:** non-empty supplied values update; empties preserve. The code locates the record and is not changed separately. Matching rows are unchanged.
- **Duplicate/caveat:** repeated normalized code errors. Downstream Season rows use this code.
- **Example:** `bundesliga,Bundesliga,domestic_league,DE,true`

### RealClub

- **Purpose/model/dependencies:** persistent real-world club (`RealClub`), independent of a season; imported before SeasonClub.
- **Canonical header:** `club_provider,club_external_id,club_slug,name,short_name,country_code,logo_path`; no universally required header (row identity rules still apply).
- **Create:** requires `name`, `short_name`, `club_slug`; provider pair, country and logo are optional.
- **Identity:** preferred complete provider pair, fallback normalized slug; if both resolve they must identify the same club. A new provider mapping can update an existing slug match atomically.
- **Normalization/formats:** provider lowercased, external ID exact, slug canonicalized, country uppercase/two letters; short name max 32, other strings max 255.
- **Updates/empty cells:** supplied non-empty payload only; empty optional values preserve and cannot clear. Matching rows are unchanged.
- **Duplicate/caveat:** duplicate pair or slug errors; names never match. `RealClub` persists across years; `SeasonClub` is its participation in one Season.
- **Example:** `opta,Club-001,fc-example,FC Example,FCE,DE,`

### Player

- **Purpose/model/dependencies:** global person (`Player`), later referenced by registration; no club, role, quotation, or shirt assignment belongs here.
- **Canonical header:** `player_provider,player_external_id,player_slug,first_name,last_name,display_name,birth_date,is_active`; no universally required header.
- **Create:** requires `display_name`, `player_slug`; provider pair and other fields optional; active defaults true.
- **Identity:** preferred complete provider pair, fallback normalized slug; both must agree. Names never match.
- **Formats:** provider lowercase; external ID exact; `birth_date` `YYYY-MM-DD`; boolean `true`/`false`.
- **Updates/empty cells:** non-empty supplied values only; blank name/date does not clear it. A provider mapping may be attached atomically. Matching rows are unchanged.
- **Duplicate/caveat:** duplicate pair/slug errors. Later imports resolve the provider mapping, not an old `Player.external_id` field.
- **Example:** `opta,Player-001,jane-doe,Jane,Doe,Jane Doe,2000-01-02,true`

### Season

- **Purpose/model/dependencies:** named date range for a RealCompetition (`Season`); competition must exist.
- **Canonical header:** `competition_code,season_name,starts_at,ends_at,is_active`; required header: `competition_code,season_name`.
- **Create:** requires both identity fields and `starts_at`,`ends_at`; active defaults true. Dates are `YYYY-MM-DD`, with end on/after start.
- **Identity:** normalized competition code + exact trimmed season name. Later imports use the same pair.
- **Updates/empty cells:** supplied dates/active update; blanks preserve. Matching payload is unchanged.
- **Duplicate/caveat:** duplicate pair or unknown code errors. CSV does not move a Season between competitions.
- **Example:** `bundesliga,2026/27,2026-08-01,2027-05-31,true`

### SeasonClub

- **Purpose/model/dependencies:** one persistent RealClub participating in one Season (`SeasonClub`); Season and RealClub must exist.
- **Canonical header:** `competition_code,season_name,club_provider,club_external_id,club_slug,season_club_provider,season_club_external_id,display_name,is_active`; required header: `competition_code,season_name`.
- **Create:** identity pair plus RealClub via complete club provider pair or slug. Seasonal provider pair, display name, active are optional; active defaults true.
- **Two identities:** global club columns resolve `RealClub`; natural seasonal identity is Season + RealClub. `season_club_provider` + `season_club_external_id` optionally gives that participation a direct global provider mapping. Partial pairs error and supplied global identity/slug must agree.
- **Normalization/formats:** competition code/slug canonicalized, providers lowercased, IDs exact, boolean `true`/`false`.
- **Updates/empty cells:** non-empty optional values update; blanks preserve. It cannot move an existing SeasonClub to another Season/RealClub.
- **Duplicate/conflict:** duplicate natural or seasonal external identity errors; an external identity resolving to another participation errors.
- **Example:** `bundesliga,2026/27,opta,Club-001,fc-one,opta,Entry-001,FC One,true`

### Matchday

- **Purpose/model/dependencies:** a real competition round within one Season (`MatchDay`); Season must exist and PlayerScore later refers to it.
- **Canonical header:** `competition_code,season_name,matchday_number,name,starts_at,ends_at`; required header: first three fields.
- **Create:** requires identity and both timestamps; `name` optional. Number is integer 0–65535.
- **Identity:** Season + number. Competition code is canonicalized.
- **Dates/timezones:** timestamps must be ISO-8601 with explicit `Z` or `±HH:MM`; values are converted to UTC for storage/comparison. End must not precede start.
- **Updates/empty cells:** supplied timestamps update and omitted ones preserve. Unlike the general rule, an included empty `name` clears it to null. Matching payload is unchanged.
- **Duplicate/caveat:** duplicate identity, unknown Season, or timezone-less timestamp errors.
- **Example:** `bundesliga,2026/27,1,,2026-08-21T20:30:00+02:00,2026-08-23T20:30:00+02:00`

### RealMatch

- **Purpose/model/dependencies:** a fixture/result (`RealMatch`) within an existing Matchday, between two existing SeasonClubs. Competition, Season, Matchday, both global RealClub provider identities, and both seasonal memberships must exist.
- **Canonical header:** `competition_code,season_name,matchday_number,home_club_provider,home_club_external_id,away_club_provider,away_club_external_id,kickoff_at,home_score,away_score,status`; the first seven identity columns are required headers.
- **Create:** requires all identity fields plus `kickoff_at` and `status`; scores are optional and remain null when omitted.
- **Identity:** authoritative database identity is Matchday + home SeasonClub + away SeasonClub. CSV resolves normalized competition code + exact trimmed season name + matchday number, then each provider/external ID through RealClubExternalIdentity -> RealClub -> SeasonClub for that Season. There is currently no RealMatch external identity.
- **Normalization/formats:** providers are trimmed/lowercased; opaque external IDs remain exact. Kickoff requires ISO-8601 with explicit `Z` or `±HH:MM` and is normalized to UTC. Scores are integers from 0 through 65535. Status values are `scheduled`, `in_progress`, `finished`, `postponed`, and `cancelled`, sourced in code from `RealMatchStatus`.
- **Updates/empty cells:** only `kickoff_at`, `home_score`, `away_score`, and `status` are mutable. A non-empty supplied value participates in semantic comparison; empty optional update cells preserve stored values. Zero is a supplied score. There is no null-clear sentinel, so CSV cannot clear a stored score to null.
- **Duplicate/invariants:** every row sharing the same resolved identity is an error; an existing database identity is instead update/unchanged. Home and away must differ and both belong to the Matchday Season. Analysis batch-loads dependencies and writes nothing.
- **Stale analysis:** execution skips error/unchanged rows, rechecks Matchday/SeasonClub Season membership and the expected create/update database identity, then saves through normal Eloquent invariants. Changed identity state raises an exception and the shared whole-import transaction rolls back.
- **Example:** `serie_a,2026/27,1,opta,Club-Home,opta,Club-Away,2026-08-22T20:45:00+02:00,2,1,scheduled`

### PlayerSeasonRegistration

- **Purpose/model/dependencies:** the season-specific combination **Player + SeasonClub + PlayerRole/context** (`PlayerSeasonRegistration`). Competition, Season, Player and club provider mappings, SeasonClub, and PlayerRole key must exist.
- **Canonical header:** `competition_code,season_name,player_provider,player_external_id,club_provider,club_external_id,registration_provider,registration_external_id,player_role,quotation,shirt_number,registered_at,released_at,is_active`.
- **Required header/create:** first six fields are headers; create additionally needs `player_role`. Player and club identities are always complete provider pairs.
- **Identity:** preferred direct registration provider pair; otherwise Player + SeasonClub natural identity. When direct and natural identities are supplied, they must resolve to the same registration. Providers normalize lowercase; IDs remain exact.
- **Fields/formats:** role is a `PlayerRole.key`; quotation is 0..999999.99 (two decimals); shirt number integer 0..65535; timestamps require ISO-8601 explicit offset and are stored in UTC; active is `true`/`false`.
- **Updates/empty cells:** only non-empty role, quotation, number, timestamps, and active are supplied; blanks never clear or zero them. Creates use defaults, including active true. Existing released registrations remain historical and are not repurposed for another club.
- **Active/transfer rule:** active means `is_active=true` and `released_at` null. Only one such registration per player/Season is accepted. There is no automatic transfer orchestration: first import an update that deactivates (`is_active=false`) and/or releases the old registration, confirm its completion, then import the new club registration.
- **Duplicate/caveat:** duplicate natural/direct identity errors; unknown role/mappings, mismatched direct identity, or another active registration errors. There is no empty-cell way to clear `released_at` and reactivate a released row.
- **Example:** `serie_a,2026/27,opta,Player-001,opta,Club-001,opta,Registration-001,forward,25.50,9,2026-08-20T12:00:00+02:00,,true`

### PlayerScore

- **Purpose/model/dependencies:** global real-world performance (`PlayerScore`), never League-specific. Competition, Season, Matchday, registration, Player/club fallback mappings and same-Season relationship must already exist.
- **Canonical header:** `competition_code,season_name,matchday_number,registration_provider,registration_external_id,player_provider,player_external_id,club_provider,club_external_id,status,base_rating,goals,assists,yellow_cards,red_cards,own_goals,penalties_scored,penalties_missed,penalties_saved,goals_conceded,clean_sheet,is_captain,final_score`.
- **Required header/create:** competition, season, number, status; create needs a complete direct registration pair or complete Player **and** club pairs. `confirmed` additionally requires `base_rating`.
- **Identity:** resolved PlayerSeasonRegistration + Matchday. Direct registration identity is preferred; fallback resolves Player + RealClub -> SeasonClub -> registration in the Season. Both routes, if present, must agree.
- **Formats:** status is `pending`, `confirmed`, or `did_not_play`; base rating -99.99..99.99; final score -999.99..999.99; events are non-negative integers; booleans `true`/`false`; decimals max two places.
- **Updates/empty cells:** optional blanks preserve on update; creates use model/database defaults. `final_score` is optional external/provider data—this import never derives it.
- **Status:** `pending` may omit a base rating; `confirmed` requires a base rating and is playable only with it; `did_not_play` forcibly clears `base_rating` and `final_score`, sets every event to zero, and sets `clean_sheet`/`is_captain` false, irrespective of omitted cells.
- **Duplicate/warning/caveat:** duplicate registration+matchday errors. Changing/new confirmed scores warns about explicit recalculation. Import never deletes a score.
- **Example:** `serie_a,2026/27,1,opta,Registration-001,,,,,confirmed,7.50,1,0,0,0,0,0,0,0,0,true,false,10.00`

## PlayerScore versus fantasy scoring

`PlayerScore` is source performance data. `CalculateTeamMatchdayScore` combines its raw performance fields with League-specific scoring settings. Importing, editing, or deleting a score **does not automatically recalculate** an already calculated League Matchday. If historical fantasy results should reflect a correction, a commissioner/co-commissioner must explicitly invoke the existing affected-matchday recalculation workflow.

## Operational workflows

### Private external-data transformation

Use only sources you are authorized to access: external/scraped source -> extract and normalize -> transform into the downloaded CSV contract -> Import data -> Analyse -> inspect actions/errors/warnings -> Confirm. CSV is the supported bridge from arbitrary source shape; this subsystem neither implements scraping nor bypasses provider terms/access controls.

### Initial season

Import a missing competition (occasional), persistent clubs and global players (initially, then changes), Season (once), SeasonClubs (once plus participation changes), Matchdays (season schedule), registrations (initially and after roster changes), then PlayerScores as rounds occur. Wait for each dependency import to complete before analysing its consumer.

### Recurring matchday

After a real round, obtain authorized performance data, transform it to PlayerScore CSV, Analyse, resolve errors and inspect warnings, Confirm, and monitor the queue outside this page. Explicitly recalculate affected fantasy Matchdays when desired. Import registration changes first after transfers or new players.

### Production execution and recovery

Confirmation dispatches only after the `ready` to `queued` transaction commits. A queued job may take over an `importing` record only after `IMPORT_STALE_AFTER`; configure `IMPORT_QUEUE_TIMEOUT < IMPORT_STALE_AFTER < DB_QUEUE_RETRY_AFTER`. Terminal records never execute again. Terminal job failure changes a queued/importing record to `failed` and stores one execution-level diagnostic rather than copying an infrastructure error to every row.

Uploads are limited to 10 MiB, and analysis and execution parse the complete file in memory. This is not a performance guarantee: benchmark representative files and configure `IMPORT_QUEUE_TIMEOUT`, `DB_QUEUE_RETRY_AFTER`, and `IMPORT_STALE_AFTER` before launch.

The checksummed execution source remains in private storage. Web and worker processes must share that persistent storage. No automatic cleanup runs; never delete sources for queued/importing imports and define an operator retention policy for terminal records.

## Troubleshooting and current limitations

- **Blocked:** fix duplicate rows, partial/unknown identities, formats, or validation errors and create a new analysis; blocked history cannot be confirmed.
- **Failed:** inspect persisted row errors/log/queue failure, correct the source or concurrent identity change, then upload as a new import. There is no retry/history UI here.
- **Stale/checksum errors:** do not change stored sources; re-analyse after domain identity changes.
- **Current limits:** no RealMatch external identity; no automatic transfer orchestration; no null-clear sentinel; no automatic PlayerScore/final-score calculation; no automatic League recalculation; no chunking/partial success; every import executes atomically. Market behavior is outside this subsystem.
## League-specific fantasy scoring

`PlayerScore` remains global raw real-football performance data. A confirmed score requires a
`base_rating`; `final_score` remains an optional externally supplied/provider-derived value for
compatibility and never controls League fantasy points. `CalculateTeamMatchdayScore` combines the
raw rating and events with each League's bonus/malus settings. Its `base_points` is the sum of those
League-specific individual scores before captain, goalkeeper clean-sheet, and defense modifiers.

The default rules are goal +3, assist +1, yellow card -0.5, red card -1, own goal -2,
penalty scored +3, penalty missed -3, penalty saved +3, and goal conceded -1. The source contract
does not unambiguously state whether `goals` includes `penalties_scored`; scoring therefore safely
treats penalties as a subset of goals (up to the reported goal count), replacing rather than adding
the ordinary goal bonus. This prevents double counting. Clean-sheet and real-captain facts remain
raw provider facts whose optional League bonuses are applied separately. Settings changes do not
automatically recalculate historical results; the explicit recalculation flow remains required.