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
