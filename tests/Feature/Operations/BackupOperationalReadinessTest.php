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

test('the backup verification workflow restores only into an isolated, disposable database and never targets production', function () {
    $workflow = file_get_contents(base_path('.github/workflows/billing-restore-readiness.yml'));

    expect($workflow)
        ->toContain('postgres:17-alpine')
        ->toContain('miseledger_restore_target')
        ->not->toContain('production')
        ->not->toContain('DB_PASSWORD }}');
});
