# Mutation and Feature Entitlement Map (P0-005, PB-002)

This is the authoritative classification of business routes, commercial
recovery routes, and console import entry points for commercial write access
and feature entitlements.

The repository has advanced beyond the original P0-005 inspection baseline.
Commercial write gating, feature entitlement infrastructure, and Stripe
billing routes now exist. PB-002 updates the documentation contract only. It
does not change route behavior, middleware, authorization, provider
integration, or feature enforcement.

It builds directly on:

- [`docs/subscription-access-matrix.md`](subscription-access-matrix.md), which
  defines `full`, `full_with_warning`, and `read_only`.
- [`docs/subscription-plan-catalog.md`](subscription-plan-catalog.md), which
  defines stable application plan identity and provider-neutral acquisition
  semantics.
- The current Stripe/Cashier implementation, which remains the executable
  provider adapter until later provider-neutral runtime work is implemented.

## Classification legend

**Mutation domain** identifies the operational area a write route belongs to.

**Commercial write policy** describes how a route relates to commercial
access:

- `blocked_when_read_only`: normal organization business write. It requires
  writable commercial access.
- `always_allowed`: independent from the commercial write gate, such as
  authentication, organization bootstrap, or billing recovery. Normal
  authentication, organization membership, RBAC, validation, and security
  controls still apply.
- `admin_gate_only`: governed by the administrative axis rather than
  subscription state.

**Read availability** identifies whether a GET or HEAD route remains
available while an organization is commercially `read_only`.

**Feature entitlement** identifies whether an application capability is
subject to a per-plan feature gate. A commercial write policy and a feature
entitlement are separate decisions.

## Authentication and organization-creation routes

These routes precede or fall outside the commercial state of an existing
organization and must not be blocked by an organization's subscription
lifecycle.

| Route(s) | Source | Commercial write policy |
|---|---|---|
| `GET /` (`home`) | `WelcomeController@index` | `always_allowed` |
| Authentication, logout, registration, password-reset, verification, two-factor, passkey, and account-authentication routes | Laravel Fortify / Passkeys | `always_allowed` |
| Personal `settings/*` routes | Settings controllers | `always_allowed` |
| `GET organizations/create` | `OrganizationController@create` | `always_allowed` |
| `POST organizations` | `OrganizationController@store` | `always_allowed` |

Creating a new organization cannot depend on a subscription that does not yet
exist. Its later trial or subscription bootstrap is a separate commercial
concern.

`PUT organizations/{organization}/activate` is not in this group. It changes
the existing organization's administrative `active` state and is classified
under organization administration below.

## Billing acquisition and recovery routes

Billing recovery must remain reachable for an authorized organization member
even when that organization is commercially `read_only`.

Application billing semantics are provider-neutral. Cashier continues to own
Stripe portal, checkout, and lifecycle synchronization, while PayMongo owns
its separate checkout and webhook boundary.

| Route | Method | Current implementation | Commercial policy | Read availability |
|---|---|---|---|---|
| `organizations/{organization}/billing` | GET | `OrganizationBillingController@show` | `always_allowed` relative to commercial write access, still requires billing RBAC | `available_read_only` |
| `organizations/{organization}/billing/checkout` | POST | `OrganizationCheckoutController@store`, currently creates Stripe Checkout through Cashier | `always_allowed` relative to commercial write access, still requires billing RBAC | n/a |
| `organizations/{organization}/billing/portal` | POST | `OrganizationBillingPortalController@store`, currently opens Stripe's hosted portal through Cashier | `always_allowed` relative to commercial write access, still requires billing RBAC | n/a |
| `organizations/{organization}/billing/checkout/success` | GET | `OrganizationCheckoutStatusController@success`, current Stripe Checkout synchronization status | `always_allowed` relative to commercial write access, still requires billing RBAC | `available_read_only` |
| `organizations/{organization}/billing/checkout/cancel` | GET | `OrganizationCheckoutStatusController@cancel` | `always_allowed` relative to commercial write access, still requires billing RBAC | `available_read_only` |
| `stripe/payment/{id}` (`cashier.payment`) | GET | Cashier `PaymentController@show` | Stripe adapter recovery endpoint, outside organization commercial write gating | recovery endpoint |
| `billing/webhooks/stripe` (`cashier.webhook`) | POST | `StripeWebhookController@handleWebhook` | Stripe provider callback, not an organization business mutation route | n/a |
| `billing/webhooks/paymongo` (`billing.webhooks.paymongo`) | POST | `PayMongoWebhookController` | PayMongo provider callback, not an organization business mutation route | n/a |

