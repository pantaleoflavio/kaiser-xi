# Development Guide

## Purpose

This document describes the development conventions used throughout the Kaiser XI backend.

The goal is to keep the codebase consistent, maintainable and easy to extend as the project grows.

---

# General principles

The project follows a domain-oriented approach where business rules are kept outside controllers whenever possible.

General principles:

* Keep controllers thin.
* Prefer explicit code over clever code.
* Keep business rules inside dedicated services.
* Validate all incoming requests through Form Requests.
* Authorize access through Policies.
* Return API Resources from API endpoints.
* Prefer composition over duplication.
* Favor readability over micro-optimizations.

---

# Project architecture

The typical request flow is:

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
Eloquent Models
      │
      ▼
API Resource
      │
      ▼
HTTP Response
```

Responsibilities are intentionally separated.

---

# Controller conventions

Controllers should:

* remain small;
* delegate business logic to Services;
* return API Resources;
* never contain validation logic;
* never contain authorization logic other than explicit Gate checks when route middleware cannot be used.

Controllers should not manipulate domain objects directly beyond orchestration.

---

# Validation

Every endpoint accepting user input should use a dedicated Form Request.

Validation rules belong inside Form Requests.

Controllers should never call `Validator::make()` directly.

---

# Authorization

Authorization is handled through Laravel Policies.

Whenever possible, authorization should be applied through route middleware.

Examples:

* view
* create
* update

When policy signatures require additional contextual models that cannot be resolved through route middleware, explicit `Gate::authorize()` calls are acceptable.

---

# Business logic

Business logic belongs inside Services.

Services should:

* implement a single use case;
* receive models or DTO-like values;
* remain framework-light whenever practical.

Controllers should orchestrate Services rather than implementing business rules.

---

# Eloquent models

Models should contain:

* relationships;
* casts;
* scopes;
* model-specific behavior.

Models should not contain orchestration logic.

---

# API Resources

All API responses exposing domain models should use API Resources.

Resources are responsible for:

* response shape;
* field visibility;
* computed attributes;
* nested resources.

---

# Database conventions

The project follows these database conventions:

* granular migrations;
* foreign keys whenever possible;
* explicit unique constraints;
* factories for testing;
* seeders for local development.

Business invariants should be enforced both in the application layer and, where appropriate, at the database level.

---

# Testing

Feature tests should verify complete HTTP behavior.

Unit tests should verify isolated business logic when appropriate.

Factories should generate valid domain objects with minimal configuration.

Tests should clearly express:

* the scenario;
* the action;
* the expected outcome.

---

# Coding style

The project follows:

* PSR-12;
* Laravel Pint;
* typed properties;
* constructor property promotion;
* explicit return types.

Consistency is preferred over personal style.

---

# Local development workflow

Typical workflow:

```bash
php artisan migrate:fresh --seed

php artisan test

./vendor/bin/pint

composer validate

composer audit --locked
```

---

# Git workflow

Recommended workflow:

* create a feature branch;
* keep commits focused on a single concern;
* ensure all tests pass before pushing;
* open a pull request for review.

---

# Related documentation

Additional documentation is available under the `docs/` directory:

* ARCHITECTURE.md
* DOMAIN.md
* API.md
* ROADMAP.md


This document describes the local development workflow for Kaiser XI.

## Requirements

Recommended local environment:

* Windows with WSL2 Ubuntu
* Docker Desktop with WSL integration enabled
* VS Code with Remote WSL
* Git
* Docker Compose

The preferred workflow uses Docker containers for application commands.

## Working directory

Example project path in WSL:

```bash
cd /mnt/c/xampp/htdocs/kaiser-xi
```

## Start the local stack

```bash#
cp backend/.env.example backend/.env
docker compose up -d --build
```

Check services:

```bash
docker compose ps
```

Expected services:

* backend
* frontend
* postgres

## Stop the local stack

```bash
docker compose down
```

Reset local containers and volumes:

```bash
docker compose down -v
```

Use `down -v` only when you intentionally want to reset local PostgreSQL data and named volumes.

## Backend container workflow

Enter the backend container:

```bash
docker compose exec backend sh
```

The backend development service sets Docker PostgreSQL credentials explicitly, including `DB_PASSWORD=password`, and runs `composer install` before FrankenPHP starts so a fresh backend vendor volume is populated automatically.

Common backend commands:

```bash
php artisan test
php artisan migrate:fresh --seed
php artisan route:list --path=api/v1
php artisan optimize:clear
composer validate
```

From the host:

```bash
docker compose exec backend php artisan test
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend composer validate
docker compose exec backend composer dump-autoload
```

## Frontend container workflow

Enter the frontend container:

```bash
docker compose exec frontend sh
```

Common frontend commands:

```bash
npm run dev
npm run build
```

From the host:

```bash
docker compose exec frontend npm run build
```

## Verification checklist

Before committing changes, run:

```bash
docker compose exec backend composer validate
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend composer dump-autoload
docker compose exec backend php artisan test
docker compose exec frontend npm run build
curl http://127.0.0.1:8000/api/v1/health
```

## Backend code style

PHP code style should be handled with Laravel Pint.

Run Pint:

```bash
docker compose exec backend ./vendor/bin/pint
```

Check formatting without applying changes:

```bash
docker compose exec backend ./vendor/bin/pint --test
```

## Frontend code style

Frontend formatting should be handled with Prettier.

Run Prettier from the frontend container:

```bash
docker compose exec frontend npx prettier --write .
```

Check formatting without applying changes:

```bash
docker compose exec frontend npx prettier --check .
```

## Suggested VS Code setup

Recommended extensions:

* Remote - WSL
* Docker
* PHP Intelephense
* Laravel Pint formatter
* Prettier - Code formatter
* Tailwind CSS IntelliSense
* ESLint

Recommended editor behavior:

* format TypeScript, React, JSON, CSS and Markdown with Prettier
* format PHP with Laravel Pint
* keep `.env` files uncommitted
* run the full verification checklist before committing

If workspace VS Code settings are kept local, they should not be committed.

## Environment files

Backend:

```bash
cp backend/.env.example backend/.env
```

Frontend:

```bash
cp frontend/.env.example frontend/.env
```

Never commit real `.env` files.

## Local PostgreSQL

Inside Docker:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=fantasy_football
DB_USERNAME=fantasy
DB_PASSWORD=password
```

