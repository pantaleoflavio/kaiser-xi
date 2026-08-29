# Kaiser XI
**Your League. Your Rules.**

Kaiser XI is a full-stack fantasy football platform inspired by the Italian Fantacalcio model.

The application is designed to support multiple real football competitions, seasons and custom fantasy leagues within a single deployment. It separates real-football data, global platform administration and league-specific gameplay permissions.

## Project status

Kaiser XI is under active development.

Currently implemented:

* Laravel REST API
* React authentication interface
* Docker-based local development environment
* PostgreSQL database
* Laravel Sanctum authentication
* registration, login, logout and password reset
* protected frontend routes
* global role hierarchy
* league-specific role model
* multi-competition football domain
* Filament administration panel
* English, German and Italian administration interface
* session-based language switcher
* database factories and seeders
* automated backend tests

The next development phase focuses on fantasy league creation, membership and league-specific authorization.

## Architecture

Kaiser XI uses a monorepo structure:

```text
Kaiser XI/
├── backend/          Laravel API and Filament administration
├── frontend/         React application
├── docs/             Development documentation
├── docker-compose.yml
└── .github/workflows/
```

The application follows an API-first architecture:

```text
React frontend
      ↓
Laravel REST API
      ↓
PostgreSQL
```

Filament provides a separate internal interface for managing competitions, seasons, clubs, players, fixtures, scores, users and roles.

Internal reference documentation:

* [CSV import system](docs/csv-import-system.md)
* [Super Admin dashboard](docs/super-admin-dashboard.md)

## Technology stack

### Backend

* PHP 8.5
* Laravel 13
* PostgreSQL
* Laravel Sanctum
* Filament 5
* FrankenPHP
* PHPUnit
* Laravel Pint

### Frontend

* React
* TypeScript
* Vite
* React Router
* TanStack Query
* React Hook Form
* Zod
* Tailwind CSS

### Infrastructure

* Docker Compose
* GitHub Actions
* production-oriented Docker images

## Domain model

The real-football domain separates global identities from competition-specific participation.

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

Important design decisions:

* clubs and players are stored as global identities
* a club participates in a competition through a season registration
* player club, role and quotation data are season-specific
* matchdays belong to competition seasons
* real matches reference clubs registered for the same season
* global platform roles are separate from fantasy league roles

## Authorization model

Global platform roles:

* `super_admin`
* `global_admin`
* `user`

League-specific roles:

* `commissioner`
* `co_commissioner`
* `participant`

A user's role inside one fantasy league does not grant global administration privileges.

## API

All API endpoints are versioned under:

```text
/api/v1
```

Currently available:

```text
GET  /api/v1/health

POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me

POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
```

List all registered API routes:

```bash
docker compose exec backend php artisan route:list --path=api/v1
```

## Administration panel

The internal Filament panel is available at:

```text
http://127.0.0.1:8000/admin
```

Access rules:

* `super_admin` manages domain data, users and global roles
* `global_admin` manages domain data
* regular users cannot access the panel

The panel supports English, German and Italian. The selected locale is stored in the browser session.

## Local development

Create the local environment files:

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

Start the application:

```bash
docker compose up -d --build
```

Available services:

```text
Frontend:     http://localhost:5173
Backend API:  http://127.0.0.1:8000
Admin panel:  http://127.0.0.1:8000/admin
Adminer:      http://localhost:8080
PostgreSQL:   localhost:5433
```

Check running containers:

```bash
docker compose ps
```

Stop the environment:

```bash
docker compose down
```

## Quality checks

Run migrations and seeders:

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

Run backend tests:

```bash
docker compose exec backend php artisan test
```

Check PHP formatting:

```bash
docker compose exec backend ./vendor/bin/pint --test
```

Build the frontend:

```bash
docker compose exec frontend npm run build
```

Validate dependencies and autoloading:

```bash
docker compose exec backend composer validate
docker compose exec backend composer audit --locked
docker compose exec backend composer dump-autoload -o
```

Check the API:

```bash
curl http://127.0.0.1:8000/api/v1/health
```

## Engineering approach

The backend follows these conventions:

* thin controllers
* Form Requests for validation
* API Resources for JSON responses
* Policies for authorization
* services and actions for business workflows
* granular database migrations
* explicit Eloquent relationships
* database constraints for domain integrity
* automated feature and domain tests
* PSR-4 compliant namespaces

The frontend uses typed validation, API state management and protected routing to keep presentation and backend concerns separated.

# Testing

Run backend tests from `/app` in the backend container with:

```sh
composer test
```

The command forces Laravel's dedicated PostgreSQL test database before PHP
starts and forwards PHPUnit options (for example, `composer test -- --filter=AuthTest`).
The base test case also rejects any bootstrapped application that is not in the
`testing` environment or whose configured database does not end in `_test` or
`_testing`, before Laravel initializes destructive database test traits.