### Billing-route rules

Organization billing routes remain additive to RBAC. Commercial recovery
access never grants membership or `BillingManage` permission.

These provider-specific callbacks are outside authentication, verification,
organization RBAC, feature-entitlement, and commercial-write enforcement.
They are infrastructure callbacks, not tenant business mutations. CSRF is
excluded only for the exact callback paths above. Stripe remains protected by
Cashier's signature handling and observability; PayMongo verifies the raw
request body against its `Paymongo-Signature` HMAC boundary before parsing or
processing payload data. Neither endpoint guesses a provider from request data.

PayMongo's currently documented subscription webhook contract exposes
`subscription.activated`, `subscription.past_due`, `subscription.unpaid`, and
`subscription.updated`, plus subscription-invoice payment events. It does not
document a terminal subscription-ended/cancelled webhook event or a terminal
subscription status in those examples, so this callback intentionally does not
fabricate an `ended` lifecycle mapping.

Changing `BILLING_PROVIDER` in the future must affect only new subscription
acquisition. Existing provider-owned subscriptions must continue to use their
own provider recovery and lifecycle routes.

## Organization settings, members, and locations

| Route | Method | Controller@action | Mutation domain | Commercial write policy | Read availability | Feature entitlement |
|---|---|---|---|---|---|---|
| `organizations/{organization}/settings` | GET | `OrganizationController@edit` | n/a | n/a | `available_read_only` | core / no feature gate |
| `organizations/{organization}/settings` | PUT | `OrganizationController@update` | org_settings | `blocked_when_read_only` | n/a | n/a |
| `organizations/{organization}/activate` | PUT | `OrganizationController@activate` | org_administration | `admin_gate_only` | n/a | n/a |
| `organizations/{organization}/members` | GET | `OrganizationMemberController@index` | n/a | n/a | `available_read_only` | core / no feature gate |
| `organizations/{organization}/members` | POST | `OrganizationMemberController@store` | org_members | `blocked_when_read_only` | n/a | n/a |
| `organizations/{organization}/locations` | GET | `OrganizationLocationController@index` | n/a | n/a | `available_read_only` | plan entitlement may apply under current feature configuration |
| `organizations/{organization}/locations` | POST | `OrganizationLocationController@store` | org_locations | `blocked_when_read_only` | n/a | plan entitlement may apply |
| `organizations/{organization}/locations/{location}/edit` | GET | `OrganizationLocationController@edit` | n/a | n/a | `available_read_only` | plan entitlement may apply |
| `organizations/{organization}/locations/{location}` | PUT | `OrganizationLocationController@update` | org_locations | `blocked_when_read_only` | n/a | plan entitlement may apply |
| `.../locations/{location}/storage-locations` | GET | `OrganizationStorageLocationController@index` | n/a | n/a | `available_read_only` | plan entitlement may apply |
| `.../locations/{location}/storage-locations` | POST | `OrganizationStorageLocationController@store` | org_locations | `blocked_when_read_only` | n/a | plan entitlement may apply |
| `.../storage-locations/{storageLocation}/edit` | GET | `OrganizationStorageLocationController@edit` | n/a | n/a | `available_read_only` | plan entitlement may apply |
| `.../storage-locations/{storageLocation}` | PUT | `OrganizationStorageLocationController@update` | org_locations | `blocked_when_read_only` | n/a | plan entitlement may apply |

Commercial access and feature entitlements are independent. A route may be
commercially writable but still unavailable because its plan does not grant
the applicable feature or because a quantitative plan limit has been
reached.

## Inventory