From the host machine:

```text
localhost:5433
```

## Multi-competition domain model

The application stores multiple real football competitions in one database. Seasons belong to real competitions, and fantasy leagues belong to seasons. Real clubs and players are global identities: `season_clubs` records club participation in a season, while `player_season_registrations` records a player's club, eligibility, quotation, and active status for a season.

## Deterministic demo environment

> **Local development only:** the credentials below are intentionally public demo credentials and must never be used in production.

From the `backend` directory, seed the complete demo environment with:

```bash
php artisan db:seed --class=DemoEnvironmentSeeder
```

For a clean database, run:

```bash
php artisan migrate:fresh
php artisan db:seed --class=DemoEnvironmentSeeder
```

The default `DatabaseSeeder` includes this environment only when `APP_ENV` is `local` or `testing`; production seeding retains reference data without creating these demo users.

All demo users use the password `password`:

| Email | Purpose |
| --- | --- |
| `demo.commissioner@example.com` | Commissioner and owner of a populated roster |
| `demo.cocommissioner@example.com` | Co-commissioner with a partial roster |
| `demo.participant1@example.com` | Ordinary participant with a nearly empty roster |
| `demo.participant2@example.com` | Ordinary participant with an empty roster |
| `demo.participant3@example.com` through `demo.participant7@example.com` | Existing ordinary participant scenarios with empty rosters |
| `demo.nonmember@example.com` | Non-member for authorization checks |

The seed creates **Demo League** for Serie A 2025/2026 with four clubs, 24 deterministic registered players across all four roles, nine memberships and fantasy teams, a 500 initial budget, and a 50% release refund. Seven players are actively assigned at deterministic prices, one assignment is historical/released, and the remaining player pool supports search, filtering, eligibility, and pagination checks. The original commissioner and seven participant accounts and teams remain available; the co-commissioner and non-member scenarios are additive. The command is safe to repeat: stable emails, slugs, and composite natural keys are resolved with idempotent Eloquent operations, while roster services create only missing assignments.

Real matches reference season clubs, and player scores reference player season registrations. This keeps competition- and season-specific data separate from global club and player identity.

## Migrations

Use granular Laravel migrations. The domain schema follows this convention.

Preferred rule:

* one migration per table

Do not create milestone-sized migrations such as:

```text
create_milestone3_domain_tables
create_all_domain_tables
create_fantasy_schema
```

Migration order must respect foreign-key dependencies.

## Git workflow

Use `dev` for active development.

Use `main` as stable baseline.

Check current branch:

```bash
git branch
```

Commit manually:

```bash
git status
git add .
git commit -m "Your commit message"
git push
```

## Troubleshooting

If backend says `vendor/autoload.php` is missing, run:

```bash
docker compose exec backend composer install
```

If services are not running:

```bash
docker compose up -d --build
```

If PostgreSQL credentials mismatch after changing compose environment variables:

```bash
docker compose down -v
docker compose up -d --build
```

If frontend build fails after dependency changes:

```bash
docker compose exec frontend npm install
docker compose exec frontend npm run build
```

## Internal admin panel

The Filament internal admin panel is served at `http://127.0.0.1:8000/admin` and uses the existing user accounts.

- `super_admin` (level 100) manages all domain resources, users, and global roles.
- `global_admin` (level 80) manages domain resources but cannot manage users or global roles.
- `user` (level 10) is a normal platform user and cannot access the admin panel.
- League roles are league-scoped records in `league_roles` / `league_user` and are intentionally separate from global platform roles in `roles` / `role_user`.
