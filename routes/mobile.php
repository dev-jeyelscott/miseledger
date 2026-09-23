<?php

use App\Http\Controllers\Mobile\HomeController;
use App\Http\Controllers\Mobile\LocationController;
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
    });
