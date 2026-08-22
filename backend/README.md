# FantaMeister Backend

Laravel API backend for **FantaMeister**, a multi-competition fantasy football platform.

The backend is designed to support multiple real football competitions, seasons, clubs and players within a single deployment, while keeping global administration separate from league-specific permissions.

## Tech stack

* PHP 8.5
* Laravel 13
* PostgreSQL
* Laravel Sanctum
* Filament 5
* FrankenPHP
* Docker Compose
* PHPUnit
* Laravel Pint

## Current features

* REST API versioned under `/api/v1`
* User registration and authentication with Laravel Sanctum
* Password reset flow
* Global role hierarchy:

  * `super_admin`
  * `global_admin`
  * `user`
* League-scoped roles:

  * `commissioner`
  * `co_commissioner`
  * `participant`
* Multi-competition real-football domain
* Filament administration panel
* English, German and Italian admin translations
* Session-based language switcher
* League lifecycle management
* League memberships with scoped roles
* Fantasy team management
* Policy-based authorization
* API Resources and Form Requests

## Domain architecture

The real-football domain separates global entities from season-specific participation.

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

Key design decisions:

* real clubs and players are global identities
* clubs participate in competitions through `season_clubs`
* players are associated with clubs, roles and quotations through season registrations
* matchdays belong to seasons
* real matches reference the participating season clubs
* fantasy league permissions remain separate from global platform roles

## Architecture

```text
Authentication
        │
        ▼
Controllers
        │
        ▼
Form Requests
        │
        ▼
Policies
        │
        ▼
Services
        │
        ▼
Eloquent Models
        │
        ▼
API Resources
```

## API

Implemented endpoints:

```text
GET    /api/v1/health

POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password

GET    /api/v1/leagues
POST   /api/v1/leagues
GET    /api/v1/leagues/{league}
PATCH  /api/v1/leagues/{league}
DELETE /api/v1/leagues/{league}

GET    /api/v1/leagues/{league}/members

GET    /api/v1/leagues/{league}/invitations
POST   /api/v1/leagues/{league}/invitations
DELETE /api/v1/leagues/{league}/invitations/{invitation}

GET    /api/v1/league-invitations/{code}
POST   /api/v1/league-invitations/{code}/accept

GET    /api/v1/leagues/{league}/fantasy-teams
POST   /api/v1/leagues/{league}/fantasy-teams
GET    /api/v1/leagues/{league}/fantasy-teams/{fantasyTeam}
PATCH  /api/v1/leagues/{league}/fantasy-teams/{fantasyTeam}
```

List the registered routes:

```bash
docker compose exec backend php artisan route:list --path=api/v1
```

## Administration panel

The internal administration panel is available at:

```text
http://127.0.0.1:8000/admin
```

Access rules:

* `super_admin` can manage domain data, users and global roles
* `global_admin` can manage domain data
* regular users cannot access the panel

The panel supports English, German and Italian. The selected language is stored in the current browser session.

## Local development

From the repository root:

```bash
cp backend/.env.example backend/.env
docker compose up -d --build
```

Run migrations and seeders:

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

Run the backend test suite:

```bash
docker compose exec backend php artisan test
```

Check code formatting:

```bash
docker compose exec backend ./vendor/bin/pint --test
```

Apply formatting:

```bash
docker compose exec backend ./vendor/bin/pint
```

Check Composer metadata and security advisories:

```bash
docker compose exec backend composer validate
docker compose exec backend composer audit --locked
docker compose exec backend composer dump-autoload -o
```

## Engineering conventions

The backend follows these conventions:

* thin controllers
* Form Requests for validation and authorization
* API Resources for response serialization
* Policies for authorization
* dedicated Services for business logic
* Eloquent relationships and route model binding
* database constraints enforcing domain integrity
* factories and seeders for local development
* automated feature tests
* Laravel Pint for code style

## Project status

Completed foundations:

* development infrastructure
* authentication
* global administration
* real-football domain
* league lifecycle
* league memberships
* fantasy team management
* Filament administration
* backend internationalization

Current focus:

* player assignment
* fantasy roster management
* formations
* transfers and auction workflow
* matchday engine
