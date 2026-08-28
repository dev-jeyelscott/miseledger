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

## Backup Scope and Recovery Boundary

PostgreSQL is the sole authoritative backup source for MiseLedger business data. Scheduled off-host backups and periodic restore verification (see [Production Data Services](#production-data-services)) cover exactly this data.

The authoritative business domains recovered from a PostgreSQL backup are:

- organizations;
- memberships;
- inventory master;
- stock movements;
- stock balances;
- purchasing;
- suppliers;
- stock counts;
- transfers;
- waste;
- recipes;
- billing;
- audit records.

Redis is explicitly excluded from authoritative business-data recovery. Its runtime role (sessions, cache, queues, distributed scheduler locks, maintenance-mode state; see [Redis](#redis)) is operational, not a system of record: nothing durable is recovered from Redis, and a Redis loss is remediated by cache/session rebuild, not by a backup restore.

StockMovement is authoritative ledger history. StockBalance is a derived projection maintained from StockMovement. Do not rebuild or repair StockMovement from StockBalance; a divergence must be corrected by replaying or reconciling movements, never by reverse-deriving history from the balance snapshot.

No current MiseLedger feature generates durable user-uploaded or user-generated files (see [Application Filesystem](#application-filesystem)), so no such files are in current backup scope. Before any future durable-file feature ships, that feature's PR must include an explicit persistence-and-backup review covering: where the files are stored, whether they are included in the PostgreSQL backup boundary or require a separate backup mechanism, and their retention and restore-verification plan. A scope document alone is not a backup: do not treat this documentation as evidence that durable-file backup is implemented, only that PostgreSQL backup coverage is.

## Production Backup Automation

The scheduler resource (see [Coolify Scheduler Resource](#coolify-scheduler-resource)) runs `php artisan backup:database` daily. The command:

1. runs `pg_dump --format=custom` against the production database into a transient `/tmp` path inside the scheduler container only, never into a Compose volume or `storage/app`;
2. uploads that archive with `restic backup`, which encrypts the archive client-side before it leaves the container, to the operator-configured off-host repository;
3. deletes the transient `/tmp` archive immediately after upload, whether the upload succeeded or failed;
4. runs `restic forget --prune` to apply the retention policy.

This mechanism never uses Redis and never treats application-container filesystem storage as the backup destination; the `/tmp` archive is a transient handoff to the off-host, encrypted repository, not a persistence layer.

### Required operator-managed configuration

The repository is deliberately provider-agnostic: it targets any restic-supported, off-host, encrypted repository (S3-compatible object storage, B2, Azure, GCS, SFTP, or a REST server). The operator selects and configures the actual provider before enabling the schedule, entirely through Coolify runtime secrets:

- `RESTIC_REPOSITORY`: the operator-chosen off-host repository target (for example an S3-compatible bucket URL);
- `RESTIC_PASSWORD`: the repository encryption password; restic uses it to encrypt every archive at rest independently of the storage provider's own encryption, so encryption at rest is guaranteed by the integration itself, not merely by provider configuration;
- S3-compatible backends reuse the existing `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` runtime secrets; the operator must provision a least-privileged credential scoped only to the dedicated backup bucket, never the application's own credentials;
- `BACKUP_RETENTION_DAILY` / `BACKUP_RETENTION_WEEKLY` / `BACKUP_RETENTION_MONTHLY`: retention counts passed to `restic forget --keep-daily --keep-weekly --keep-monthly` (defaults `7`/`4`/`12`);
- `BACKUP_SCHEDULE_TIME`: the daily run time (default `02:00`);
- `BACKUP_ALERT_WEBHOOK_URL`: an optional operator-provisioned generic HTTP webhook; the command posts a failure notification to it whenever `pg_dump`, `restic backup`, or `restic forget` fails, in addition to its own non-zero exit code and Coolify's process/log observability.

None of these values are committed; `.env.example` only documents the placeholder keys. `RESTIC_REPOSITORY`, `RESTIC_PASSWORD`, and the S3-compatible credentials are Coolify secrets injected only at container runtime, never build arguments, Compose source, or a committed `.env`.

Operational ownership: the application operator provisions and rotates the backup repository credentials and confirms the schedule is active; the database operator remains responsible for restore execution (see [Restore Runbook](#restore-runbook)) and for periodically confirming retained snapshot counts match the configured policy.

## Backup Verification

Backup success is never inferred from a file's presence alone. Verification is exercised by the scheduled `billing restore readiness` workflow (`.github/workflows/billing-restore-readiness.yml`), which runs monthly and on demand, entirely against an isolated, CI-provisioned PostgreSQL instance.

A verified backup run confirms, in order:

1. the backup command (`pg_dump`) completes without error;
2. the resulting archive is non-empty;
3. the archive is independently readable (`pg_restore --list`);
4. the archive restores cleanly into a separate, disposable database;
5. the restored billing and ledger records match the source data.

Each successful run records the verification timestamp in the workflow run's GitHub Actions job summary. That job summary is the approved operational destination for the most recent verified-backup timestamp; there is no separate application-owned backup-verification record.

Backup verification failures are detectable through the workflow's own failure state: a failed scheduled or on-demand run marks the run red in the Actions tab, writes a failure entry with the failure timestamp to the job summary, and triggers GitHub's default failed-scheduled-workflow notification to repository watchers. This is the operator-approved alerting path; no additional external alerting dependency is introduced.

Backup verification always runs against an isolated CI-provisioned PostgreSQL instance and restores only into a separate, disposable database created for that run. It never connects to, restores into, or mutates the production database, and it never logs secrets, connection strings, or archive contents.

## Restore Drill

The scheduled `billing restore readiness` workflow (`.github/workflows/billing-restore-readiness.yml`) is also the required non-production restore drill: it is runnable on demand before launch and runs monthly on a schedule, entirely against an isolated, CI-provisioned PostgreSQL instance, restoring only into a clean, disposable target created for that run. It never uses production credentials or connects to the production database.

Each drill run records the following evidence in the workflow run's GitHub Actions job summary, the same approved operational evidence destination used for [Backup Verification](#backup-verification):

- backup timestamp;
- restore start time;
- restore completion time;
- backup size;
- result;
- validation result of the restored business and ledger records;
- achieved RTO;
- achieved RPO.

The initial targets are a maximum RPO of 24 hours and a maximum RTO of 4 hours. Achieved RPO is measured as the age of the backup relative to the restore start; achieved RTO is measured as the elapsed time from restore start to validated restore completion. A drill that misses either target fails the workflow: the run turns red in the Actions tab and triggers GitHub's default failed-scheduled-workflow notification, the same operator-approved alerting path documented in [Backup Verification](#backup-verification).

## Restore Runbook

This runbook recovers MiseLedger from a verified PostgreSQL backup after data loss or corruption. Use it only when the incident is PostgreSQL data loss/corruption; use [Rollback](#rollback) instead when the application image itself is at fault and the schema remains compatible.

### Prerequisites

- a verified backup exists for the required point in time (see [Backup Verification](#backup-verification)); note its verification timestamp and archive identifier;
- the incident is confirmed to require a PostgreSQL restore, not an application rollback;
- Coolify access to place MiseLedger in maintenance mode and to provision a new, isolated PostgreSQL target;
- this document contains no credentials; obtain restore-time database credentials only from the Coolify secret store.

### Responsible roles

- Incident commander: authorizes the restore and owns the go/no-go decision to resume writes.
- Database operator: provisions the clean PostgreSQL target and performs the restore.
- Application operator: places MiseLedger in maintenance mode, repoints and redeploys the application, and runs post-restore validation.
- Second approver: independently confirms every post-restore check before writes resume.

### Procedure

1. **Stop writes.** Put MiseLedger in maintenance mode (`php artisan down --retry=60`) and stop or pause the worker and scheduler resources so no queued job or scheduled command writes during the restore.
2. **Provision a clean PostgreSQL target.** Create a new, isolated PostgreSQL database dedicated to the restore. Never restore in place over the existing database; a clean target ensures a partial or failed restore never leaves the prior database in a torn state.
3. **Restore the verified backup.** Restore the identified, verified backup archive into the clean target only, using `pg_restore`.
4. **Validate the application** against the restored database:
   - update the private PostgreSQL connection configuration in Coolify secrets to point at the restored target;
   - deploy or restart the web, worker, and scheduler resources on the same application image commit;
   - verify `GET /up` returns HTTP 200;
   - verify `php artisan migrate:status` shows no pending migrations.
5. **Run the post-restore checks** below and capture their results as evidence.
6. **Check ledger integrity.** Confirm StockMovement history is present and that StockBalance figures reconcile against StockMovement for a sample of items. Do not reconstruct StockMovement from StockBalance and do not repair StockBalance directly; this preserves the same invariant defined in [Backup Scope and Recovery Boundary](#backup-scope-and-recovery-boundary).
7. **Resume writes** only after the second approver confirms every post-restore check passes: take the application out of maintenance mode (`php artisan up`) and resume worker and scheduler processing.

### Post-restore checks

Capture the result of each check as evidence:

- organizations: row count and a known organization record present;
- memberships: row count and a known membership record present;
- inventory item counts: `inventory_items` row count matches the backup's point in time;
- latest stock movements: the most recent StockMovement records for a sample of items match the backup;
- stock balances: StockBalance figures reconcile against StockMovement for the same sample, read-only, with no repair;
- purchasing records: purchase orders/receipts are present and counts match;
- billing records: subscriptions and billing projections are present and match Stripe/PayMongo state;
- login: an authenticated login succeeds against the restored database;
- critical reports: at least one inventory/ledger report renders without error against the restored data.

### Evidence to capture

- the verified backup artifact identifier and its verification timestamp;
- restore start and end timestamps and the operators involved;
- the result of each post-restore check listed above;
- `/up` and `migrate:status` output captured after repointing;
- the second approver's explicit sign-off before writes resume.

### Escalation conditions

Escalate to the incident commander and halt before resuming writes when:

- any post-restore check fails;
- StockBalance does not reconcile against StockMovement for the sampled items;
- the restore itself fails or the archive cannot be read;
- no verified backup exists for the required point in time.

Never resolve a failed or partial restore by reconstructing StockMovement from StockBalance or by editing `stock_balances` directly; escalate for a new restore attempt or a manual reconciliation review instead.

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

