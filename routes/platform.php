<?php

use App\Http\Controllers\Platform\PlatformDashboardController;
use Illuminate\Support\Facades\Route;

// Platform authority is independent from every organization-scoped capability.
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'platform.admin'])
    ->group(function (): void {
        Route::get('/', [PlatformDashboardController::class, 'index'])
            ->name('dashboard');
    });
