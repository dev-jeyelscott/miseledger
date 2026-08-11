<?php

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationLocationController;
use App\Http\Controllers\OrganizationMemberController;
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
    });
});

require __DIR__.'/settings.php';
