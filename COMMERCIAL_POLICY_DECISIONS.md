# AGEAX commercial policy decisions

**Status:** Approved for Stripe sandbox testing only. Not authorization for live payments.

**Approved by:** AGEAX sole proprietor
**Decision date:** 2026-08-25

## Business and launch status

- **Brand name:** AGEAX
- **Intended legal structure:** Philippine sole proprietorship
- **Registration status:** Pending
- **Operating address:** B32 L06 Bria Homes, Norzagaray, Bulacan, Philippines
- **Current Stripe live-payment gate:** Stripe live mode must remain disabled until the business registration is completed and the final legal entity details replace this draft record.

The Stripe live-payment gate above is Stripe-specific operational policy. It
must not be interpreted as approval, rejection, or equivalent live-account
policy for PayMongo or another payment provider.

## Provider-neutral billing selection policy

PB-001 established the following commercial contract. PB-003 and PB-004
implement its server-side provider configuration and production validation
boundary without implementing PayMongo acquisition or lifecycle behavior.

Supported acquisition-provider identifiers are:

```text
stripe
paymongo
```

The provider-selection environment contract is:

```text
BILLING_PROVIDER=
BILLING_STRIPE_ENABLED=
BILLING_PAYMONGO_ENABLED=
```

`BILLING_PROVIDER` selects the provider for **new paid-subscription**
**acquisition only**.

`BILLING_STRIPE_ENABLED` and `BILLING_PAYMONGO_ENABLED` independently
determine whether their respective provider may accept new subscription
acquisition.

Enabling a provider does not select it.

An unset, blank, malformed, or unsupported `BILLING_PROVIDER` must fail
closed. The application must not silently choose a fallback provider.

The selected provider must be enabled. If it is disabled, new paid
subscription acquisition must remain unavailable until configuration is
corrected.

PB-003 and PB-004 implement these environment variables in server-side
billing configuration and production boot validation. PayMongo acquisition,
webhook processing, subscription synchronization, lifecycle servicing, and
generic provider ownership remain later implementation work.

## Existing subscription provider ownership

Every paid subscription must retain the provider that owns it.

Changing `BILLING_PROVIDER` applies only to new subscription acquisition. It
must not migrate, relabel, cancel, recreate, or redirect an existing
subscription to another provider.

Existing subscriptions created by today's Cashier/Stripe implementation are
Stripe-owned.

Future cancellation, renewal, recovery, lifecycle synchronization,
reconciliation, portal behavior, and provider-specific management must use
the subscription's own provider ownership rather than the currently selected
provider for new acquisitions.

The current database does not yet contain generic provider-ownership
persistence. That capability requires a later implementation task and must
not be considered complete merely because this policy is documented.

## Billing-provider security

Payment-provider secrets are server-only.

Secret keys, private API credentials, webhook signing secrets, signing
material, and other provider-private configuration must never be sent through
Inertia props, frontend APIs, React configuration, or browser bundles.

Only deliberately safe presentation metadata may be exposed to the browser.

## Billing and tax

- **Currencies:** Philippine peso (PHP) and United States dollar (USD).
- **Market:** Customers in the Philippines and internationally.
- **Tax registration:** The business is not currently VAT-registered with the BIR.
- **Current Stripe Tax policy:** Enabled for any future Stripe live launch, subject to accountant or tax-adviser review and Stripe-supported tax registrations.
- **Current Stripe invoice/receipt policy:** Stripe receipts only; no separate formal-invoice workflow is currently required.

The Stripe-specific tax and receipt decisions above do not establish
equivalent PayMongo capabilities or policy. PayMongo-specific tax, receipt,
invoice, refund, settlement, and live-account requirements remain unresolved
until separately reviewed and approved.

## Subscription terms

These are commercial terms and are intended to remain independent of the
payment provider unless a later approved policy explicitly states otherwise.

- **Trial:** 30 days for every plan; a payment method is required at signup.
- **Trial conversion:** The selected subscription automatically charges when the trial ends unless cancelled beforehand.
- **Cancellation:** Customers may cancel at any time. Cancellation takes effect at the end of the current paid billing period and access continues through that period.
- **Refunds:** A full refund is available within 14 days of the first paid subscription charge when requested through support. There are no prorated refunds for unused time after cancellation and no discretionary refunds after that period, except where applicable law requires otherwise.

A provider implementation must not silently weaken or redefine these
commercial terms. If a provider cannot implement an approved term safely,
that provider must remain unavailable for the affected acquisition flow until
the discrepancy is explicitly resolved.

## Provider-neutral subscription lifecycle

MiseLedger commercial access uses these normalized lifecycle concepts:

```text
generic trial
active
past_due
unpaid
grace period
ended
```

Provider-specific statuses are implementation inputs and must be normalized
before they control commercial application access.

Stripe-specific values such as `trialing` remain Stripe adapter terminology,
not additional commercial lifecycle states.

The commercial access mapping is defined in
`docs/subscription-access-matrix.md`.

## Customer terms, privacy, and support

- **Terms of Service:** Drafted in `TERMS_OF_SERVICE.md`; not yet published.
- **Privacy Policy:** Drafted in `PRIVACY_POLICY.md`; not yet published.
- **Account-data retention after cancellation:** 90 days, subject to any longer retention obligation imposed by law or payment providers.
- **Data export:** Available to customers during the 90-day retention period.
- **Support channel:** jleward.escote17@gmail.com.

## Required follow-up before live launch

1. Complete the Philippine sole-proprietorship registration and update the legal name, registration number, tax details, and business address where required.
2. Have the Terms of Service, Privacy Policy, international tax treatment, and refund policy reviewed for the markets in which AGEAX will sell.
3. Publish the approved legal pages and make them available before account creation or payment collection.
4. Configure and verify Stripe live-mode requirements, including appropriate tax registrations and Stripe Tax settings, before enabling Stripe live acquisition.
5. Perform a separate PayMongo commercial, legal, tax, receipt/invoice, settlement, refund, security, and live-account review before approving PayMongo for live acquisition. This document does not currently approve or describe those provider-specific capabilities.
6. Implement and verify durable provider ownership before allowing more than one provider to own subscriptions.
7. Verify that switching the selected acquisition provider cannot alter or orphan existing provider-owned subscriptions.
