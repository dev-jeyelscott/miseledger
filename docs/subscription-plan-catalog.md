# Subscription Plan Catalog Contract (P0-004, PB-001)

This is the authoritative contract for how subscription plans, pricing,
provider selection, provider ownership, and entitlements are represented.

The current executable subscription runtime remains Laravel Cashier with
Stripe. PB-003 and PB-004 implement the provider-aware server configuration
and production boot-validation portions of the provider-neutral commercial
contract. PayMongo checkout, lifecycle adapters, generic provider ownership
persistence, and other PayMongo runtime behavior are still not implemented.

The current plan catalog lives in
[`config/subscription.php`](../config/subscription.php) and is exposed to
billing infrastructure through [`config/billing.php`](../config/billing.php).

## Stable application identifiers

### Subscription type

`subscription.type`, currently defaulting to `default`, identifies the single
commercial subscription slot owned by an organization.

The current Stripe implementation passes this identifier to Cashier
subscription APIs such as `$organization->subscribed($type)`. Future provider
integrations must preserve the same application-level subscription meaning
without requiring business code to depend on provider-specific APIs.

### Plan codes

`subscription.plans.*` keys and `App\Enums\PlanCode` are stable internal plan
identifiers.

Plan codes are the application-facing commercial identity. Provider-specific
identifiers such as Stripe Price IDs or future PayMongo product, plan,
payment-link, or subscription identifiers must never replace `PlanCode` in
controllers, policies, entitlement checks, React pages, or other business
logic.

## Billing provider selection contract

The future provider-neutral runtime must recognize exactly these provider
identifiers:

- `stripe`
- `paymongo`

The implemented provider-selection environment contract is:

```text
BILLING_PROVIDER=
BILLING_STRIPE_ENABLED=
BILLING_PAYMONGO_ENABLED=
```

These values have the following semantics.

`BILLING_PROVIDER` selects the provider used for **new paid-subscription**
**acquisition only**.

`BILLING_STRIPE_ENABLED` controls whether Stripe may accept new subscription
acquisition.

`BILLING_PAYMONGO_ENABLED` controls whether PayMongo may accept new
subscription acquisition.

Enabling a provider does not select it. A provider may be enabled while a
different enabled provider is selected for new acquisitions.

An unset, blank, malformed, or unsupported `BILLING_PROVIDER` must fail
closed. The application must not silently choose Stripe, PayMongo, or any
other provider.

The selected provider must also be enabled. For example,
`BILLING_PROVIDER=stripe` with `BILLING_STRIPE_ENABLED=false` must make new
paid-subscription acquisition unavailable rather than falling back to
PayMongo.

PB-003 and PB-004 implement these values in `.env.example`,
`config/billing.php`, and production boot validation. This is configuration
and validation infrastructure only. Selecting PayMongo does not by itself
implement PayMongo checkout, webhook handling, subscription synchronization,
or lifecycle servicing.

## Subscription provider ownership

Every paid subscription has provider ownership.

Provider ownership is immutable for that subscription lifecycle. Changing
`BILLING_PROVIDER` must never migrate, relabel, cancel, recreate, or redirect
an existing subscription to another provider.

Provider selection therefore has two separate concerns:

1. The selected application provider determines where a **new** subscription
   is acquired.
2. The subscription's own provider ownership determines where that existing
   subscription is subsequently serviced.

Cancellation, renewal, billing recovery, provider portal access, webhook
processing, reconciliation, subscription synchronization, and other
provider-specific lifecycle operations must use the existing subscription's
provider ownership rather than the application's currently selected
acquisition provider.

The current database does not yet implement a generic provider-ownership
field. Cashier's `subscriptions` table currently contains Stripe-specific
fields including `stripe_id`, `stripe_status`, and `stripe_price`.
Organization billing identity also currently uses Stripe-specific customer
fields.

A later implementation phase must introduce the minimum durable mechanism
needed to record generic provider ownership before multiple providers can
safely own subscriptions. PB-001 does not select that schema or add it.

All subscriptions already created through the current implementation are
Stripe-owned. Introducing or selecting PayMongo later must not alter their
ownership.

## Billing currency is independent from `Organization.currency`

`subscription.currency` represents the SaaS billing currency used when a
provider charges an organization.

`Organization.currency` governs the organization's operational and reporting
currency for inventory costing.

These concerns are independent. Billing code must never change inventory
currency semantics, and inventory code must never derive operational currency
from billing-provider configuration.

The current Stripe implementation consumes `subscription.currency` through
`config('billing.currency')`.

## Trial duration is configuration-owned

