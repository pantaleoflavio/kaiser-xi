# Domain Model

## Purpose

This document describes the business domain of FantaMeister.

It explains the concepts represented by the application, their relationships and the business rules that govern them.

Implementation details are intentionally omitted.

---

# Domain overview

FantaMeister consists of two interconnected domains:

* **Real Football**, representing real-world football competitions.
* **Fantasy Football**, representing user-created fantasy leagues.

The fantasy domain depends on the real football domain but never modifies it.

---

# Real Football Domain

The real football domain models football independently from fantasy gameplay.

```text
RealCompetition
└── Season
    ├── SeasonClub
    │   └── RealClub
    ├── PlayerSeasonRegistration
    │   ├── Player
    │   ├── PlayerRole
    │   └── SeasonClub
    └── Matchday
        └── RealMatch
```

## Real Competition

A real competition represents a football competition such as:

* Serie A
* Bundesliga
* Premier League
* Champions League

Competitions are global and reusable across seasons.

---

## Season

A season belongs to one real competition.

A season defines:

* participating clubs;
* registered players;
* matchdays;
* matches.

Each season is independent from previous and future seasons.

---

## Clubs

A club exists independently from any season.

Its participation in a specific season is represented through a Season Club.

This allows clubs to move between competitions without changing their identity.

---

## Players

Players are global entities.

Their registration for a season stores information such as:

* current club;
* playing role;
* quotation;
* registration period.

Players may change clubs between seasons while preserving their identity.

---

## Matchdays

Matchdays belong to a season.

Each matchday contains multiple real matches.

---

# Fantasy Domain

Fantasy football is modeled independently from real football.

```text
League
├── Membership
├── FantasyTeam
├── Invitation
└── Settings
```

---

## League

A league is a private fantasy competition created by users.

A league belongs to a season.

Multiple fantasy leagues may coexist for the same season.

---

## Membership

Users participate in leagues through memberships.

Each membership stores:

* user;
* league;
* league role;
* join date.

Memberships define permissions inside a league.

---

## League Roles

League roles are independent from platform roles.

Available roles are:

* commissioner
* co_commissioner
* participant

Only commissioners and co-commissioners may manage league administration.

---

## Fantasy Team

Each league member owns exactly one fantasy team.

A fantasy team belongs to:

* one league;
* one user.

Fantasy teams contain:

* players;
* formations;
* matchday scores.

Names and visual identity may be customized by their owner.

---

## Invitations

League invitations allow users to join private leagues.

An invitation is associated with:

* one league;
* one invited user or invitation code;
* expiration and acceptance state.

---

## League Settings

League settings define gameplay configuration.

Examples include:

* budget;
* scoring rules;
* roster size;
* transfer rules;
* competition format.

---

# Authorization

Authorization is divided into two independent scopes.

## Platform roles

Platform roles control administration of the application.

* super_admin
* global_admin
* user

These roles never grant league permissions.

---

## League roles

League roles only affect actions inside a fantasy league.

* commissioner
* co_commissioner
* participant

League permissions never grant platform administration rights.

---

# Core business rules

The current domain enforces the following rules:

* A user may belong to multiple leagues.
* A user may own at most one fantasy team per league.
* A fantasy team belongs to exactly one league.
* A fantasy team belongs to exactly one user.
* A league belongs to exactly one season.
* A season belongs to exactly one real competition.
* Clubs and players are global entities.
* Player registrations are season-specific.
* League permissions are isolated from platform permissions.

These rules are enforced through application logic and database constraints whenever appropriate.

---

# Future domain evolution

The domain is designed to support future functionality including:

* player auctions;
* transfer windows;
* roster validation;
* real-match captain bonus mechanics;
* formation management;
* live scoring;
* standings;
* playoffs;
* multiple fantasy competition formats.

The existing domain model has been designed to accommodate these features without major structural changes.
