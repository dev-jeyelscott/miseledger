<?php

use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\InventoryItemUnitController;
use App\Http\Controllers\Inventory\UnitOfMeasureController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationLocationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationStorageLocationController;
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
});

require __DIR__.'/settings.php';
