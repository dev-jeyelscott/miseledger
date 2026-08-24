<?php

namespace App\Providers;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\OrganizationCommercialWriteGate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureBilling();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register the fixed organization-scoped MVP permission vocabulary.
     */
    protected function configureAuthorization(): void
    {
        foreach (OrganizationPermission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (
                    User $user,
                    Organization $organization,
                ): bool => $user->hasOrganizationPermission(
                    $organization,
                    $permission,
                ) && OrganizationCommercialWriteGate::permits(
                    $organization,
                    $permission,
                ),
            );
        }
    }

    /**
     * Configure Organization as the sole Cashier billable customer model.
     */
    protected function configureBilling(): void
    {
        Cashier::useCustomerModel(Organization::class);

        $this->validateBillingConfiguration();
    }

    /**
     * Fail safely, rather than at first Stripe API call, when required
     * billing configuration is missing outside local/testing environments.
     */
    protected function validateBillingConfiguration(): void
    {
        if (app()->isLocal() || app()->runningUnitTests()) {
            return;
        }

        /** @var list<string> $requiredKeys */
        $requiredKeys = config('billing.required_in_production', []);

        $missing = collect($requiredKeys)
            ->reject(fn (string $key): bool => filled(Arr::get(config('billing'), $key)))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException(
                'Missing required billing configuration: '.$missing->implode(', '),
            );
        }
    }
}
