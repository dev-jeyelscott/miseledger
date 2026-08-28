<?php

use App\Http\Controllers\Billing\OrganizationBillingCancellationController;
use App\Http\Controllers\Billing\OrganizationBillingController;
use App\Http\Controllers\Billing\OrganizationBillingPortalController;
use App\Http\Controllers\Billing\OrganizationCheckoutController;
use App\Http\Controllers\Billing\OrganizationCheckoutStatusController;
use App\Http\Controllers\Billing\OrganizationInvoicePaymentController;
use App\Http\Controllers\Billing\OrganizationInvoiceStatusController;
use App\Http\Controllers\Billing\OrganizationManualRenewalController;
use App\Http\Controllers\Billing\OrganizationSubscriptionUpgradeController;
use App\Http\Controllers\Billing\PayMongoWebhookController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\InventoryAdjustmentController;
use App\Http\Controllers\Inventory\InventoryBrandController;
use App\Http\Controllers\Inventory\InventoryCategoryController;
use App\Http\Controllers\Inventory\InventoryItemBarcodeController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\InventoryItemUnitController;
use App\Http\Controllers\Inventory\InventoryProductController;
use App\Http\Controllers\Inventory\InventoryProductOptionController;
use App\Http\Controllers\Inventory\InventoryProductOptionValueController;
use App\Http\Controllers\Inventory\InventoryValuationReportController;
use App\Http\Controllers\Inventory\LowStockReportController;
use App\Http\Controllers\Inventory\OpeningBalanceController;
use App\Http\Controllers\Inventory\PurchasingHistoryReportController;
use App\Http\Controllers\Inventory\StockCountController;
use App\Http\Controllers\Inventory\StockMovementLedgerReportController;
use App\Http\Controllers\Inventory\StockOnHandReportController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\Inventory\UnitOfMeasureController;
use App\Http\Controllers\Inventory\WasteController;
use App\Http\Controllers\Inventory\WasteReasonController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationLocationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationStorageLocationController;
use App\Http\Controllers\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Recipes\RecipeController;
use App\Http\Controllers\Recipes\RecipeCostController;
use App\Http\Controllers\Suppliers\SupplierController;
use App\Http\Controllers\Suppliers\SupplierItemController;
use App\Http\Controllers\WelcomeController;
use App\Support\Billing\FeatureCode;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\PaymentController;

Route::get('/', [WelcomeController::class, 'index'])->name('home');

Route::get('stripe/payment/{id}', [PaymentController::class, 'show'])
    ->name('cashier.payment');

