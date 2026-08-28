<?php

test('the deployment guide documents PostgreSQL as the sole authoritative backup source and enumerates the covered business domains', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('## Backup Scope and Recovery Boundary')
        ->toContain('PostgreSQL is the sole authoritative backup source for MiseLedger business data.')
        ->toContain('- organizations;')
        ->toContain('- memberships;')
        ->toContain('- inventory master;')
        ->toContain('- stock movements;')
        ->toContain('- stock balances;')
        ->toContain('- purchasing;')
        ->toContain('- suppliers;')
        ->toContain('- stock counts;')
        ->toContain('- transfers;')
        ->toContain('- waste;')
        ->toContain('- recipes;')
        ->toContain('- billing;')
        ->toContain('- audit records.');
});

test('the deployment guide excludes Redis from authoritative business-data recovery while documenting its runtime role', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('Redis is explicitly excluded from authoritative business-data recovery.')
        ->toContain('nothing durable is recovered from Redis, and a Redis loss is remediated by cache/session rebuild, not by a backup restore.');
});

test('the deployment guide preserves StockMovement as ledger history and forbids rebuilding it from StockBalance', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('StockMovement is authoritative ledger history.')
        ->toContain('Do not rebuild or repair StockMovement from StockBalance');
});

test('the deployment guide states no durable user-generated files currently require backup and defines the pre-release persistence-and-backup review', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('No current MiseLedger feature generates durable user-uploaded or user-generated files')
        ->toContain('so no such files are in current backup scope.')
        ->toContain('Before any future durable-file feature ships, that feature\'s PR must include an explicit persistence-and-backup review')
        ->toContain('A scope document alone is not a backup: do not treat this documentation as evidence that durable-file backup is implemented, only that PostgreSQL backup coverage is.');
});

test('the deployment guide documents the backup verification contract, its recorded timestamp destination, and its alerting path', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('## Backup Verification')
        ->toContain('Backup success is never inferred from a file\'s presence alone.')
        ->toContain('the backup command (`pg_dump`) completes without error;')
        ->toContain('the resulting archive is non-empty;')
        ->toContain('the archive is independently readable (`pg_restore --list`);')
        ->toContain('the archive restores cleanly into a separate, disposable database;')
        ->toContain('the restored billing and ledger records match the source data.')
        ->toContain('That job summary is the approved operational destination for the most recent verified-backup timestamp')
        ->toContain('This is the operator-approved alerting path; no additional external alerting dependency is introduced.')
        ->toContain('It never connects to, restores into, or mutates the production database, and it never logs secrets, connection strings, or archive contents.');
});

test('the backup verification workflow checks command completion, non-empty artifact output, and archive readability before restoring', function () {
    $workflow = file_get_contents(base_path('.github/workflows/billing-restore-readiness.yml'));

    expect($workflow)
        ->toContain('set -euo pipefail')
        ->toContain('pg_dump --format=custom')
        ->toContain('test -s /tmp/miseledger-restore-readiness.dump')
        ->toContain('pg_restore --list /tmp/miseledger-restore-readiness.dump > /dev/null')
        ->toContain('pg_restore --clean --if-exists');
});

test('the backup verification workflow records a success timestamp in the job summary and reports failures through the workflow failure path', function () {
    $workflow = file_get_contents(base_path('.github/workflows/billing-restore-readiness.yml'));

    expect($workflow)
        ->toContain('- name: Record backup verification timestamp')
        ->toContain('if: success()')
        ->toContain('Backup verification succeeded:')
        ->toContain('>> "$GITHUB_STEP_SUMMARY"')
        ->toContain('- name: Report backup verification failure')
        ->toContain('if: failure()')
        ->toContain('Backup verification FAILED:')
        ->toContain('exit 1');
});

test('the deployment guide restore runbook specifies the stop-writes through resume-writes sequence against a clean PostgreSQL target', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('## Restore Runbook')
        ->toContain('**Stop writes.**')
        ->toContain('**Provision a clean PostgreSQL target.**')
        ->toContain('Never restore in place over the existing database')
        ->toContain('**Restore the verified backup.**')
        ->toContain('**Validate the application**')
        ->toContain('**Check ledger integrity.**')
        ->toContain('**Resume writes**');
});

test('the deployment guide restore runbook enumerates every required post-restore check', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('### Post-restore checks')
        ->toContain('- organizations: row count and a known organization record present;')
        ->toContain('- memberships: row count and a known membership record present;')
        ->toContain('- inventory item counts: `inventory_items` row count matches the backup\'s point in time;')
        ->toContain('- latest stock movements: the most recent StockMovement records for a sample of items match the backup;')
        ->toContain('- stock balances: StockBalance figures reconcile against StockMovement for the same sample, read-only, with no repair;')
        ->toContain('- purchasing records: purchase orders/receipts are present and counts match;')
        ->toContain('- billing records: subscriptions and billing projections are present and match Stripe/PayMongo state;')
        ->toContain('- login: an authenticated login succeeds against the restored database;')
        ->toContain('- critical reports: at least one inventory/ledger report renders without error against the restored data.');
});

