# Subscription Access Matrix (P0-003, PB-002)

This is the single authoritative contract mapping MiseLedger's normalized
subscription lifecycle to application **commercial access**.

The commercial contract is provider-neutral. Provider integrations may use
different raw statuses and lifecycle APIs, but those provider-specific values
must be normalized before commercial access decisions are made.

The current executable implementation is still Laravel Cashier with Stripe
and directly interprets Cashier and Stripe state. PB-002 documents the future
normalization boundary without claiming that a provider adapter already
exists.

## Two independent axes

Commercial access is layered on top of, and is fully independent from,
administrative access.

1. **Administrative axis:** `Organization.active` is the manager/operator
   enable-disable flag. If it is `false`, the organization is not resolvable
   through the normal organization context regardless of commercial state.
2. **Commercial axis:** this document applies only after the organization
   passes the administrative gate. Commercial billing state never sets,
   clears, or otherwise controls `Organization.active`.

## Commercial access modes

| Mode | Write access | Notes |
|---|---|---|
| `full` | enabled | no billing warning |
| `full_with_warning` | enabled | billing warning surfaced |
| `read_only` | disabled | billing recovery remains reachable |

Commercial access is derived at request time. It is not a persisted
replacement subscription state machine.

The authoritative provider owns the raw subscription lifecycle. MiseLedger
owns the normalization from that provider lifecycle into the commercial
states defined below and then into `AccessMode`.

## Provider-neutral lifecycle states

MiseLedger recognizes exactly these commercial lifecycle states:

| Lifecycle state | Commercial meaning | Access mode | Billing recovery reachable |
|---|---|---|---|
| `generic trial` | Organization is inside an approved trial window, regardless of whether that trial is currently represented only by MiseLedger or by the owning provider | `full` | n/a |
| `active` | Provider-owned paid subscription is active | `full` | n/a |
| `past_due` | Payment collection has failed but the subscription remains recoverable and operational access should continue temporarily | `full_with_warning` | yes |
| `grace period` | Subscription is scheduled to end but remains inside its already-authorized paid access period | `full_with_warning` | yes |
| `unpaid` | Provider lifecycle indicates payment recovery is exhausted or the account must no longer receive commercial write access | `read_only` | yes |
| `ended` | Trial or subscription entitlement has ended | `read_only` | yes |

Provider-specific raw lifecycle names are not additional MiseLedger
commercial states.

## Provider normalization boundary

Every provider integration must translate its provider-specific lifecycle
into the commercial states above before application access is decided.

Business authorization, commercial middleware, policies, entitlement
presentation, and inventory workflows must not branch directly on Stripe,
PayMongo, Cashier, or another provider's raw status vocabulary.

A provider adapter must never treat an unknown raw provider status as
`active`. Unknown or unsupported provider state must fail closed to a
non-writable commercial result until it can be normalized safely.

This requirement does not mean provider raw statuses must be discarded.
Provider-specific infrastructure may retain them for synchronization,
reconciliation, debugging, audit evidence, and provider API operations.

## Current Stripe/Cashier mapping

The current implementation does not yet contain a generic provider adapter.
`OrganizationSubscriptionAccessResolver` reads Cashier's synchronized local
subscription state directly.

Its current Stripe-specific behavior corresponds to the provider-neutral
contract as follows:

| Current Stripe/Cashier input | Normalized commercial state | Current access result |
|---|---|---|
| Application `trial_ends_at` is in the future and there is no Cashier subscription | `generic trial` | `full` |
| Stripe `trialing`, observed through Cashier `onTrial()` | `generic trial` | `full` |
| Stripe `active` | `active` | `full` |
| Stripe `past_due` | `past_due` | `full_with_warning` |
| Cashier `onGracePeriod()`, with `ends_at` still in the future | `grace period` | `full_with_warning` |
| Stripe `unpaid` | `unpaid` | `read_only` |
| Trial expired with no subscription, or Cashier subscription `ended()` after its paid period | `ended` | `read_only` |

The `trialing` value above is Stripe adapter input only. It is not a seventh
MiseLedger lifecycle state.

The current resolver also returns `read_only` for unsupported Stripe statuses
rather than granting writable access. A future provider-neutral adapter must
preserve that fail-closed property.

## Rules

1. Provider subscription status is read-only input to this access model.
   Commercial access resolution must not mutate provider subscription state.
2. `past_due` remains `full_with_warning`. It does not immediately become
   `read_only`, allowing day-to-day operations while billing is recovered.
3. `grace period` remains `full_with_warning` until its paid entitlement
   ends. Only after that entitlement ends does the organization become
   `read_only`.
4. `ended` and `unpaid` are `read_only`, not inaccessible. Authorized billing
   recovery routes must remain reachable so the organization can restore
   commercial access without losing historical data.
5. This matrix carries no inventory-specific exceptions. `RecordStockMovement`,
   `StockMovement`, `StockBalance`, replay logic, and related ledger actions
   must never branch on billing state or provider identity.
6. Commercial write enforcement belongs at the authorization,
   middleware/policy, or application boundary that invokes business
   operations, never inside the stock-ledger primitive.
7. `Organization.active` remains an independent administrative gate.
8. Changing the application's selected provider for new subscription
   acquisition must not change the commercial state or provider ownership of
   an existing subscription.