Route::post('billing/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

Route::post('billing/webhooks/paymongo', PayMongoWebhookController::class)
    ->middleware('paymongo.webhook')
    ->name('billing.webhooks.paymongo');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get(
        'dashboard',
        [DashboardController::class, 'index'],
    )->name('dashboard');

    Route::get(
        'organizations/create',
        [OrganizationController::class, 'create'],
    )->name('organizations.create');

    Route::post(
        'organizations',
        [OrganizationController::class, 'store'],
    )->name('organizations.store');

    Route::put(
        'organizations/{organization}/activate',
        [OrganizationController::class, 'activate'],
    )->name('organizations.activate');

    Route::post(
        'organizations/{organization}/billing/checkout',
        [OrganizationCheckoutController::class, 'store'],
    )->name('organizations.billing.checkout');

    Route::post(
        'organizations/{organization}/billing/portal',
        [OrganizationBillingPortalController::class, 'store'],
    )->name('organizations.billing.portal');

    Route::post(
        'organizations/{organization}/billing/cancel',
        [OrganizationBillingCancellationController::class, 'store'],
    )->name('organizations.billing.cancel');

    Route::post(
        'organizations/{organization}/billing/renew',
        [OrganizationManualRenewalController::class, 'store'],
    )->name('organizations.billing.renew');

    Route::post(
        'organizations/{organization}/billing/upgrade',
        [OrganizationSubscriptionUpgradeController::class, 'store'],
    )->name('organizations.billing.upgrade');

    Route::post(
        'organizations/{organization}/billing/invoices/{invoice}/payments',
        [OrganizationInvoicePaymentController::class, 'store'],
    )->name('organizations.billing.invoices.payments.store');

    Route::get(
        'organizations/{organization}/billing/invoices/{invoice}/status',
        [OrganizationInvoiceStatusController::class, 'show'],
    )->name('organizations.billing.invoices.status');

    Route::get(
        'organizations/{organization}/billing/checkout/success',
        [OrganizationCheckoutStatusController::class, 'success'],
    )->name('organizations.billing.checkout.success');

    Route::get(
        'organizations/{organization}/billing/checkout/cancel',
        [OrganizationCheckoutStatusController::class, 'cancel'],
    )->name('organizations.billing.checkout.cancel');

    Route::get(
        'organizations/{organization}/settings',
        [OrganizationController::class, 'edit'],
    )->name('organizations.settings.edit');

    Route::put(
        'organizations/{organization}/settings',
        [OrganizationController::class, 'update'],
    )->name('organizations.settings.update');

    Route::get(
        'organizations/{organization}/members',
        [OrganizationMemberController::class, 'index'],
    )->name('organizations.members.index');

    Route::post(
        'organizations/{organization}/members',
        [OrganizationMemberController::class, 'store'],
    )->name('organizations.members.store');

    Route::get(
        'organizations/{organization}/billing',
        [OrganizationBillingController::class, 'show'],
    )->name('organizations.billing.show');

    Route::scopeBindings()->middleware('feature:'.FeatureCode::MultiLocation)->group(function (): void {
        Route::get(
            'organizations/{organization}/locations',
            [OrganizationLocationController::class, 'index'],
        )->name('organizations.locations.index');

        Route::post(
            'organizations/{organization}/locations',
            [OrganizationLocationController::class, 'store'],
        )->name('organizations.locations.store');

        Route::get(
            'organizations/{organization}/locations/{location}/edit',
            [OrganizationLocationController::class, 'edit'],
        )->name('organizations.locations.edit');

        Route::put(
            'organizations/{organization}/locations/{location}',
            [OrganizationLocationController::class, 'update'],
        )->name('organizations.locations.update');

        Route::get(
            'organizations/{organization}/locations/{location}/storage-locations',
            [OrganizationStorageLocationController::class, 'index'],
        )->name('organizations.locations.storage-locations.index');

        Route::post(
            'organizations/{organization}/locations/{location}/storage-locations',
            [OrganizationStorageLocationController::class, 'store'],
        )->name('organizations.locations.storage-locations.store');

        Route::get(
            'organizations/{organization}/locations/{location}/storage-locations/{storageLocation}/edit',
            [OrganizationStorageLocationController::class, 'edit'],
        )->name('organizations.locations.storage-locations.edit');

        Route::put(
            'organizations/{organization}/locations/{location}/storage-locations/{storageLocation}',
            [OrganizationStorageLocationController::class, 'update'],
        )->name('organizations.locations.storage-locations.update');
    });

    Route::prefix('inventory')
        ->name('inventory.')
        ->group(function (): void {
            Route::get(
                'opening-balances/create',
                [OpeningBalanceController::class, 'create'],
            )->name('opening-balances.create');

            Route::post(
                'opening-balances',
                [OpeningBalanceController::class, 'store'],
            )->name('opening-balances.store');

            Route::get(
                'adjustments/create',
                [InventoryAdjustmentController::class, 'create'],
            )->name('adjustments.create');

            Route::post(
                'adjustments',
                [InventoryAdjustmentController::class, 'store'],
            )->name('adjustments.store');

            Route::get(
                'stock-on-hand',
                [StockOnHandReportController::class, 'index'],
            )->name('stock-on-hand.index');

            Route::get(
                'stock-on-hand/export',
                [StockOnHandReportController::class, 'export'],
            )->name('stock-on-hand.export')->middleware('feature:'.FeatureCode::ReportsExport);

            Route::get(
                'low-stock',
                [LowStockReportController::class, 'index'],
            )->name('low-stock.index');

            Route::get(
                'stock-movements',
                [StockMovementLedgerReportController::class, 'index'],
            )->name('stock-movements.index');

            Route::get(
                'stock-movements/export',
                [StockMovementLedgerReportController::class, 'export'],
            )->name('stock-movements.export')->middleware('feature:'.FeatureCode::ReportsExport);

            Route::get(
                'valuation',
                [InventoryValuationReportController::class, 'index'],
            )->name('valuation.index');

            Route::get(
                'valuation/export',
                [InventoryValuationReportController::class, 'export'],
            )->name('valuation.export')->middleware('feature:'.FeatureCode::ReportsExport);

            Route::get(
                'purchasing-history',
                [PurchasingHistoryReportController::class, 'index'],
            )->name('purchasing-history.index');

            Route::get(
                'purchasing-history/export',
                [PurchasingHistoryReportController::class, 'export'],
            )->name('purchasing-history.export')->middleware('feature:'.FeatureCode::ReportsExport);

            Route::get(
                'items',
                [InventoryItemController::class, 'index'],
            )->name('items.index');

            Route::get(
                'items/create',
                [InventoryItemController::class, 'create'],
            )->name('items.create');

            Route::post(
                'items',
                [InventoryItemController::class, 'store'],
            )->name('items.store');

            Route::get(
                'items/{inventoryItem}',
                [InventoryItemController::class, 'show'],
            )->name('items.show');

            Route::get(
                'items/{inventoryItem}/edit',
                [InventoryItemController::class, 'edit'],
            )->name('items.edit');

            Route::put(
                'items/{inventoryItem}',
                [InventoryItemController::class, 'update'],
            )->name('items.update');

            Route::get(
                'categories',
                [InventoryCategoryController::class, 'index'],
            )->name('categories.index');

            Route::post(
                'categories',
                [InventoryCategoryController::class, 'store'],
            )->name('categories.store');

            Route::get(
                'categories/{inventoryCategory}/edit',
                [InventoryCategoryController::class, 'edit'],
            )->name('categories.edit');

            Route::put(
                'categories/{inventoryCategory}',
                [InventoryCategoryController::class, 'update'],
            )->name('categories.update');

            Route::get(
                'brands',
                [InventoryBrandController::class, 'index'],
            )->name('brands.index');

            Route::post(
                'brands',
                [InventoryBrandController::class, 'store'],
            )->name('brands.store');

            Route::get(
                'brands/{inventoryBrand}/edit',
                [InventoryBrandController::class, 'edit'],
            )->name('brands.edit');

            Route::put(
                'brands/{inventoryBrand}',
                [InventoryBrandController::class, 'update'],
            )->name('brands.update');

            Route::get(
                'product-families',
                [InventoryProductController::class, 'index'],
            )->name('product-families.index');

            Route::get(
                'product-families/{inventoryProduct}',
                [InventoryProductController::class, 'show'],
            )->name('product-families.show');

            Route::post(
                'product-families',
                [InventoryProductController::class, 'store'],
            )->name('product-families.store');

            Route::put(
                'product-families/{inventoryProduct}',
                [InventoryProductController::class, 'update'],
            )->name('product-families.update');

            Route::post(
                'product-families/{inventoryProduct}/options',
                [InventoryProductOptionController::class, 'store'],
            )->name('product-families.options.store');

            Route::put(
                'product-families/{inventoryProduct}/options/{inventoryProductOption}',
                [InventoryProductOptionController::class, 'update'],
            )->name('product-families.options.update');

            Route::post(
                'product-families/{inventoryProduct}/options/{inventoryProductOption}/values',
                [InventoryProductOptionValueController::class, 'store'],
            )->name('product-families.options.values.store');

            Route::put(
                'product-families/{inventoryProduct}/options/{inventoryProductOption}/values/{inventoryProductOptionValue}',
                [InventoryProductOptionValueController::class, 'update'],
            )->name('product-families.options.values.update');

            Route::post(
                'items/{inventoryItem}/units',
                [InventoryItemUnitController::class, 'store'],
            )->name('items.units.store');

            Route::get(
                'items/{inventoryItem}/units/{inventoryItemUnit}/edit',
                [InventoryItemUnitController::class, 'edit'],
            )->name('items.units.edit');

            Route::put(
                'items/{inventoryItem}/units/{inventoryItemUnit}',
                [InventoryItemUnitController::class, 'update'],
            )->name('items.units.update');

            Route::post(
                'items/{inventoryItem}/barcodes',
                [InventoryItemBarcodeController::class, 'store'],
            )->name('items.barcodes.store');

            Route::put(
                'items/{inventoryItem}/barcodes/{barcode}',
                [InventoryItemBarcodeController::class, 'update'],
            )->name('items.barcodes.update');

            Route::get(
                'units',
                [UnitOfMeasureController::class, 'index'],
            )->name('units.index');

            Route::post(
                'units',
                [UnitOfMeasureController::class, 'store'],
            )->name('units.store');

            Route::get(
                'units/{unitOfMeasure}/edit',
                [UnitOfMeasureController::class, 'edit'],
            )->name('units.edit');

            Route::put(
                'units/{unitOfMeasure}',
                [UnitOfMeasureController::class, 'update'],
            )->name('units.update');
        });

    Route::prefix('recipes')
        ->name('recipes.')
        ->middleware('feature:'.FeatureCode::Recipes)
        ->group(function (): void {
            Route::get(
                '/',
                [RecipeController::class, 'index'],
            )->name('index');

            Route::post(
                '/',
                [RecipeController::class, 'store'],
            )->name('store');

            Route::get(
                '{recipe}/edit',
                [RecipeController::class, 'edit'],
            )->name('edit');

            Route::put(
                '{recipe}',
                [RecipeController::class, 'update'],
            )->name('update');

            Route::get(
                '{recipe}/cost',
                [RecipeCostController::class, 'show'],
            )->name('cost');
        });

    Route::middleware('feature:'.FeatureCode::Purchasing)->group(function (): void {
        Route::prefix('suppliers')
            ->name('suppliers.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [SupplierController::class, 'index'],
                )->name('index');

                Route::get(
                    'create',
                    [SupplierController::class, 'create'],
                )->name('create');

                Route::post(
                    '/',
                    [SupplierController::class, 'store'],
                )->name('store');

                Route::get(
                    '{supplier}/edit',
                    [SupplierController::class, 'edit'],
                )->name('edit');

                Route::put(
                    '{supplier}',
                    [SupplierController::class, 'update'],
                )->name('update');

                Route::post(
                    '{supplier}/items',
                    [SupplierItemController::class, 'store'],
                )->name('items.store');

                Route::get(
                    '{supplier}/items/{supplierItem}/edit',
                    [SupplierItemController::class, 'edit'],
                )->name('items.edit');

                Route::put(
                    '{supplier}/items/{supplierItem}',
                    [SupplierItemController::class, 'update'],
                )->name('items.update');

                Route::post(
                    '{supplier}/items/{supplierItem}/prices',
                    [SupplierItemController::class, 'storePrice'],
                )->name('items.prices.store');
            });

        Route::prefix('purchase-orders')
            ->name('purchase-orders.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [PurchaseOrderController::class, 'index'],
                )->name('index');

                Route::get(
                    'create',
                    [PurchaseOrderController::class, 'create'],
                )->name('create');

                Route::post(
                    '/',
                    [PurchaseOrderController::class, 'store'],
                )->name('store');

                Route::get(
                    '{purchaseOrder}/edit',
                    [PurchaseOrderController::class, 'edit'],
                )->name('edit');

                Route::put(
                    '{purchaseOrder}',
                    [PurchaseOrderController::class, 'update'],
                )->name('update');

                Route::post(
                    '{purchaseOrder}/approve',
                    [PurchaseOrderController::class, 'approve'],
                )->name('approve');

                Route::post(
                    '{purchaseOrder}/cancel',
                    [PurchaseOrderController::class, 'cancel'],
                )->name('cancel');

                Route::get(
                    '{purchaseOrder}/receipts/create',
                    [GoodsReceiptController::class, 'create'],
                )->name('receipts.create');

                Route::post(
                    '{purchaseOrder}/receipts',
                    [GoodsReceiptController::class, 'store'],
                )->name('receipts.store');
            });

        Route::prefix('goods-receipts')
            ->name('goods-receipts.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [GoodsReceiptController::class, 'index'],
                )->name('index');

                Route::get(
                    '{goodsReceipt}/edit',
                    [GoodsReceiptController::class, 'edit'],
                )->name('edit');

                Route::put(
                    '{goodsReceipt}',
                    [GoodsReceiptController::class, 'update'],
                )->name('update');

                Route::post(
                    '{goodsReceipt}/finalize',
                    [GoodsReceiptController::class, 'finalize'],
                )->name('finalize');

                Route::post(
                    '{goodsReceipt}/cancel',
                    [GoodsReceiptController::class, 'cancel'],
                )->name('cancel');
            });
    });

    Route::prefix('stock-counts')
        ->name('stock-counts.')
        ->group(function (): void {
            Route::get(
                '/',
                [StockCountController::class, 'index'],
            )->name('index');

            Route::get(
                'variance',
                [StockCountController::class, 'variance'],
            )->name('variance');

            Route::get(
                'variance/export',
                [StockCountController::class, 'exportVariance'],
            )->name('variance.export')->middleware('feature:'.FeatureCode::ReportsExport);

            Route::get(
                'create',
                [StockCountController::class, 'create'],
            )->name('create');

            Route::post(
                '/',
                [StockCountController::class, 'store'],
            )->name('store');

            Route::get(
                '{stockCount}/edit',
                [StockCountController::class, 'edit'],
            )->name('edit');

            Route::put(
                '{stockCount}',
                [StockCountController::class, 'update'],
            )->name('update');

            Route::post(
                '{stockCount}/submit',
                [StockCountController::class, 'submit'],
            )->name('submit');

            Route::post(
                '{stockCount}/finalize',
                [StockCountController::class, 'finalize'],
            )->name('finalize');

            Route::post(
                '{stockCount}/cancel',
                [StockCountController::class, 'cancel'],
            )->name('cancel');
        });

    Route::prefix('waste')
        ->name('waste.')
        ->group(function (): void {
            Route::get(
                '/',
                [WasteController::class, 'index'],
            )->name('index');

            Route::post(
                '/',
                [WasteController::class, 'store'],
            )->name('store');

            Route::get(
                'export',
                [WasteController::class, 'export'],
            )->name('export')->middleware('feature:'.FeatureCode::ReportsExport);
        });

    Route::prefix('waste-reasons')
        ->name('waste-reasons.')
        ->group(function (): void {
            Route::post(
                '/',
                [WasteReasonController::class, 'store'],
            )->name('store');

            Route::put(
                '{wasteReason}',
                [WasteReasonController::class, 'update'],
            )->name('update');
        });

    Route::prefix('stock-transfers')
        ->name('stock-transfers.')
        ->group(function (): void {
            Route::get(
                '/',
                [StockTransferController::class, 'index'],
            )->name('index');

            Route::get(
                'variance',
                [StockTransferController::class, 'variance'],
            )->name('variance');

            Route::get(
                'create',
                [StockTransferController::class, 'create'],
            )->name('create');

            Route::post(
                '/',
                [StockTransferController::class, 'store'],
            )->name('store');

            Route::get(
                '{stockTransfer}/edit',
                [StockTransferController::class, 'edit'],
            )->name('edit');

            Route::put(
                '{stockTransfer}',
                [StockTransferController::class, 'update'],
            )->name('update');

            Route::post(
                '{stockTransfer}/ship',
                [StockTransferController::class, 'ship'],
            )->name('ship');

            Route::post(
                '{stockTransfer}/receive',
                [StockTransferController::class, 'receive'],
            )->name('receive');

            Route::post(
                '{stockTransfer}/cancel',
                [StockTransferController::class, 'cancel'],
            )->name('cancel');
        });
});

require __DIR__.'/settings.php';