| Route | Method | Mutation domain | Commercial write policy | Read availability | Feature entitlement |
|---|---|---|---|---|---|
| `inventory/items`, `inventory/items/create` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `inventory/items` | POST | inventory_catalog | `blocked_when_read_only` | n/a | n/a |
| `inventory/items/{inventoryItem}/edit` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `inventory/items/{inventoryItem}` | PUT | inventory_catalog | `blocked_when_read_only` | n/a | n/a |
| `inventory/items/{inventoryItem}/units` | POST | inventory_catalog | `blocked_when_read_only` | n/a | n/a |
| `inventory/items/{inventoryItem}/units/{inventoryItemUnit}(/edit)` | GET/PUT | inventory_catalog | `blocked_when_read_only` for PUT | `available_read_only` for GET | core / no feature gate for GET |
| `inventory/categories`, `inventory/categories/{inventoryCategory}/edit` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `inventory/categories` | POST | inventory_catalog | `blocked_when_read_only` | n/a | n/a |
| `inventory/categories/{inventoryCategory}` | PUT | inventory_catalog | `blocked_when_read_only` | n/a | n/a |
| `inventory/units`, `inventory/units/{unitOfMeasure}/edit` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `inventory/units` | POST | inventory_catalog | `blocked_when_read_only` | n/a | n/a |
| `inventory/units/{unitOfMeasure}` | PUT | inventory_catalog | `blocked_when_read_only` | n/a | n/a |
| `inventory/adjustments/create` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `inventory/adjustments` | POST | inventory_ledger_write | `blocked_when_read_only` | n/a | n/a |
| `inventory/opening-balances/create` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `inventory/opening-balances` | POST | inventory_ledger_write | `blocked_when_read_only` | n/a | n/a |

`inventory_ledger_write` routes ultimately enter
`RecordStockMovement` through their owning domain actions.

Commercial enforcement belongs before that ledger boundary. Provider-neutral
commercial access must never enter or alter the stock-ledger primitive.
`RecordStockMovement`, `StockMovement`, and `StockBalance` must remain
provider-neutral and billing-unaware.

## Reports and exports

| Route | Method | Domain | Read availability | Feature entitlement |
|---|---|---|---|---|
| `inventory/stock-on-hand` | GET | reports | `available_read_only` | core visibility |
| `inventory/stock-on-hand/export` | GET | reports | `available_read_only` | current `ReportsExport` feature gate |
| `inventory/low-stock` | GET | reports | `available_read_only` | according to configured plan policy |
| `inventory/stock-movements` | GET | reports | `available_read_only` | core visibility |
| `inventory/stock-movements/export` | GET | reports | `available_read_only` | current `ReportsExport` feature gate |
| `inventory/valuation` | GET | reports | `available_read_only` | core visibility |
| `inventory/valuation/export` | GET | reports | `available_read_only` | current `ReportsExport` feature gate |
| `inventory/purchasing-history` | GET | reports | `available_read_only` | core visibility |
| `inventory/purchasing-history/export` | GET | reports | `available_read_only` | current `ReportsExport` feature gate |
| `stock-counts/variance` and export where registered | GET | reports | `available_read_only` | according to current route configuration |
| `stock-transfers/variance` | GET | reports | `available_read_only` | according to current route configuration |
| `waste/export` | GET | reports | `available_read_only` | according to current route configuration |

Commercial `read_only` state must not erase baseline historical or stock
visibility required to understand an organization's inventory.

A feature entitlement is additive to this rule and must be evaluated
independently from payment-provider identity.

## Purchasing

