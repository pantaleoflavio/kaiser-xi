# Architecture

## Overview

FantaMeister is organized around two independent but connected domains:

* the **Real Football Domain**, which models real-world football competitions;
* the **Fantasy Domain**, which models user-created fantasy leagues.

The separation between these domains allows the platform to support multiple competitions, seasons and fantasy leagues without duplicating real-world data.

---

# High-level architecture

```text
                  FantaMeister
                        │
        ┌───────────────┴────────────────┐
        │                                │
        ▼                                ▼
 Real Football Domain             Fantasy Domain
        │                                │
        ▼                                ▼
 Seasons, Clubs, Players         Leagues, Teams, Members
```

The real football domain is global.

The fantasy domain references the real football domain but never duplicates it.

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

Design principles:

* clubs are global entities;
* players are global entities;
* seasons connect clubs and competitions;
* player registrations are season-specific;
* matches belong to matchdays.

This model allows players and clubs to move naturally between seasons.

---

# Fantasy Domain

Fantasy gameplay is isolated from the real football data.

```text
League
├── Memberships
│   ├── User
│   └── LeagueRole
├── FantasyTeams
│   ├── User
│   ├── Players
│   ├── Formations
│   └── MatchdayScores
├── Invitations
└── Settings
```

Each fantasy league is an independent environment.

Users participate through memberships.

Each member owns exactly one fantasy team.

Fantasy teams evolve independently while sharing the same underlying football data.

---

# Authorization model

Authorization exists on two distinct levels.

## Global authorization

Platform administration uses global roles.

```text
super_admin
        │
global_admin
        │
      user
```

These roles control administrative capabilities.

---

## League authorization

League permissions are independent from platform administration.

```text
commissioner
        │
co_commissioner
        │
participant
```

League roles only affect actions performed inside a fantasy league.

A global administrator is not automatically a league commissioner.

Likewise, a league commissioner gains no administrative access to the platform.

---

# Request flow

Incoming requests follow the same application pipeline.

```text
HTTP Request
      │
      ▼
Route
      │
      ▼
Middleware
      │
      ▼
Form Request
      │
      ▼
Policy
      │
      ▼
Controller
      │
      ▼
Service
      │
      ▼
Models
      │
      ▼
API Resource
      │
      ▼
HTTP Response
```

Each layer has a single responsibility.

---

# Design principles

The architecture follows these principles:

* explicit domain modeling;
* thin controllers;
* isolated business logic;
* policy-based authorization;
* explicit Eloquent relationships;
* database-enforced integrity;
* RESTful API design;
* separation between real football and fantasy gameplay.

The objective is to keep the system maintainable while allowing new fantasy features to be introduced without affecting the underlying football domain.

---

# Planned evolution

The architecture is intended to support future modules including:

* player auctions;
* transfers;
* roster management;
* formations;
* matchday calculations;
* live standings;
* multiple fantasy competition formats.

These features build upon the existing domain model without requiring structural changes.