9. Existing subscriptions must be normalized using the provider that owns
   them, not the application's currently selected acquisition provider.
10. Billing-provider credentials, signing secrets, and private provider
    configuration remain server-only.

## Existing-organization rollout behavior

Rollout classification remains independent from provider selection.

Organizations classified as permanently exempt retain their existing
commercial behavior regardless of provider selection.

Legacy-organization protections defined in
[`existing-organization-rollout-plan.md`](existing-organization-rollout-plan.md)
also remain in force. PB-002 does not reclassify any organization or move any
tenant between access modes.

## Commercial transition matrix

Provider-neutral behavior that later adapter tests must preserve includes:

```text
generic trial -> active
generic trial -> ended
active -> past_due -> active
active -> past_due -> unpaid
active -> grace period -> active
active -> grace period -> ended
unpaid -> active
ended -> new valid subscription -> active
```

At every transition:

- Commercial access is derived from normalized lifecycle state.
- Authorized billing recovery remains reachable from `read_only`.
- Normal business mutations remain blocked while `read_only`.
- Administrative `Organization.active = false` remains independently
  authoritative.
- Stock-ledger state is never changed merely because billing state changed.

## Current implementation boundary

Today:

- Laravel Cashier synchronizes Stripe subscriptions.
- Cashier's `subscriptions` table stores Stripe-specific lifecycle fields.
- `OrganizationSubscriptionAccessResolver` directly reads Cashier and Stripe
  state.
- Stripe checkout and webhook synchronization remain provider-specific.
- No provider-neutral lifecycle adapter is implemented yet.
- A generic provider-ownership identity layer (`billing_customers` /
  `billing_subscriptions`, see below) exists as of Phase 2, but commercial
  access decisions still read Cashier/Stripe state directly rather than this
  identity layer.

These facts are implementation details, not the long-term business
vocabulary.

## Durable provider-neutral billing identity (Phase 2, PB-005–PB-008)

Phase 2 adds a durable, provider-neutral persistence layer underneath the
commercial contract above. It is additive infrastructure, not a new
entitlement authority:

- `App\Enums\BillingProvider` (`stripe`, `paymongo`) and
  `App\Support\Billing\BillingIdentity` are the sole boundary converting a
  raw provider string into that enum. Business logic should use the enum,
  never the raw `'stripe'`/`'paymongo'` string literals.
- `billing_customers` durably records which external customer identity an
  organization holds on a given provider: `(organization_id, provider,
  external_customer_id, livemode)`, unique per `(organization_id, provider)`
  and per `(provider, external_customer_id)`. An organization may hold
  separate Stripe and PayMongo customer identities simultaneously.
- `billing_subscriptions` durably records normalized subscription identity
  and lifecycle timestamps per provider: `external_subscription_id`,
  `external_plan_id`, `plan_code`, `interval`, `provider_status`,
  `trial_ends_at`, `current_period_ends_at`, `next_billing_at`, `ends_at`,
  `cancelled_at`. Unique per `(provider, external_subscription_id)`. Its
  `(billing_customer_id, organization_id, provider)` composite foreign key
  makes it database-impossible for a subscription to belong to a billing
  customer from a different organization or a different provider.
- `provider_status` is retained verbatim from the provider and is never
  interpreted here — only `OrganizationSubscriptionAccessResolver` decides
  commercial access, and it continues to do so by reading Cashier's
  synchronized Stripe state directly, exactly as described above. This
  table is durable identity/history, not a competing entitlement source.
- **Laravel Cashier is not replaced or migrated away from.** Its
  `subscriptions`/`subscription_items` tables and the `stripe_id`/
  `pm_type`/`pm_last_four` columns on `Organization` remain the Stripe
  integration and storage mechanism exactly as before. `billing_customers`/
  `billing_subscriptions` are synchronized *from* that authoritative Stripe
  state (via `App\Actions\Billing\SynchronizeStripeBillingProjection`,
  triggered by `App\Listeners\SynchronizeBillingProjectionFromWebhook` on
  Cashier's `WebhookHandled` event) — never the reverse.
- `billing_webhook_effects`, which guards webhook-triggered audit entries
  and lifecycle notifications against duplicate delivery, is now
  provider-neutral: uniqueness is enforced at the database level on
  `(provider, external_event_id)` rather than a Stripe-only event id. Stripe
  and PayMongo may each reuse the same raw external event id independently
  without colliding. The legacy `stripe_event_id` column is preserved
  (now nullable, populated only for `provider = stripe` rows) for backward
  compatibility rather than removed.
- PayMongo synchronization is schema- and boundary-ready but not
  implemented in this phase: no PayMongo webhook ingress exists yet, so no
  PayMongo `billing_customers`/`billing_subscriptions` rows are created or
  backfilled by application code.

## Explicit non-goals of PB-002

PB-002 does not:

- Rewrite `OrganizationSubscriptionAccessResolver`.
- Add a provider adapter.
- Add PayMongo.
- Change Cashier or Stripe synchronization.
- Add migrations.
- Change `AccessMode`.
- Change rollout classifications.
- Change entitlement enforcement.
- Change `Organization.active`.
- Change any stock-ledger behavior.
