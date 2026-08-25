# Existing-Organization Rollout Plan (P8-004)

This is the authoritative pre-enforcement rollout plan for turning on
commercial (subscription) access enforcement — already wired via
`OrganizationCommercialWriteGate` into every `OrganizationPermission` gate
(`App\Providers\AppServiceProvider::configureAuthorization`) — for
organizations that existed before subscription billing shipped.

## Why this plan is required

`OrganizationSubscriptionAccessResolver` derives commercial access from
Cashier subscription state and the generic-trial `trial_ends_at` column.
Organizations created before billing shipped have no subscription and no
`trial_ends_at` value (the column was added later with no backfill).
Unmitigated, such an organization would resolve to `read_only` the instant
enforcement observes it — not because of any billing decision, but because
it predates billing. This plan defines the classification and rollout
mechanism that prevents that surprise.

## Classification (`organizations.rollout_classification`)

Every existing organization must receive exactly one explicit
classification, assigned by approved operational governance — never
inferred from `created_at`/`updated_at`, and never auto-assigned by a
migration backfill. The column
(`database/migrations/2026_08_25_000000_add_rollout_classification_to_organizations_table.php`)
is nullable and starts unassigned for every row, including existing ones.

`App\Enums\OrganizationRolloutClassification` defines the five allowed
values:

| Classification | Meaning | Access behavior |
|---|---|---|
| `development_test` | Internal engineering/QA tenant, never a real customer | Permanently writable (`OrganizationSubscriptionAccessResolver` exempt), regardless of subscription/trial state |
| `internal_free` | Company-operated tenant intentionally not billed | Permanently writable, same as above |
| `grandfathered` | Real pre-existing customer approved to keep full access without a paid subscription | Permanently writable, same as above |
| `trial_eligible` | Real pre-existing customer approved to re-enter the normal generic-trial flow | Normal trial/subscription-derived access once operations sets `trial_ends_at` |
| `immediately_billable` | Real pre-existing customer approved to be asked to subscribe now | Normal trial/subscription-derived access; typically requires an active subscription or in-progress checkout to stay writable |

Unclassified organizations (`rollout_classification IS NULL`) that also
have no `trial_ends_at` and no Stripe customer (`stripe_id IS NULL`) —
i.e. legacy tenants never touched by the trial/checkout flow — resolve as
writable by default (see `OrganizationSubscriptionAccessResolver::
isUnclassifiedLegacyOrganization`). This is the fail-open guarantee behind
acceptance criterion "no existing organization becomes read-only
unexpectedly": enforcement stays inactive for a tenant until it is
explicitly classified. This never overrides `Organization.active`, which
remains the independent administrative gate, and never touches
`StockMovement`/`StockBalance` ledger state.

## Rollout order and activation gates

Enforcement for existing organizations does not activate until every gate
below is independently verified, in order:

1. **Billing foundation verified** — Cashier configuration
   (`config/billing.php`), queue/webhook reliability, and secret management
   (P7) are deployed and passing in the target environment.
2. **Tenant classification captured** — every existing organization has a
   non-null `rollout_classification`, approved by operational governance.
   Verify with:
   ```
   SELECT count(*) FROM organizations WHERE rollout_classification IS NULL;
   ```
   Must return `0` before proceeding.
3. **Billing UI verified** — organization billing/checkout pages (P8-001..3)
   render correctly for at least one organization in each of the five
   classifications.
4. **Checkout/webhooks verified** — a live (or Stripe test-mode) checkout
   completes end to end and the corresponding webhook is observed to
   synchronize `subscriptions`/`billing_webhook_effects` correctly.
5. **Monitoring verified** — billing observability/alerting (P7-002) is
   confirmed to receive live Stripe events before enforcement activates.

Only after all five gates pass does operations begin assigning
`trial_eligible`/`immediately_billable` classifications their real
`trial_ends_at` or subscription, which is what actually moves a specific
organization off the permanent/fail-open exemption and onto
trial/subscription-derived access. No global "enforcement switch" exists or
is needed: activation is inherently per-organization and reversible by
classification.

## Rollback

Rollback is a data change, not a deploy:

- Reverting a specific organization to full access: set its
  `rollout_classification` back to `development_test`, `internal_free`, or
  `grandfathered` (or extend `trial_ends_at` / restore its subscription).
  Takes effect immediately since access is derived fresh on every request.
- Reverting the schema: `php artisan migrate:rollback` against
  `2026_08_25_000000_add_rollout_classification_to_organizations_table`
  drops the column; with it absent, every organization falls back to the
  pre-P8-004 resolver behavior for that request path, so this migration
  must not be rolled back while any organization actively depends on a
  `development_test`/`internal_free`/`grandfathered`/fail-open exemption.
- No ledger (`StockMovement`/`StockBalance`), audit log, or historical
  record is ever mutated by classification or by rollback: this plan only
  changes commercial write access, derived fresh per request.

## Explicit non-goals of this task

- No organization is actually classified by this task; classification
  values are an approved operational decision, not something inferred here
  from timestamps.
- No global enforcement toggle/feature flag is introduced; activation is
  per-organization via classification, which is reversible without a
  deploy.
- No change to `Organization.active`, ledger behavior, or the two-axis
  administrative/commercial access model established in
  `docs/subscription-access-matrix.md`.