test('the deployment guide restore runbook forbids reconstructing StockMovement from StockBalance and forbids direct StockBalance repair', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('Do not reconstruct StockMovement from StockBalance and do not repair StockBalance directly')
        ->toContain('Never resolve a failed or partial restore by reconstructing StockMovement from StockBalance or by editing `stock_balances` directly');
});

test('the deployment guide restore runbook defines prerequisites, responsible roles, evidence to capture, and escalation conditions without credentials', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('### Prerequisites')
        ->toContain('this document contains no credentials; obtain restore-time database credentials only from the Coolify secret store.')
        ->toContain('### Responsible roles')
        ->toContain('Incident commander: authorizes the restore and owns the go/no-go decision to resume writes.')
        ->toContain('Database operator: provisions the clean PostgreSQL target and performs the restore.')
        ->toContain('Application operator: places MiseLedger in maintenance mode, repoints and redeploys the application, and runs post-restore validation.')
        ->toContain('Second approver: independently confirms every post-restore check before writes resume.')
        ->toContain('### Evidence to capture')
        ->toContain('### Escalation conditions');

    $runbook = substr(
        $docs,
        strpos($docs, '## Restore Runbook'),
        strpos($docs, '## Coolify Web Resource') - strpos($docs, '## Restore Runbook')
    );

    expect($runbook)
        ->not->toContain('DB_PASSWORD=')
        ->not->toContain('PGPASSWORD=');
});

test('the backup verification workflow restores only into an isolated, disposable database and never targets production', function () {
    $workflow = file_get_contents(base_path('.github/workflows/billing-restore-readiness.yml'));

    expect($workflow)
        ->toContain('postgres:17-alpine')
        ->toContain('miseledger_restore_target')
        ->not->toContain('production')
        ->not->toContain('DB_PASSWORD }}');
});

test('the restore drill workflow is runnable on demand and scheduled for periodic execution', function () {
    $workflow = file_get_contents(base_path('.github/workflows/billing-restore-readiness.yml'));

    expect($workflow)
        ->toContain('workflow_dispatch:')
        ->toContain('schedule:')
        ->toContain('cron:');
});

test('the restore drill workflow records backup timestamp, restore start and completion, backup size, validation result, achieved RTO, and achieved RPO', function () {
    $workflow = file_get_contents(base_path('.github/workflows/billing-restore-readiness.yml'));

    expect($workflow)
        ->toContain('BACKUP_TIMESTAMP=$(date -u +%FT%TZ)')
        ->toContain('BACKUP_SIZE_BYTES=$(stat -c%s')
        ->toContain('RESTORE_START=$(date -u +%FT%TZ)')
        ->toContain('RESTORE_END=$(date -u +%FT%TZ)')
        ->toContain('VALIDATION_RESULT=pass')
        ->toContain('VALIDATION_RESULT=fail')
        ->toContain('ACHIEVED_RTO_SECONDS=$((RESTORE_END_EPOCH - RESTORE_START_EPOCH))')
        ->toContain('ACHIEVED_RPO_SECONDS=$((RESTORE_START_EPOCH - BACKUP_EPOCH))')
        ->toContain('- Backup timestamp: $BACKUP_TIMESTAMP')
        ->toContain('- Backup size (bytes): $BACKUP_SIZE_BYTES')
        ->toContain('- Restore start: $RESTORE_START')
        ->toContain('- Restore completion: $RESTORE_END')
        ->toContain('- Validation result: $VALIDATION_RESULT')
        ->toContain('- Achieved RTO (seconds):')
        ->toContain('- Achieved RPO (seconds):')
        ->toContain('- Drill result: $DRILL_RESULT');
});

test('the restore drill workflow targets a maximum RPO of 24 hours and RTO of 4 hours and fails the run when either target is missed', function () {
    $workflow = file_get_contents(base_path('.github/workflows/billing-restore-readiness.yml'));

    expect($workflow)
        ->toContain('RTO_TARGET_SECONDS=14400')
        ->toContain('RPO_TARGET_SECONDS=86400')
        ->toContain('if [ "$ACHIEVED_RTO_SECONDS" -gt "$RTO_TARGET_SECONDS" ] || [ "$ACHIEVED_RPO_SECONDS" -gt "$RPO_TARGET_SECONDS" ]; then')
        ->toContain('DRILL_RESULT=fail')
        ->toContain('Restore drill missed its RTO/RPO target')
        ->toContain('exit 1');
});

