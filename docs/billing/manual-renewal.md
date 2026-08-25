# PayMongo QR Ph Manual Renewal

Manual QR Ph billing is a collection method, not a subscription type. The durable commercial entitlement remains `BillingSubscription`; each paid period is represented by one `BillingInvoice`, and each QR generation is preserved as a `BillingPayment` attempt.

## Lifecycle

1. An authorized billing administrator starts or renews a manual PayMongo subscription.
2. MiseLedger creates or reuses one invoice for the next commercial period, using the configured Plan Catalog amount in PHP minor units.
3. MiseLedger creates a PayMongo Payment Intent limited to `qrph`, creates and attaches a QR Ph payment method, and stores the resulting attempt and expiry.
4. The browser displays only that local checkout projection. A QR, scan, redirect, or browser message never grants access.
5. A verified `payment.paid` webhook resolves the local Payment Intent, validates provider, organization, amount, currency, and livemode, then idempotently settles the payment and invoice.
6. Settlement advances the subscription to the invoice's exact period boundary. Repeated events return the already-settled attempt and do not extend access again.

## Operational rules

- Enable capability with `BILLING_PAYMONGO_MANUAL_QRPH_ENABLED=true`; this does not alter existing subscriptions.
- Define `PAYMONGO_QRPH_AMOUNT_<PLAN>_<INTERVAL>` in minor units. The browser never supplies an amount.
- Configure PayMongo to deliver `payment.paid`, `payment.failed`, and `qrph.expired` to `/billing/webhooks/paymongo`.
- QR attempts expire after the provider's normal 30-minute policy. Expired attempts are immutable history; generating a new QR creates a separate attempt.
- Access ends at the paid period boundary when no confirmed renewal exists. QR-specific grace periods are not applied.

## Security boundaries

All endpoints require `billing.manage` for the route organization and re-check the invoice organization. Provider callbacks verify their raw-body signature before payload processing. Provider IDs are matched only against local provider-neutral projections, and logs omit credentials, raw payloads, and payment tokens.
