# Existing-Organization Rollout Plan (P8-004, PB-002)

This is the authoritative pre-enforcement rollout plan for commercial
subscription access enforcement for organizations that existed before
subscription billing shipped.

Commercial write enforcement is already wired through
`OrganizationCommercialWriteGate` into organization permission checks. This
document defines how legacy organizations are protected while the billing
architecture evolves from today's Stripe/Cashier implementation toward the
provider-neutral commercial contract.

## Why this plan is required

`OrganizationSubscriptionAccessResolver` currently derives commercial access
from the generic-trial `trial_ends_at` field and the durable provider-neutral
local subscription projection.

Organizations created before billing shipped may have neither a subscription
nor a `trial_ends_at` value. Without an explicit legacy policy, such an
organization could become `read_only` merely because it predates billing.

This plan defines the classification and rollout mechanism that prevents that
surprise.

Provider-neutral billing work must preserve this protection. Selecting a new
provider for subscription acquisition must not implicitly reclassify a legacy
organization or change its commercial access.

## Classification (`organizations.rollout_classification`)

Every existing organization must receive exactly one explicit classification
through approved operational governance.

Classification must never be inferred from `created_at`, `updated_at`,
payment-provider identifiers, or other incidental data, and must never be
auto-assigned by a migration backfill.

The column created by
`database/migrations/2026_08_25_000000_add_rollout_classification_to_organizations_table.php`
is nullable and starts unassigned.

`App\Enums\OrganizationRolloutClassification` defines the five allowed
values:

| Classification | Meaning | Access behavior |
|---|---|---|
| `development_test` | Internal engineering or QA tenant, never a real customer | Permanently writable regardless of trial or subscription state |
| `internal_free` | Company-operated tenant intentionally not billed | Permanently writable |
| `grandfathered` | Real pre-existing customer approved to keep full access without a paid subscription | Permanently writable |
| `trial_eligible` | Real pre-existing customer approved to re-enter the normal trial flow | Normal trial or subscription-derived access after operations sets `trial_ends_at` |
| `immediately_billable` | Real pre-existing customer approved to subscribe now | Normal trial or subscription-derived access |

These classifications are application policy. They are not Stripe or
PayMongo states and must not be owned by a payment provider.

## Current legacy-detection implementation

The current Stripe-first resolver contains a compatibility rule for an
unclassified legacy organization.

Today an organization resolves as legacy and writable when all of the
following are true:

```text
rollout_classification IS NULL
trial_ends_at IS NULL
no durable billing customer exists
```

The `stripe_id` condition is intentionally documented as a **current**
**Stripe-specific implementation detail**. It reflects the present Cashier
schema and the fact that beginning Stripe checkout creates or associates a
Stripe customer.

It must not be interpreted as the provider-neutral definition of a legacy
organization.

The current rule guarantees that a pre-billing tenant which has never entered
the existing Stripe trial/checkout path does not unexpectedly become
read-only merely because commercial enforcement exists.

A later multi-provider implementation must preserve the same business
guarantee using the durable provider-neutral billing ownership model selected
by that later task.

PB-001 and PB-002 do not change the current resolver or database fields.

This protection never overrides `Organization.active`, which remains the
independent administrative gate, and never mutates `StockMovement`,
`StockBalance`, or any other stock-ledger record.

## Provider ownership and rollout

Existing paid subscriptions retain their provider ownership.

Changing the application's selected acquisition provider:

- Does not reclassify an organization.
- Does not migrate an existing subscription.
- Does not remove an existing legacy exemption.
- Does not rewrite an existing Stripe customer or subscription.
- Applies only when a new paid subscription is acquired.

When provider-neutral billing is implemented, lifecycle servicing for an
existing subscription must use that subscription's provider ownership.

## Rollout order and activation gates

Commercial enforcement for existing organizations must not be considered
operationally ready until every applicable gate below is verified.

### 1. Billing foundation verified

The selected provider's acquisition, lifecycle synchronization,
authentication, secret management, failure handling, and observability must
be deployed and verified for the target environment.

For the current Stripe implementation this means, at minimum,
`config/billing.php`, Cashier configuration, Stripe checkout, webhook
synchronization, queue handling, and billing secret management.

No equivalent PayMongo implementation is claimed by this document.

### 2. Tenant classification captured

Every existing organization must have an approved
`rollout_classification`.

Operational verification:

```sql
SELECT count(*)
FROM organizations
WHERE rollout_classification IS NULL;
```

The result must be `0` before deliberately moving the entire existing tenant
population away from the legacy fail-open period.

### 3. Billing UI verified

Organization billing and acquisition/recovery pages must render correctly for
each rollout classification.

The UI must not imply that changing the selected provider migrates an
existing subscription.

### 4. Acquisition and lifecycle synchronization verified

At least one end-to-end subscription acquisition must be completed using the
provider intended for the environment, and its authoritative lifecycle must
be observed in MiseLedger's synchronized commercial state.

For today's implementation this is a Stripe test-mode or approved live-mode
Checkout flow followed by verified Stripe webhook synchronization into the
Cashier `subscriptions` table and application billing effects.

Future providers must satisfy equivalent provider-specific verification
before they are enabled for acquisition.

### 5. Monitoring verified

Provider lifecycle failures, webhook or callback failures, synchronization
failures, and acquisition failures must be observable before commercial
enforcement depends on that provider.

Today's implementation uses Stripe-specific billing observability. PB-001
does not claim equivalent PayMongo monitoring exists.

## Activation behavior

Only after the applicable rollout gates pass should operations deliberately
move `trial_eligible` and `immediately_billable` organizations onto their
real trial or subscription-derived commercial state.

No global commercial enforcement switch is required by the current rollout
design. Activation is primarily organization-specific and remains reversible
through explicit classification and trial/subscription state.

Provider selection is a separate concern. It decides new subscription
acquisition and does not act as an enforcement switch.

## Rollback

Rollback of an organization's commercial rollout remains a data or
subscription-policy operation, not a stock-ledger mutation.

A specific organization may be restored to writable commercial access by
assigning an approved permanently exempt classification, extending an
approved trial, or restoring a valid provider-owned subscription according to
the applicable commercial policy.

The rollout-classification migration must not be rolled back while
organizations depend on its classifications or legacy protection.

No rollback operation described here may rewrite `StockMovement`,
`StockBalance`, historical inventory records, or audit evidence merely to
change commercial access.

## Provider-neutral migration constraint

When generic provider ownership is introduced later, migration design must
preserve the known ownership of existing Stripe subscriptions.

A migration must not guess provider ownership from the then-current value of
`BILLING_PROVIDER`.

Existing Cashier subscription records and their Stripe identifiers are
evidence that those subscriptions are Stripe-owned.

Any row whose provider ownership cannot be established deterministically must
fail closed for provider-management operations and require explicit
reconciliation. It must not be silently assigned to the currently selected
provider.

## Explicit non-goals of PB-001 and PB-002

These tasks do not:

- Classify any organization.
- Change `OrganizationSubscriptionAccessResolver`.
- Change the current `stripe_id` legacy-detection rule.
- Add provider ownership persistence.
- Add PayMongo.
- Migrate existing Stripe subscriptions.
- Add a global enforcement flag.
- Change `Organization.active`.
- Change stock-ledger behavior.
