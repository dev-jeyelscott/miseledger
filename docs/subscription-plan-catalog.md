# Subscription Plan Catalog Contract (P0-004)

This is the authoritative contract for how launch plans, pricing, and
entitlements are represented once billing is implemented. It defines the
configuration shape only; it does not integrate Cashier/Stripe, create
pricing database tables, or select any commercial values.

The contract lives in [`config/subscription.php`](../config/subscription.php)
and is env-backed via `.env.example`.

## Stable identifiers

- **Subscription type** (`subscription.type`, default `default`): the
  identifier passed to Cashier's subscription APIs (e.g.
  `$organization->subscribed($type)`). An organization has exactly one
  subscription of this type regardless of which plan it is on.
- **Plan codes** (`subscription.plans.*` keys): stable internal identifiers
  for each sellable plan. Plan codes, not Stripe Price IDs, are the only
  plan identifier allowed in controllers, policies, or React pages.

## Billing currency is independent from `Organization.currency`

`subscription.currency` is the currency Stripe charges the organization in.
`Organization.currency` (see `app/Models/Organization.php`) governs the
organization's operational/reporting currency for inventory costing. These
are separate concerns and neither reads nor writes the other.

## Trial duration is configuration-owned

`subscription.trial_days` is the only source for trial length. No code path
may hardcode a trial day count; anything needing the trial window (P2 access
resolver, onboarding flows, etc.) must read this configuration value.

## Price IDs are configuration-only

Each plan maps billing interval(s) to a Stripe Price ID through env
variables resolved inside `config/subscription.php`
(`subscription.plans.<code>.prices.<interval>`). Price IDs:

- Must never be hardcoded in application code.
- Must never appear in a controller, Inertia response, or React page.
- Are only ever read from this configuration by the future checkout/billing
  integration (P1-004).

## Feature entitlements and limits

Each plan entry also carries `features` (a list of entitlement keys) and
optional `limits` (quantitative caps, e.g. seat or location counts). No
`plans`, `features`, or `plan_features` database tables are required for the
MVP: the configuration array is the single source of truth consumed by the
future entitlement/access-resolution work (P2).

## Unresolved business decisions

The following values are intentionally left unresolved rather than guessed,
and must be supplied before P1-004/P2 can build on this contract:

1. Which plan codes are sold at launch, and their names.
2. Whether both monthly and yearly intervals are required at launch, or
   monthly-only is sufficient for the MVP.
3. The Stripe Price ID for each approved plan/interval combination.
4. Which features and quantitative limits (if any) are enforced per plan.
5. The initial plan exposure policy — whether every configured plan is
   purchasable at launch or only a subset is publicly offered.
6. The SaaS billing currency (`subscription.currency`).
7. The trial duration in days (`subscription.trial_days`).

## Explicit non-goals of this task

- No Cashier/Stripe installation or integration.
- No `plans`, `features`, or `plan_features` database tables.
- No hardcoded Stripe Price IDs anywhere in the codebase.
- No selection of plan codes, prices, limits, trial length, or billing
  currency without business approval.
- No payment-provider secrets exposed through Inertia.

## Downstream consumers (future phases, not implemented here)

- **P1-004** — Cashier/Stripe integration that resolves Price IDs from this
  configuration for checkout and billing portal sessions.
- **P2** — access resolver / entitlement checks that read `features` and
  `limits` from this configuration per plan code.
