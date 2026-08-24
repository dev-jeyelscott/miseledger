<?php

test('the isolated restore-readiness workflow verifies billing and ledger records without production credentials', function () {
    $workflow = file_get_contents(base_path('.github/workflows/billing-restore-readiness.yml'));

    expect($workflow)
        ->toContain('workflow_dispatch:')
        ->toContain('postgres:17-alpine')
        ->toContain('redis:7-alpine')
        ->toContain('pg_dump --format=custom')
        ->toContain('pg_restore --clean --if-exists')
        ->toContain('miseledger_restore_target')
        ->toContain("grep -Fx '1|1|1'")
        ->not->toContain('composer setup');
});
