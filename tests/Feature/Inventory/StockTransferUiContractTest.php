<?php

use Illuminate\Support\Facades\File;

test('stock transfer detail uses confirmation dialogs and history-aware navigation', function () {
    $source = File::get(resource_path('js/pages/stock-transfers/form.tsx'));
    $normalizedSource = preg_replace('/\s+/', ' ', $source);

    expect($normalizedSource)
        ->not->toBeNull()
        ->and($normalizedSource)
        ->toContain("import { navigateToPreviousPage } from '@/lib/navigation-history';")
        ->toContain('navigateToPreviousPage(StockTransferController.index().url)')
        ->toContain('Ship stock transfer?')
        ->toContain('Cancel stock transfer?')
        ->toContain('Confirm transfer receipt?')
        ->toContain('Discard unsaved changes?')
        ->toContain('StockTransferController.ship.form(')
        ->toContain('StockTransferController.cancel.form(')
        ->toContain('StockTransferController.receive.form(')
        ->toContain('replace: stockTransfer === null')
        ->toContain("router.on('before'")
        ->toContain("window.addEventListener('beforeunload'")
        ->toContain('disabled={draftDirty}')
        ->not->toContain('<Link href={StockTransferController.index()}>');
});

test('variance report returns through app history and replaces filter history entries', function () {
    $source = File::get(resource_path('js/pages/stock-transfers/variance.tsx'));
    $normalizedSource = preg_replace('/\s+/', ' ', $source);

    expect($normalizedSource)
        ->not->toBeNull()
        ->and($normalizedSource)
        ->toContain("import { navigateToPreviousPage } from '@/lib/navigation-history';")
        ->toContain('navigateToPreviousPage(')
        ->toContain('options={{ replace: true }}')
        ->toContain('replace');
});

test('navigation helper prefers actual browser history with a canonical replace fallback', function () {
    $source = File::get(resource_path('js/lib/navigation-history.ts'));
    $normalizedSource = preg_replace('/\s+/', ' ', $source);

    expect($normalizedSource)
        ->not->toBeNull()
        ->and($normalizedSource)
        ->toContain('window.history.back();')
        ->toContain('router.visit(fallbackUrl, {')
        ->toContain('replace: true');
});
