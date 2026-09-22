<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Register the Horizon gate.
     *
     * The `horizon.middleware` stack (`platform.admin`) already enforces
     * this boundary on every request in every environment; this gate is
     * kept in sync as the package's own authorization hook (defense in
     * depth), matching the same platform-admin-plus-strong-factor authority
     * required by App\Http\Middleware\EnsurePlatformAdmin.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user instanceof User
            && $user->isPlatformAdmin()
            && $user->hasApprovedStrongFactor());
    }
}
