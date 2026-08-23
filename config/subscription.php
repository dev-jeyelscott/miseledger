<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cashier Subscription Type
    |--------------------------------------------------------------------------
    |
    | Stable identifier passed to Cashier's subscription APIs (e.g.
    | $organization->subscribed($type)). It is not a plan code: an
    | organization has exactly one subscription of this type regardless of
    | which plan it is on. Configuration-owned so it is never duplicated as
    | a string literal across controllers, policies, or middleware.
    |
    */

    'type' => env('SUBSCRIPTION_TYPE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Billing Currency
    |--------------------------------------------------------------------------
    |
    | The currency in which the organization is charged by Stripe. This is
    | intentionally independent from `Organization.currency`, which governs
    | the organization's operational/reporting currency and must never be
    | read or written by billing code.
    |
    | UNRESOLVED BUSINESS DECISION: no default is set. The platform's
    | billing currency must be approved before subscriptions can be created.
    |
    */

    'currency' => env('SUBSCRIPTION_BILLING_CURRENCY'),

    /*
    |--------------------------------------------------------------------------
    | Trial Duration
    |--------------------------------------------------------------------------
    |
    | Number of days a newly created organization may use the application
    | before a Cashier subscription is required. Configuration-owned rather
    | than hardcoded so it can be changed without a code deployment.
    |
    | UNRESOLVED BUSINESS DECISION: no default is set. The trial length must
    | be approved before it is enforced anywhere.
    |
    */

    'trial_days' => env('SUBSCRIPTION_TRIAL_DAYS'),

    /*
    |--------------------------------------------------------------------------
    | Plan Catalog
    |--------------------------------------------------------------------------
    |
    | Keys are stable internal plan codes. They are the only plan identifier
    | allowed to appear in controllers, policies, or React pages; Stripe
    | Price IDs must always be resolved through this configuration and never
    | hardcoded or passed to the frontend directly.
    |
    | Each plan maps its billing interval(s) to a Stripe Price ID via env,
    | plus a display name, feature entitlements, and optional quantitative
    | limits granted by that plan. No `plans`, `features`, or
    | `plan_features` database tables exist for the MVP: this array is the
    | single source of truth consumed by `App\Support\Billing\PlanCatalog`
    | (P2-001), which is the only supported way to resolve a Stripe Price
    | ID into a plan definition.
    |
    | UNRESOLVED BUSINESS DECISIONS, none of which are guessed here:
    |   - Which plan codes are sold at MVP launch, and their names.
    |   - Whether monthly and yearly intervals are both required at launch,
    |     or monthly-only is sufficient (affects whether the `prices` shape
    |     below needs both keys populated).
    |   - The Stripe Price ID for each plan/interval combination.
    |   - Which features and quantitative limits (if any) are enforced per
    |     plan for the MVP.
    |   - The initial plan exposure policy (e.g. whether every plan is
    |     purchasable at launch or only a subset is publicly offered).
    |
    | Example shape (left empty until the above is approved). A limit set
    | to `null` is an explicit, deliberate "unlimited"; PlanCatalog treats
    | an undeclared limit key as a configuration error rather than
    | inferring unlimited from its absence:
    |
    | 'plans' => [
    |     'plan_code' => [
    |         'name' => 'Plan Display Name',
    |         'prices' => [
    |             'monthly' => env('SUBSCRIPTION_PRICE_PLAN_CODE_MONTHLY'),
    |             'yearly' => env('SUBSCRIPTION_PRICE_PLAN_CODE_YEARLY'),
    |         ],
    |         'features' => [],
    |         'limits' => [],
    |     ],
    | ],
    |
    */

    'plans' => [],

];
