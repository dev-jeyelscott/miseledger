<?php

namespace App\Providers;

use App\Console\Commands\StartMiseLedgerMcpServer;
use App\Enums\OrganizationPermission;
use App\Mcp\AiMcpExecutionContext;
use App\Models\Organization;
use App\Models\User;
use App\Support\Ai\Providers\AiProviderAdapter;
use App\Support\Ai\Providers\CodexAppServerProvider;
use App\Support\Billing\BillingConfigurationValidator;
use App\Support\Billing\OrganizationCommercialWriteGate;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Cashier::ignoreRoutes();

        $this->app->singleton(AiMcpExecutionContext::class);
        $this->app->bind(AiProviderAdapter::class, CodexAppServerProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAiRateLimiting();
        $this->configureAuthorization();
        $this->configureBilling();

        $this->commands([
            StartMiseLedgerMcpServer::class,
        ]);
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
     * Keep interactive abuse limits tenant- and member-specific. Provider
     * execution has an additional shared queue limiter in ProcessAiRun.
     */
    protected function configureAiRateLimiting(): void
    {
        RateLimiter::for('ai-message', function (Request $request): Limit {
            $organizationId = $request->attributes->get('activeOrganization')?->getKey();

            return Limit::perMinute((int) config('ai.rate_limits.messages_per_minute'))
                ->by('ai-message:'.($organizationId ?? 'none').':'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('ai-connection', function (Request $request): Limit {
            return Limit::perHour((int) config('ai.rate_limits.connections_per_hour'))
                ->by('ai-connection:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('ai-provider-openai', fn (): Limit => Limit::perMinute(
            (int) config('ai.rate_limits.provider_runs_per_minute'),
        )->by('ai-provider-openai'));
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
        if (! app()->isProduction()) {
            return;
        }

        BillingConfigurationValidator::validateProduction((array) config('billing'));
    }
}