`subscription.trial_days` is the application source for configured trial
duration.

No code path may hardcode a trial day count. Provider integrations must map
the approved MiseLedger trial contract into their provider-specific
capabilities without changing the commercial access semantics defined in
[`subscription-access-matrix.md`](subscription-access-matrix.md).

## Provider-specific external pricing identifiers

Provider-specific pricing identifiers are infrastructure details.

The current Stripe adapter maps plan intervals to Stripe Price IDs through
`subscription.plans.<code>.prices.<interval>`.
`App\Support\Billing\PlanCatalog` currently validates and resolves those
Stripe Price IDs.

Current Stripe Price IDs:

- Must never be hardcoded in controllers, policies, React pages, or inventory
  code.
- Must never be exposed to the browser as application plan identifiers.
- Must be resolved through the billing plan catalog.
- Must fail closed when missing, malformed, unknown, or ambiguously
  configured.

A future PayMongo implementation must keep its external identifiers behind a
PayMongo-specific configuration or adapter boundary. It must not overload
`PlanCode`, require frontend consumers to understand PayMongo identifiers, or
reinterpret Stripe Price IDs as provider-neutral values.

The exact future provider-specific configuration shape is intentionally
deferred until the provider runtime is implemented.

## Feature entitlements and limits

Each plan definition carries a display name, feature entitlement keys, and
optional quantitative limits.

A limit set to `null` is explicitly unlimited. An omitted required limit must
not be silently interpreted as unlimited.

Plan entitlements are application-owned commercial policy. They must remain
independent from the payment provider selected for subscription acquisition.

Changing an organization's payment provider must not change which application
features a given internal `PlanCode` represents.

## Current Stripe implementation boundary

Provider-aware configuration and production boot validation now exist, but
the subscription runtime remains intentionally Stripe-specific:

- Laravel Cashier owns Stripe subscription synchronization.
- `PlanCatalog` resolves Stripe Price IDs.
- `OrganizationSubscriptionAccessResolver` reads Cashier subscription state.
- Stripe checkout and billing portal flows use Cashier.
- Stripe webhook routes synchronize Cashier state and application billing
  effects.
- Existing Cashier subscription rows do not contain generic provider
  ownership.

Those implementation details remain valid until later PB tasks replace or
wrap them with a provider-neutral boundary.

PB-001 and PB-002 change documentation contracts only.

## Secret-management contract

Payment-provider secrets are server-only.

The following must never be included in Inertia props, API responses intended
for browser consumption, React configuration, browser bundles, logs intended
for customers, or other frontend-visible payloads:

- Provider secret/API keys.
- Webhook signing secrets.
- Private authentication credentials.
- Signing material.
- Provider-internal tokens or sensitive identifiers that are not explicitly
  approved for client use.

Only deliberately safe presentation or capability metadata may cross the
frontend boundary, such as an internal plan code, plan display name, supported
billing interval, or a non-secret provider display capability when required by
the UI.

The current Stripe implementation already follows this rule by keeping raw
Stripe Price IDs and secrets server-side.

## Fail-closed rules

The provider-neutral implementation must fail closed when:

- `BILLING_PROVIDER` is missing, blank, malformed, or unsupported.
- The selected provider is disabled.
- The selected internal plan cannot be mapped to an external price or product
  required by that provider.
- A provider-specific external identifier is malformed or ambiguous.
- Existing subscription provider ownership cannot be established safely.

Failure must prevent new acquisition or unsafe provider operations. It must
not grant paid entitlements, silently select another provider, or mutate an
existing subscription's ownership.

## Historical non-goals of PB-001 and PB-002

PB-001 and PB-002 were documentation-only tasks and did not:

- Install or integrate PayMongo.
- Add provider factories, interfaces, adapters, or service bindings.
- Add environment variables to `.env.example`.
- Modify `config/billing.php` or `config/subscription.php`.
- Add or modify migrations.
- Add generic provider ownership persistence.
- Change existing Stripe or Cashier behavior.
- Migrate existing Stripe subscriptions.
- Change plan entitlements.
- Change commercial access behavior.
- Expose provider secrets to the frontend.

## Future implementation requirements

Later provider-runtime work must preserve all of the following:

1. Stable internal `PlanCode` identity.
2. Fail-closed provider selection.
3. Explicit provider enablement.
4. Immutable provider ownership for existing subscriptions.
5. Provider-specific external identifiers behind provider infrastructure.
6. Provider-neutral lifecycle normalization before commercial access
   decisions.
7. Server-only provider secrets.
8. Existing organization isolation, commercial authorization, and ledger
   integrity boundaries.
