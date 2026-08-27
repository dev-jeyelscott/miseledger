# MiseLedger Container and Coolify Deployment

## Purpose

MiseLedger uses one project-owned PHP 8.5 application image for production web, queue-worker, scheduler, and one-off Artisan commands.

The same Dockerfile defines local and production PHP/runtime requirements. Environment-specific differences are intentional:

- local development uses the `development` target;
- local source code is bind-mounted;
- local Composer and Node dependencies are writable;
- local Vite HMR runs as a separate Compose service;
- local Xdebug is available but disabled by default;
- production uses the final `production` target;
- production application code and Vite assets are immutable;
- production does not contain Composer, Node, npm, Xdebug, or development dependencies;
- production secrets are injected by Coolify at runtime.

Do not use `php artisan serve` as the permanent production HTTP server.

## Production Process Model

One production image is used for three independent long-running process types:

| Process | Command | Public HTTP |
| --- | --- | --- |
| Web | `apache2-foreground` | Yes, through Coolify only |
| Worker | `php artisan queue:work redis --sleep=1 --tries=3 --timeout=90` | No |
| Scheduler | `php artisan schedule:work` | No |

The web process listens on container port `8080`.

The worker timeout must remain lower than the Redis queue `retry_after` configuration. The current application queue `retry_after` is 120 seconds.

Do not combine web, worker, and scheduler under Supervisor. Coolify owns process restart and lifecycle behavior independently.

## Local Development

### Initial setup

From Ubuntu or WSL:

```bash
docker compose build --pull
docker compose run --rm app composer setup
docker compose up -d
```

`composer setup` creates `.env` when missing, generates `APP_KEY`, runs migrations, installs npm dependencies, and builds the frontend.

Subsequent development startup is:

```bash
docker compose up -d
```

Inspect the stack:

```bash
docker compose ps
docker compose logs -f app
```

Stop the stack without deleting PostgreSQL or Redis data:

```bash
docker compose down
```

Never use `docker compose down -v` unless deliberately deleting local PostgreSQL and Redis data.

### Existing Sail PostgreSQL volume

The PostgreSQL volume name remains `sail-pgsql` deliberately.

Fresh volumes create `miseledger_testing` automatically. An older existing Sail volume may predate the repository-owned initializer.

Verify the test database:

```bash
docker compose exec pgsql sh -lc \
'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '\''miseledger_testing'\''"'
```

If no row is returned, create it once:

```bash
docker compose exec pgsql sh -lc \
'createdb -U "$POSTGRES_USER" miseledger_testing'
```

### Vite HMR

The `vite` Compose service listens on the configured `VITE_PORT`.

Default:

```text
http://localhost:5174
```

For WSL environments that require polling:

```bash
VITE_USE_POLLING=true docker compose up -d vite
```

### Xdebug

Xdebug exists only in the development image and is disabled by default.

Enable it:

```bash
XDEBUG_MODE=debug docker compose up -d --force-recreate app
```

Disable it again:

```bash
XDEBUG_MODE=off docker compose up -d --force-recreate app
```

## Production Data Services

### PostgreSQL

Use PostgreSQL 18.

Production requirements:

- private network only;
- no public PostgreSQL port;
- dedicated MiseLedger database;
- dedicated MiseLedger database credentials;
- persistent volume;
- scheduled off-host backups;
- defined retention;
- periodic restore verification.

PostgreSQL is authoritative business data. Inventory movements, balances, purchasing, organization membership, billing projections, audit data, and other durable application records depend on it.

Never treat Redis, filesystem caches, or Docker images as substitutes for a PostgreSQL backup.

### Redis

Use Redis 7 for the currently tested baseline.

Redis is used for:

- sessions;
- cache;
- queues;
- distributed scheduler locks;
- maintenance-mode state when production uses the cache maintenance driver.

Redis must remain private.

Production Redis should have persistent storage because queued billing notifications are operationally significant. Cache entries may be rebuilt, but queued work and active sessions should not be intentionally discarded.

