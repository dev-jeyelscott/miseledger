<?php

use Illuminate\Support\Facades\File;

test('stock transfer detail uses confirmation dialogs and history-aware navigation', function () {
    $source = File::get(resource_path('js/pages/stock-transfers/form.tsx'));

    expect($source)
        ->toContain("import { navigateToPreviousPage } from '@/lib/navigation-history';")
        ->toContain('navigateToPreviousPage(StockTransferController.index().url)')
        ->toContain('Ship stock')
        ->toContain('transfer?')
        ->toContain('Cancel stock')
        ->toContain('Confirm transfer receipt?')
        ->toContain('Discard unsaved changes?')
        ->toContain('StockTransferController.ship.form(')
        ->toContain('StockTransferController.cancel.form(')
        ->toContain('StockTransferController.receive.form(')
        ->toContain('options={{ replace: stockTransfer === null }}')
        ->toContain("router.on('before'")
        ->toContain("window.addEventListener('beforeunload'")
        ->toContain('disabled={draftDirty}')
        ->not->toContain('<Link href={StockTransferController.index()}>');
});

test('variance report returns through app history and replaces filter history entries', function () {
    $source = File::get(resource_path('js/pages/stock-transfers/variance.tsx'));

    expect($source)
        ->toContain("import { navigateToPreviousPage } from '@/lib/navigation-history';")
        ->toContain('navigateToPreviousPage(')
        ->toContain('options={{ replace: true }}')
        ->toContain('replace');
});

test('navigation helper prefers actual browser history with a canonical replace fallback', function () {
    $source = File::get(resource_path('js/lib/navigation-history.ts'));

    expect($source)
        ->toContain('window.history.back();')
        ->toContain('router.visit(fallbackUrl, {')
        ->toContain('replace: true');
});
