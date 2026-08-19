<?php

use Illuminate\Support\Facades\File;

test(
    'goods receipt detail uses confirmation dialogs and history aware navigation',
    function () {
        $source = File::get(
            resource_path('js/pages/goods-receipts/form.tsx'),
        );

        expect($source)
            ->toContain(
                "import { navigateToPreviousPage } from '@/lib/navigation-history';",
            )
            ->toContain(
                'navigateToPreviousPage(GoodsReceiptController.index().url)',
            )
            ->toContain('Finalize goods')
            ->toContain('receipt?')
            ->toContain('Cancel goods')
            ->toContain('Discard unsaved receipt changes?')
            ->toContain('GoodsReceiptController.finalize.form(')
            ->toContain('GoodsReceiptController.cancel.form(')
            ->toContain('replace: goodsReceipt === null')
            ->toContain("preserveState: 'errors'")
            ->toContain("router.on('before'")
            ->toContain("window.addEventListener('beforeunload'")
            ->toContain('disabled={draftDirty}')
            ->toContain('setDefaultsOnSuccess')
            ->not->toContain('PreviousPageButton');
    },
);

test(
    'receiving keeps complex creation and detail workflows on dedicated routes',
    function () {
        $source = File::get(
            resource_path('js/pages/goods-receipts/index.tsx'),
        );

        expect($source)
            ->toContain('Receive from purchase order')
            ->toContain('PurchaseOrderController.index()')
            ->toContain('GoodsReceiptController.edit(')
            ->toContain(
                '<Form action={GoodsReceiptController.index().url} method="get">',
            )
            ->toContain('preserveScroll')
            ->toContain('preserveState');
    },
);

test(
    'shared previous page navigation uses in app history with a canonical fallback',
    function () {
        $source = File::get(
            resource_path('js/lib/navigation-history.ts'),
        );

        expect($source)
            ->toContain("router.on('start'")
            ->toContain("router.on('navigate'")
            ->toContain("router.on('finish'")
            ->toContain('currentIndex > 0')
            ->toContain('window.history.back();')
            ->toContain('router.visit(fallbackUrl, {')
            ->toContain('replace: true');
    },
);