| Route | Method | Domain | Commercial write policy | Read availability | Feature entitlement |
|---|---|---|---|---|---|
| `suppliers`, `suppliers/create`, `suppliers/{supplier}/edit` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `suppliers` | POST | purchasing | `blocked_when_read_only` | n/a | n/a |
| `suppliers/{supplier}` | PUT | purchasing | `blocked_when_read_only` | n/a | n/a |
| `suppliers/{supplier}/items` | POST | purchasing | `blocked_when_read_only` | n/a | n/a |
| `suppliers/{supplier}/items/{supplierItem}(/edit)` | GET/PUT | purchasing | `blocked_when_read_only` for PUT | `available_read_only` for GET | core / no feature gate for GET |
| `suppliers/{supplier}/items/{supplierItem}/prices` | POST | purchasing | `blocked_when_read_only` | n/a | n/a |
| `purchase-orders`, `purchase-orders/create`, `purchase-orders/{purchaseOrder}/edit` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `purchase-orders` | POST | purchasing | `blocked_when_read_only` | n/a | n/a |
| `purchase-orders/{purchaseOrder}` | PUT | purchasing | `blocked_when_read_only` | n/a | n/a |
| `purchase-orders/{purchaseOrder}/approve` | POST | purchasing | `blocked_when_read_only` | n/a | n/a |
| `purchase-orders/{purchaseOrder}/cancel` | POST | purchasing | `blocked_when_read_only` | n/a | n/a |
| `purchase-orders/{purchaseOrder}/receipts/create` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `purchase-orders/{purchaseOrder}/receipts` | POST | receipts | `blocked_when_read_only` | n/a | n/a |
| `goods-receipts`, `goods-receipts/{goodsReceipt}/edit` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `goods-receipts/{goodsReceipt}` | PUT | receipts | `blocked_when_read_only` | n/a | n/a |
| `goods-receipts/{goodsReceipt}/finalize` | POST | receipts | `blocked_when_read_only` | n/a | n/a |
| `goods-receipts/{goodsReceipt}/cancel` | POST | receipts | `blocked_when_read_only` | n/a | n/a |

Finalizing a goods receipt may write stock movements, but billing/provider
checks must remain outside the ledger mutation primitive.

## Counts, waste, and transfers

| Route | Method | Domain | Commercial write policy | Read availability | Feature entitlement |
|---|---|---|---|---|---|
| `stock-counts`, `stock-counts/create`, `stock-counts/{stockCount}/edit` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `stock-counts` | POST | counts | `blocked_when_read_only` | n/a | n/a |
| `stock-counts/{stockCount}` | PUT | counts | `blocked_when_read_only` | n/a | n/a |
| `stock-counts/{stockCount}/submit` | POST | counts | `blocked_when_read_only` | n/a | n/a |
| `stock-counts/{stockCount}/finalize` | POST | counts | `blocked_when_read_only` | n/a | n/a |
| `stock-counts/{stockCount}/cancel` | POST | counts | `blocked_when_read_only` | n/a | n/a |
| `waste` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `waste` | POST | waste | `blocked_when_read_only` | n/a | n/a |
| `waste-reasons` | POST | waste_reasons | `blocked_when_read_only` | n/a | n/a |
| `waste-reasons/{wasteReason}` | PUT | waste_reasons | `blocked_when_read_only` | n/a | n/a |
| `stock-transfers`, `stock-transfers/create`, `stock-transfers/{stockTransfer}/edit` | GET | n/a | n/a | `available_read_only` | core / no feature gate |
| `stock-transfers` | POST | transfers | `blocked_when_read_only` | n/a | n/a |
| `stock-transfers/{stockTransfer}` | PUT | transfers | `blocked_when_read_only` | n/a | n/a |
| `stock-transfers/{stockTransfer}/ship` | POST | transfers | `blocked_when_read_only` | n/a | n/a |
| `stock-transfers/{stockTransfer}/receive` | POST | transfers | `blocked_when_read_only` | n/a | n/a |
| `stock-transfers/{stockTransfer}/cancel` | POST | transfers | `blocked_when_read_only` | n/a | n/a |

Stock-count finalization, waste recording, stock-transfer shipping, and
stock-transfer receiving can create stock movements. Their commercial checks
must remain at the application boundary and never be inserted into
`RecordStockMovement`.

## Recipes