Use a dedicated Redis instance for MiseLedger where practical. If several products ever share infrastructure, do not share credentials or key namespaces implicitly.

## Application Filesystem

Current repository evidence does not require durable user-uploaded application files for normal MiseLedger operation.

Current production boundaries:

| Path/data | Persistence requirement |
| --- | --- |
| `public/build` | Immutable image |
| `storage/framework` | Ephemeral |
| `bootstrap/cache` | Ephemeral |
| logs | Container stdout/stderr |
| `storage/app` | Persist only when a real durable-file feature requires it |

If a future feature stores durable business files on the local filesystem, either:

1. add an explicitly backed-up `storage/app` persistent volume, or
2. move that feature to supported object storage.

Do not persist the entire application directory.

## Coolify Web Resource

Create a Git-based application using this repository and the root `Dockerfile`.

Production target is the final Dockerfile stage, so no alternate Dockerfile or Sail runtime is required.

Configure:

- internal port: `8080`;
- public domain: MiseLedger production domain;
- HTTPS/TLS: managed by Coolify;
- health check type: HTTP;
- health path: `/up`;
- expected response: HTTP 200;
- restart policy: enabled;
- public database ports: none;
- public Redis ports: none.

Do not publish container port `8080` directly on the VPS firewall.

Laravel trusts the Coolify reverse proxy. The security assumption behind `trustProxies(at: '*')` is therefore that the application container remains reachable only through the private Coolify/Docker network.

## Coolify Worker Resource

Deploy the exact same repository commit and Dockerfile as the web resource.

Do not assign a domain.

Use the production image and override its process with:

```bash
php artisan queue:work redis --sleep=1 --tries=3 --timeout=90
```

If the current Coolify UI requires a Docker entrypoint override, the equivalent custom Docker option is:

```text
--entrypoint "sh -c 'php artisan queue:work redis --sleep=1 --tries=3 --timeout=90'"
```

Do not configure an HTTP health check for this resource. Process liveness and Coolify restart behavior are the appropriate first-level supervision.

Start with one worker. Scale only when measured queue latency requires it.

## Coolify Scheduler Resource

Deploy the exact same repository commit and Dockerfile as the web and worker resources.

Do not assign a domain.

Run:

```bash
php artisan schedule:work
```

Equivalent custom Docker option when required:

```text
--entrypoint "sh -c 'php artisan schedule:work'"
```

Run one scheduler replica.

The application already protects scheduled billing work with `withoutOverlapping()` and `onOneServer()`. Redis must therefore be available to the scheduler.

## Required Shared Production Environment

Web, worker, and scheduler must receive the same application, database, Redis, billing, mail, and encryption configuration.

Minimum runtime baseline:

```dotenv
APP_NAME=MiseLedger
APP_ENV=production
APP_DEBUG=false
APP_URL=https://miseledger.example.com
APP_KEY=<Coolify secret>

APP_MAINTENANCE_DRIVER=cache
APP_MAINTENANCE_STORE=redis

LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_DEPRECATIONS_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=<private PostgreSQL hostname>
DB_PORT=5432
DB_DATABASE=<database>
DB_USERNAME=<secret>
DB_PASSWORD=<secret>

SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true

CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=<private Redis hostname>
REDIS_PORT=6379
REDIS_PASSWORD=<secret when configured>

BILLING_LOG_CHANNEL=stack
```

Also configure all currently required Stripe or PayMongo production variables from `.env.example`.

Never store production credentials in:

- the Dockerfile;
- Docker build arguments;
- Compose source;
- `.env` committed to Git;
- Vite environment variables;
- frontend props.

## Production Migrations

Never run migrations from the Docker image `ENTRYPOINT` or web startup command.

Migrations are an explicit release operation.

Recommended controlled release sequence:

