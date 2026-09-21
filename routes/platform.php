<?php

use App\Http\Controllers\Platform\PlatformBillingController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformOrganizationController;
use App\Http\Controllers\Platform\PlatformProductCatalogController;
use App\Http\Controllers\Platform\PlatformUserController;
use Illuminate\Support\Facades\Route;

// Platform authority is independent from every organization-scoped capability.
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'platform.admin'])
    ->group(function (): void {
        Route::get(
            '/',
            [PlatformDashboardController::class, 'index'],
        )->name('dashboard');

        Route::get(
            '/users',
            [PlatformUserController::class, 'index'],
        )->name('users.index');

        Route::get(
            '/users/{user}',
            [PlatformUserController::class, 'show'],
        )->name('users.show');

        Route::get(
            '/organizations',
            [PlatformOrganizationController::class, 'index'],
        )->name('organizations.index');

        Route::get(
            '/organizations/{organization}',
            [PlatformOrganizationController::class, 'show'],
        )->name('organizations.show');

        Route::get(
            '/billing',
            [PlatformBillingController::class, 'index'],
        )->name('billing.index');

        Route::get(
            '/billing/subscriptions',
            [PlatformBillingController::class, 'subscriptions'],
        )->name('billing.subscriptions.index');

        Route::get(
            '/billing/payments',
            [PlatformBillingController::class, 'payments'],
        )->name('billing.payments.index');

        Route::get(
            '/product-catalog',
            [PlatformProductCatalogController::class, 'index'],
        )->name('product-catalog.index');

        Route::post(
            '/product-catalog/plans/{planCode}/versions',
            [PlatformProductCatalogController::class, 'store'],
        )->name('product-catalog.versions.store');

        Route::get(
            '/product-catalog/plans/{planCode}',
            [PlatformProductCatalogController::class, 'show'],
        )->name('product-catalog.show');

        Route::get(
            '/product-catalog/versions/{billingPlanVersion}/edit',
            [PlatformProductCatalogController::class, 'edit'],
        )->name('product-catalog.versions.edit');

        Route::put(
            '/product-catalog/versions/{billingPlanVersion}',
            [PlatformProductCatalogController::class, 'update'],
        )->name('product-catalog.versions.update');

        Route::post(
            '/product-catalog/versions/{billingPlanVersion}/publish',
            [PlatformProductCatalogController::class, 'publish'],
        )->name('product-catalog.versions.publish');
    });
