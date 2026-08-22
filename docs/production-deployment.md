# Production deployment

## Build

Build the backend image from `backend/Dockerfile.prod`. For a non-container release run `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`. Build the frontend with `npm ci && npm run build`. The tracked production default deliberately uses same-origin `/api/v1`; set `VITE_API_BASE_URL` explicitly at build time when the API has a different public origin.

## Release

Run exactly one release task before updating web replicas:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

With the supplied production Compose overlay, run `docker compose -f docker-compose.prod.yml --profile release run --rm release`. Do not run migrations independently in every web replica. After deployment run `php artisan queue:restart` against the shared queue before replacing workers.

## Run

Run the web server and a separately supervised worker for queue `default`. `docker-compose.prod.yml` provides separate `backend` and `worker` services using the same image and environment. Both mount the same persistent `storage/app/private` volume because queued CSV jobs must read the source saved by the web process.

Set `APP_ENV=production`, `APP_DEBUG=false`, a persistent `APP_KEY`, PostgreSQL `DB_*`, `APP_URL`, `FRONTEND_URL`, queue/cache/session/log/mail settings, and strong explicit bootstrap credentials. The default assumes an edge proxy sends same-origin `/api/v1` to Laravel. For a cross-origin build, the edge/API configuration must allow the configured frontend origin to send `Authorization`, `Accept`, and `Content-Type`.

Import jobs parse a complete file in memory and accept uploads up to 10 MiB. Benchmark representative maximum files before launch. Keep `IMPORT_QUEUE_TIMEOUT < IMPORT_STALE_AFTER < DB_QUEUE_RETRY_AFTER` so the worker terminates an attempt before it is stale and redelivery happens only after safe takeover is possible.

Import sources are retained on private storage after analysis. No scheduled cleanup currently runs; never remove sources for `queued` or `importing` records. Establish an operator retention policy for terminal records and back up the database and retained sources together.

## Verify

1. Request `GET /api/v1/health` and Laravel's `/up` endpoint.
2. Log in through the production frontend.
3. Analyse and confirm one small CSV, then verify the worker changes it from `queued` to `completed`.
4. Request one authenticated League endpoint.
5. Check worker logs, `failed_jobs`, private-storage persistence, and database backup status.