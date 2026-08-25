<?php

/*
|--------------------------------------------------------------------------
| Billing Configuration Contract
|--------------------------------------------------------------------------
|
| This is the single config('billing.*') contract application code must use
| for billing configuration. Provider-specific credentials remain server-side
| and are never exposed through Inertia props or browser configuration.
|
| The selected provider controls new subscription acquisition only. Existing
| provider-owned subscriptions retain their provider ownership independently
| from this selection.
|
*/

$subscription = require __DIR__.'/subscription.php';

$stripeKey = env('STRIPE_KEY');

$stripeMode = match (true) {
    is_string($stripeKey) && str_starts_with($stripeKey, 'pk_live_') => 'live',
    is_string($stripeKey) && str_starts_with($stripeKey, 'pk_test_') => 'test',
    default => null,
};

$providers = [
    'stripe' => [
        'enabled' => (bool) env('BILLING_STRIPE_ENABLED', false),
        'key' => $stripeKey,
        'secret' => env('STRIPE_SECRET'),
        'mode' => $stripeMode,
        'webhook_secret' => match ($stripeMode) {
            'live' => env('STRIPE_LIVE_WEBHOOK_SECRET'),
            'test' => env('STRIPE_TEST_WEBHOOK_SECRET'),
            default => null,
        },
    ],

    'paymongo' => [
        'enabled' => (bool) env('BILLING_PAYMONGO_ENABLED', false),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
    ],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Acquisition Provider
    |--------------------------------------------------------------------------
    |
    | No default is intentional. Production must explicitly select one of the
    | supported providers, and BillingConfigurationValidator will fail closed
    | when this value is missing, malformed, unsupported, or disabled.
    |
    */

    'provider' => env('BILLING_PROVIDER'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Provider credentials are environment-backed server configuration only.
    | Enabling a provider does not select it.
    |
    */

    'providers' => $providers,

    /*
    |--------------------------------------------------------------------------
    | Transitional Stripe Compatibility
    |--------------------------------------------------------------------------
    |
    | Preserve the existing billing.stripe.* contract during migration. New
    | provider-aware infrastructure should use billing.providers.stripe.*.
    |
    */

    'stripe' => $providers['stripe'],

    /*
    |--------------------------------------------------------------------------
    | Subscription Identity, Currency, Trial, and Plan Catalog
    |--------------------------------------------------------------------------
    |
    | config/subscription.php remains authoritative for application-owned
    | subscription identity, billing currency, trial duration, plans,
    | entitlements, and limits.
    |
    */

    'subscription_type' => $subscription['type'],

    'currency' => $subscription['currency'],

    'trial_days' => $subscription['trial_days'],

    'plans' => $subscription['plans'],

    /*
    |--------------------------------------------------------------------------
    | Billing Logger
    |--------------------------------------------------------------------------
    */

    'logger' => env('BILLING_LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Common Production Requirements
    |--------------------------------------------------------------------------
    |
    | Provider-specific requirements are validated separately according to
    | provider selection and enablement.
    |
    */

    'required_in_production' => [
        'currency',
        'trial_days',
        'subscription_type',
    ],

];
