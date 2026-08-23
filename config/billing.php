<?php

/*
|--------------------------------------------------------------------------
| Billing Configuration Contract
|--------------------------------------------------------------------------
|
| This is the single config('billing.*') contract application code must use
| for anything billing-related. It layers Stripe credentials, webhook
| settings, and the billing logger on top of the plan/currency/trial/type
| catalog already defined in config/subscription.php (required via plain
| PHP `require`, not config(), so this file has no dependency on Laravel's
| config-file load order).
|
*/

$subscription = require __DIR__.'/subscription.php';

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe Credentials
    |--------------------------------------------------------------------------
    |
    | Read from the environment only. Never pass these to Inertia::share,
    | a controller response, or any React page prop.
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Identity, Currency, Trial, and Plan Catalog
    |--------------------------------------------------------------------------
    |
    | Sourced from config/subscription.php, which remains the authoritative
    | contract for these values (see docs/subscription-plan-catalog.md).
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
    |
    | Logging channel application code should use for billing-domain events
    | (e.g. Log::channel(config('billing.logger'))). Falls back to the
    | application's default "stack" channel when unset.
    |
    */

    'logger' => env('BILLING_LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Required Configuration
    |--------------------------------------------------------------------------
    |
    | Dot-paths, relative to this file, that must resolve to a non-null
    | value outside local/testing environments. Enforced by
    | AppServiceProvider so missing billing configuration fails safely at
    | boot instead of surfacing as a checkout-time Stripe API error.
    |
    */

    'required_in_production' => [
        'stripe.key',
        'stripe.secret',
        'stripe.webhook_secret',
        'currency',
        'trial_days',
        'subscription_type',
    ],

];