1. confirm a recent PostgreSQL backup exists;
2. deploy only backward-compatible schema changes whenever possible;
3. place MiseLedger in shared Redis-backed maintenance mode when a migration cannot safely run while requests continue;
4. deploy the new web image;
5. run `php artisan migrate --force` once from the new web container;
6. verify `/up`;
7. leave maintenance mode;
8. deploy or restart worker and scheduler on the same commit;
9. verify logs, queue processing, and scheduled-command registration.

Maintenance commands:

```bash
php artisan down --retry=60
php artisan migrate --force
php artisan up
```

Because production maintenance state is Redis-backed, replacement web containers observe the same maintenance state.

Do not configure `composer setup` for production. It performs initialization work that is appropriate for a new development environment, not a controlled production release.

## Deployment Verification

After every deployment verify:

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
```

Then verify externally:

```text
GET https://<miseledger-domain>/up
```

Expected result is HTTP 200.

Verify that:

- CSS and JavaScript load from immutable `public/build`;
- no Vite development server is required;
- authenticated sessions survive normal web-container restarts while Redis remains available;
- the queue worker is running;
- the scheduler is running;
- PostgreSQL and Redis are not publicly reachable;
- application and billing logs appear in Coolify;
- no `.env` exists inside the image.

## Failure Isolation

The three process types must remain independent.

Expected behavior:

- web failure does not terminate the worker;
- worker failure does not take down HTTP;
- scheduler failure does not take down HTTP;
- Redis failure may disrupt sessions, cache, queues, and scheduler locks;
- PostgreSQL failure prevents authoritative application operations;
- Vite failure has no production impact because Vite is not a production service.

## Rollback

Application-image rollback is safe only when the database schema remains compatible with the previous image.

Do not automatically run `migrate:rollback` during application rollback.

For an application failure:

1. restore the previous known-good application image;
2. keep the database at its current schema when backward compatible;
3. restore PostgreSQL from a verified backup only when the schema/data change itself requires recovery.

Inventory ledger records must never be repaired through direct `stock_balances` edits as part of deployment recovery.

## Observability

Initial production observability uses existing application behavior plus Coolify:

- Laravel application logs to stderr;
- Apache access logs to stdout;
- Apache errors to stderr;
- Coolify `/up` health monitoring;
- Coolify process restart monitoring;
- Laravel failed-job persistence;
- existing billing observability and reconciliation;
- PostgreSQL backup/restore verification.

Add external APM or metrics only when an operational requirement justifies the additional dependency.

## AGEAX Container Convention

The reusable AGEAX convention is intentionally small:

1. each product owns its Dockerfile;
2. each Dockerfile is based on that product's actual runtime;
3. one immutable production image runs web and compatible CLI processes;
4. local Compose reproduces material production dependencies;
5. development conveniences live only in the development target;
6. production secrets are runtime configuration;
7. web, worker, and scheduler fail independently;
8. databases and Redis stay private;
9. migrations are controlled release operations;
10. persistent data and backup boundaries are explicit.

This is a convention, not a requirement that all AGEAX products use PHP, PostgreSQL, Redis, Apache, or the same service topology.

## ThermaSnap Adoption Gate

Do not copy the MiseLedger Dockerfile into ThermaSnap.

First inspect ThermaSnap's authoritative repository and verify:

- exact PHP and Laravel versions;
- Composer platform requirements;
- image/GIF processing extensions and system libraries;
- filesystem persistence requirements;
- database topology;
- queue behavior;
- scheduled work;
- photo-processing behavior;
- printer or hardware integration boundaries;
- local development requirements.

Reuse only the container conventions that remain valid after that inspection.

## UpShop Adoption Gate

Do not create an UpShop Docker implementation until its authoritative repository is resolved and inspected.

Do not assume:

- Laravel;
- PHP;
- Node;
- PostgreSQL;
- Redis;
- a queue system;
- the repository layout;
- the HTTP-serving model.

An unresolved UpShop repository is an implementation blocker, not permission to invent an architecture.