| Route | Method | Domain | Commercial write policy | Read availability | Feature entitlement |
|---|---|---|---|---|---|
| `recipes`, `recipes/{recipe}/edit` | GET | n/a | n/a | `available_read_only` | recipe management according to current plan policy |
| `recipes` | POST | recipes | `blocked_when_read_only` | n/a | according to current plan policy |
| `recipes/{recipe}` | PUT | recipes | `blocked_when_read_only` | n/a | according to current plan policy |
| `recipes/{recipe}/cost` | GET | recipes | n/a | `available_read_only` | recipe-costing feature where configured |

Feature checks must depend on MiseLedger plan entitlements, not payment
provider identity.

## Dashboard

| Route | Method | Domain | Commercial write policy | Read availability | Feature entitlement |
|---|---|---|---|---|---|
| `dashboard` | GET | dashboard | n/a | `available_read_only` | core / no feature gate |

## Master-import entry points

These commands are not exposed as normal authenticated web routes. They run
as explicitly invoked console/administrative operations against an
organization and must not become an accidental HTTP commercial-access bypass
if they are exposed through a web boundary later.

| Command | Domain | Commercial policy |
|---|---|---|
| `inventory:import-master` | master_import | console administrative operation today; any future normal tenant HTTP wrapper must apply appropriate commercial write checks |
| `inventory:import-opening-balances` | master_import / ledger write | same rule, while preserving the stock-ledger boundary |
| `inventory:rebuild-balances` | ledger_maintenance | operational recovery, not a tenant subscription mutation |
| `inventory:reconcile` | ledger_maintenance | operational recovery, not a tenant subscription mutation |

## Exclusions: ledger internals never branch on billing state

The following remain permanently outside payment-provider and commercial
subscription logic:

- `App\Actions\Inventory\RecordStockMovement`.
- `App\Actions\Inventory\ReplayStockLedger`.
- `App\Models\StockBalance`.
- `StockMovement` persistence and stock valuation calculations.
- `inventory:rebuild-balances`.
- `inventory:reconcile`.

Provider identity, `BILLING_PROVIDER`, Stripe statuses, PayMongo statuses,
billing secrets, and commercial lifecycle state must never be added to these
ledger primitives.

All commercial enforcement belongs at an application boundary that invokes
the ledger operation.

## Provider-neutral billing constraints

Billing acquisition and recovery have additional rules:

1. New acquisition uses the future selected and enabled
   `BILLING_PROVIDER`.
2. Existing subscriptions use their own durable provider ownership.
3. Provider selection never grants RBAC permission.
4. Provider selection never changes `Organization.active`.
5. Provider selection never changes plan entitlement semantics.
6. Provider selection never changes stock-ledger state.
7. Provider callbacks are provider-specific infrastructure endpoints and
   require provider-appropriate authentication and replay/idempotency
   controls.
8. Billing secrets remain server-only.

## Coverage confirmation

This map explicitly includes:

```text
public/authentication routes
organization bootstrap
organization billing page
organization checkout
organization billing portal
organization checkout result pages
Cashier Stripe payment recovery
Stripe webhook callback
organization settings and members
locations and storage locations
inventory catalog and ledger-write entry points
reports and exports
suppliers and purchasing
goods receipts
stock counts
waste
stock transfers
recipes
dashboard
master-import and ledger-maintenance commands
```

The provider-neutral contract does not claim that PayMongo routes, adapters,
callbacks, provider persistence, or provider-selection configuration are
already implemented.

## Explicit non-goals of PB-001 and PB-002

These documentation tasks add no:

- Middleware.
- Policies.
- Feature gates.
- Routes.
- Controllers.
- Payment-provider SDKs.
- Provider adapters.
- Environment variables.
- Database migrations.
- Generic provider-ownership persistence.
- PayMongo integration.
- Changes to Stripe/Cashier behavior.
- Changes to `RecordStockMovement`, `ReplayStockLedger`, or `StockBalance`.
- Changes to `Organization.active`.

## Current and future consumers

The route classifications remain authoritative input for commercial
middleware, authorization, entitlement enforcement, billing recovery UX, and
future provider-neutral billing work.

Any later provider implementation must preserve the same organization
isolation, RBAC, commercial recovery availability, plan-entitlement
boundaries, and stock-ledger exclusions documented here.
