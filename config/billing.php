<?php

/*
|--------------------------------------------------------------------------
| Billing Configuration Contract
|--------------------------------------------------------------------------
|
| The selected provider controls new subscription acquisition only. Existing
| provider-owned subscriptions retain their provider ownership.
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
        'manual_qrph' => (bool) env(
            'BILLING_PAYMONGO_MANUAL_QRPH_ENABLED',
            false,
        ),
        'mode' => env('PAYMONGO_MODE'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'customer_phone' => env('PAYMONGO_CUSTOMER_PHONE'),
        'api_base_url' => env(
            'PAYMONGO_API_BASE_URL',
            'https://api.paymongo.com/v1',
        ),
    ],
];

return [
    'provider' => env('BILLING_PROVIDER'),

    'providers' => $providers,

    'stripe' => $providers['stripe'],

    'subscription_type' => $subscription['type'],

    'currency' => $subscription['currency'],

    'trial_days' => $subscription['trial_days'],

    /*
    |--------------------------------------------------------------------------
    | Legacy Bootstrap Catalog
    |--------------------------------------------------------------------------
    |
    | Provider IDs remain infrastructure configuration. Commercial names,
    | tiers, feature grants, limits, and numeric prices move to PostgreSQL
    | after the Phase 5 cutover.
    |
    */

    'plans' => $subscription['plans'],

    /*
    |--------------------------------------------------------------------------
    | Versioned Catalog Cutover
    |--------------------------------------------------------------------------
    |
    | Keep false while schema, Version 1 drafts, publication, and subscriber
    | pin backfill are incomplete. When true, paid commercial resolution fails
    | closed unless a valid published/pinned PostgreSQL version exists.
    |
    */

    'versioned_catalog_enabled' => (bool) env(
        'BILLING_VERSIONED_CATALOG_ENABLED',
        false,
    ),

    'upgrade_minimum_manual_amount' => env(
        'BILLING_UPGRADE_MINIMUM_MANUAL_AMOUNT',
    ),

    'logger' => env('BILLING_LOG_CHANNEL', 'stack'),

    'required_in_production' => [
        'currency',
        'trial_days',
        'subscription_type',
    ],
];
