<?php

use App\Http\Controllers\Inventory\InventoryAdjustmentController;
use App\Http\Controllers\Inventory\InventoryCategoryController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\InventoryItemUnitController;
use App\Http\Controllers\Inventory\OpeningBalanceController;
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
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

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

    Route::scopeBindings()->group(function (): void {
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
                'stock-movements',
                [StockMovementLedgerReportController::class, 'index'],
            )->name('stock-movements.index');

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