test('the backup configuration contract exposes the repository-controlled retention and off-host destination shape without committed credentials', function () {
    expect(config('backup'))
        ->toHaveKeys([
            'restic_repository',
            'restic_password',
            'retention',
            'schedule_time',
            'alert_webhook_url',
        ])
        ->and(config('backup.retention'))
        ->toHaveKeys(['daily', 'weekly', 'monthly'])
        ->and(config('backup.retention.daily'))->toBe(7)
        ->and(config('backup.retention.weekly'))->toBe(4)
        ->and(config('backup.retention.monthly'))->toBe(12)
        ->and(config('backup.schedule_time'))->toBe('02:00')
        ->and(config('backup.restic_repository'))->toBeNull()
        ->and(config('backup.restic_password'))->toBeNull();

    $configuration = file_get_contents(config_path('backup.php'));

    expect($configuration)
        ->toContain("env('RESTIC_REPOSITORY')")
        ->toContain("env('RESTIC_PASSWORD')")
        ->not->toContain('s3:')
        ->not->toContain('backblaze')
        ->not->toContain('wasabi');
});

test('the daily production backup command is scheduled with repository-owned overlap and single-server protection', function () {
    $console = file_get_contents(base_path('routes/console.php'));

    expect($console)
        ->toContain("Schedule::command('backup:database')")
        ->toContain("->dailyAt((string) config('backup.schedule_time'))")
        ->toContain('->withoutOverlapping(120)')
        ->toContain('->onOneServer()')
        ->toContain('->runInBackground();');
});

test('the backup command fails safely without executing pg_dump or restic when the off-host repository is not configured', function () {
    config()->set('backup.restic_repository', null);
    config()->set('backup.restic_password', null);

    $this->artisan('backup:database')
        ->assertFailed();
});

test('the backup command source enforces an off-host encrypted destination and never hardcodes credentials', function () {
    $command = file_get_contents(app_path('Console/Commands/BackupDatabase.php'));

    expect($command)
        ->toContain("config('backup.restic_repository')")
        ->toContain("config('backup.restic_password')")
        ->toContain('restic')
        ->toContain('pg_dump')
        ->toContain('--keep-daily')
        ->toContain('--keep-weekly')
        ->toContain('--keep-monthly')
        ->toContain('--prune')
        ->not->toContain('s3:')
        ->not->toContain('AKIA')
        ->not->toContain('password123');

    expect($command)->not->toMatch('/[\'"](?:RESTIC_PASSWORD|AWS_SECRET_ACCESS_KEY)[\'"]\s*=>\s*[\'"][^\'"]+[\'"]/');
});

test('the Dockerfile installs a PostgreSQL 18-compatible client and restic for the scheduled backup command', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)
        ->toContain('postgresql-client-18')
        ->toContain('restic');
});

test('the environment example documents backup placeholders without committed credentials', function () {
    $example = file_get_contents(base_path('.env.example'));

    expect($example)
        ->toContain('RESTIC_REPOSITORY=')
        ->toContain('RESTIC_PASSWORD=')
        ->toContain('BACKUP_RETENTION_DAILY=7')
        ->toContain('BACKUP_RETENTION_WEEKLY=4')
        ->toContain('BACKUP_RETENTION_MONTHLY=12')
        ->toContain('BACKUP_SCHEDULE_TIME=02:00')
        ->toContain('BACKUP_ALERT_WEBHOOK_URL=')
        ->not->toContain('RESTIC_PASSWORD=miseledger')
        ->not->toContain('AKIA');
});

test('the deployment guide documents the required operator-managed off-host backup provider configuration and operational ownership', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('## Production Backup Automation')
        ->toContain('php artisan backup:database')
        ->toContain('never into a Compose volume or `storage/app`')
        ->toContain('This mechanism never uses Redis and never treats application-container filesystem storage as the backup destination')
        ->toContain('### Required operator-managed configuration')
        ->toContain('`RESTIC_REPOSITORY`')
        ->toContain('`RESTIC_PASSWORD`')
        ->toContain('encryption at rest is guaranteed by the integration itself')
        ->toContain('`BACKUP_RETENTION_DAILY` / `BACKUP_RETENTION_WEEKLY` / `BACKUP_RETENTION_MONTHLY`')
        ->toContain('restic forget --keep-daily --keep-weekly --keep-monthly')
        ->toContain('`BACKUP_ALERT_WEBHOOK_URL`')
        ->toContain('None of these values are committed')
        ->toContain('Operational ownership:');
});

test('the deployment guide documents the restore drill evidence contract and its initial RPO/RTO targets', function () {
    $docs = file_get_contents(base_path('docs/deployment.md'));

    expect($docs)
        ->toContain('## Restore Drill')
        ->toContain('required non-production restore drill')
        ->toContain('- backup timestamp;')
        ->toContain('- restore start time;')
        ->toContain('- restore completion time;')
        ->toContain('- backup size;')
        ->toContain('- validation result of the restored business and ledger records;')
        ->toContain('- achieved RTO;')
        ->toContain('- achieved RPO.')
        ->toContain('maximum RPO of 24 hours and a maximum RTO of 4 hours')
        ->toContain('A drill that misses either target fails the workflow');
});
