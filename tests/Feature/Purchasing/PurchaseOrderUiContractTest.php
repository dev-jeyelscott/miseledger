<?php

use Illuminate\Support\Facades\File;

test('purchase order detail uses confirmation dialogs and history-aware navigation', function () {
    $source = File::get(resource_path('js/pages/purchase-orders/form.tsx'));

    expect($source)
        ->toContain("import { navigateToPreviousPage } from '@/lib/navigation-history';")
        ->toContain('navigateToPreviousPage(PurchaseOrderController.index().url)')
        ->toContain('Approve purchase')
        ->toContain('order?')
        ->toContain('Cancel purchase')
        ->toContain('Discard unsaved changes?')
        ->toContain('PurchaseOrderController.approve.form(')
        ->toContain('PurchaseOrderController.cancel.form(')
        ->toContain('replace: purchaseOrder === null')
        ->toContain("router.on('before'")
        ->toContain("window.addEventListener('beforeunload'")
        ->toContain('disabled={draftDirty}')
        ->not->toContain('PreviousPageButton');
});

test('purchase order index keeps complex create and detail flows on dedicated routes', function () {
    $source = File::get(resource_path('js/pages/purchase-orders/index.tsx'));

    expect($source)
        ->toContain('PurchaseOrderController.create()')
        ->toContain('PurchaseOrderController.edit(')
        ->toContain('<Form action={PurchaseOrderController.index().url} method="get">')
        ->toContain('preserveScroll')
        ->toContain('preserveState');
});
