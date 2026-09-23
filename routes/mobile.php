<?php

use App\Http\Controllers\Mobile\HomeController;
use App\Http\Controllers\Mobile\ItemSearchController;
use App\Http\Controllers\Mobile\LocationController;
use App\Http\Controllers\Mobile\ReceivingController;
use App\Http\Controllers\Mobile\ScanController;
use App\Http\Controllers\Mobile\ScanLookupController;
use App\Http\Controllers\Mobile\StockCountController;
use App\Http\Controllers\Mobile\WasteController;
use App\Http\Middleware\ResolveMobileLocation;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', ResolveMobileLocation::class])
    ->prefix('mobile')
    ->name('mobile.')
    ->group(function (): void {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::prefix('location')->name('location.')->group(function (): void {
            Route::get('/', [LocationController::class, 'index'])->name('index');
            Route::post('/', [LocationController::class, 'store'])->name('store');
        });

        Route::prefix('scan')->name('scan.')->group(function (): void {
            Route::get('/', [ScanController::class, 'index'])->name('index');
            Route::post('/lookup', [ScanLookupController::class, 'lookup'])->name('lookup');
            Route::get('/search', [ItemSearchController::class, 'search'])->name('search');
        });

        Route::prefix('receiving')->name('receiving.')->group(function (): void {
            Route::get('/', [ReceivingController::class, 'index'])->name('index');
            Route::get('/ad-hoc', [ReceivingController::class, 'createAdHoc'])->name('create');
            Route::post('/ad-hoc', [ReceivingController::class, 'storeAdHoc'])->name('store');
            Route::get('/scan', [ReceivingController::class, 'scan'])->name('scan');
            Route::post('/lines', [ReceivingController::class, 'addLine'])->name('lines.store');
            Route::get('/items/{inventoryItem}/units', [ReceivingController::class, 'itemUnits'])->name('items.units');
            Route::get('/{goodsReceipt}/review', [ReceivingController::class, 'review'])->name('review');
            Route::delete('/{goodsReceipt}/lines/{line}', [ReceivingController::class, 'removeLine'])->name('lines.destroy');
            Route::post('/{goodsReceipt}/finalize', [ReceivingController::class, 'finalize'])->name('finalize');
        });

        Route::prefix('stock-counts')->name('stock-counts.')->group(function (): void {
            Route::get('/', [StockCountController::class, 'index'])->name('index');
            Route::get('/create', [StockCountController::class, 'create'])->name('create');
            Route::post('/', [StockCountController::class, 'store'])->name('store');
            Route::get('/scan', [StockCountController::class, 'scan'])->name('scan');
            Route::post('/lines', [StockCountController::class, 'addLine'])->name('lines.store');
            Route::get('/{stockCount}/review', [StockCountController::class, 'review'])->name('review');
            Route::post('/{stockCount}/submit', [StockCountController::class, 'submit'])->name('submit');
        });

        Route::prefix('waste')->name('waste.')->group(function (): void {
            Route::get('/', [WasteController::class, 'record'])->name('record');
            Route::post('/', [WasteController::class, 'store'])->name('store');
        });
    });
