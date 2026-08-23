# Mutation and Feature Entitlement Map (P0-005)

This is the authoritative audit of every currently registered business route
and console import entry point, classified for the future enforcement work:
route middleware (P4), backend policy checks and feature gates (P5, incl.
P5-003), and UI entitlement presentation (P6). **No enforcement, middleware,
policy, or feature-gate code is added by this task.** It only records the
classification those later phases must implement against.

It builds directly on:

- [`docs/subscription-access-matrix.md`](subscription-access-matrix.md)
  (P0-003) — the `AccessMode` values (`full`, `full_with_warning`,
  `read_only`) referenced below.
- [`docs/subscription-plan-catalog.md`](subscription-plan-catalog.md) /
  [`config/subscription.php`](../config/subscription.php) (P0-004) — the
  `features` entitlement keys referenced below (all currently unresolved;
  no plan codes exist yet, so no route is bound to a concrete plan feature
  key in this phase).

## Classification legend

- **Mutation domain** — the operational area a write route belongs to, used
  to group future write-blocking behavior.
- **Commercial write policy** — how the route must behave once `AccessMode`
  enforcement (P4/P5) exists:
    - `blocked_when_read_only` — normal business write; blocked outside
      `full` / `full_with_warning`.
    - `always_allowed` — independent of `AccessMode` entirely (auth,
      organization creation, billing recovery).
    - `admin_gate_only` — continues to be governed solely by the
      administrative axis (`Organization.active` / RBAC), never by commercial
      state (e.g. deactivating a location is an operational/administrative
      action, not a commercial one — no route currently does this, see
      Exceptions).
- **Read availability** — whether a GET/HEAD route must stay reachable in
  `read_only` mode. All business reads in this map are
  `available_read_only`; none are currently degraded further.
- **Feature entitlement** — whether the route is a candidate for a future
  per-plan `features` gate (P5-003) versus core functionality available on
  every plan. No route is bound to a concrete plan/feature key yet, since
  `config('subscription.plans')` has no approved plan codes (P0-004).

## Authentication and organization-creation routes (independent of existing-organization subscription)

These routes precede or fall outside resolution of an existing
organization's commercial state (`ResolveActiveOrganization` /
`AccessMode`). They must never be gated by any organization's subscription
status.

| Route(s)                                                                                                                                                                                                                                                                                          | Source                                                                         | Commercial write policy                                                                                                                                       |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /` (`home`)                                                                                                                                                                                                                                                                                  | closure in `routes/web.php`                                                    | `always_allowed` — public marketing/welcome page, reachable pre-authentication and independent of any organization's commercial state                         |
| `GET/POST login`, `POST logout`, `GET/POST register`, `GET/POST forgot-password`, `GET/POST reset-password/{token}`, `GET email/verify*`, `GET/POST two-factor-challenge`, `GET/POST user/confirm-password`, `user/two-factor-*`, `user/passkeys*`, `passkeys/*`, `.well-known/passkey-endpoints` | Laravel Fortify / Passkeys (vendor)                                            | `always_allowed`                                                                                                                                              |
| `ANY settings` (redirect to `settings/profile`), `settings/profile` (GET/PATCH/DELETE), `settings/security` (GET/PUT `settings/password`), `settings/appearance`                                                                                                                                  | `Route::redirect`, `Settings\ProfileController`, `Settings\SecurityController` | `always_allowed` — user-account navigation/state, not organization-scoped commercial state                                                                    |
| `GET organizations/create`, `POST organizations` (`organizations.store`)                                                                                                                                                                                                                          | `OrganizationController::create/store`                                         | `always_allowed` — creating a new organization cannot depend on a subscription that does not exist yet; trial/subscription bootstrap happens _after_ creation |

Feature entitlement: all reads in this group (login/register/password-reset
screens, two-factor/passkey management, personal `settings/*` account
pages, and `organizations/create`) are **core / no feature gate** — they
are user-account or pre-organization navigation, never a sellable
organization-level feature, so they are never a P5-003 candidate.

`PUT organizations/{organization}/activate` (`OrganizationController::activate`)
is **not** in this group: it mutates an _existing_ organization's
administrative `active` flag (TASK-002) and is classified under
Organization administration below.

## Future billing and subscription-recovery routes

No billing/Cashier routes exist yet (P1-004 scope). This map records the
policy they must follow once added:

- Checkout session creation, billing portal redirect, and any
  payment-retry/subscription-recovery route must be classified
  `always_allowed` and reachable for an organization in `read_only` mode
  (per `subscription-access-matrix.md` rule 4). They must **not** be gated
  behind `blocked_when_read_only`, since that would make recovery
  impossible.
- These routes are additive to RBAC: they still require organization
  membership and an authorized role, per architectural invariant 8. Billing
  authorization never substitutes for RBAC.

## Organization settings, members, and locations

| Route                                                    | Method | Controller@action                              | Mutation domain    | Commercial write policy                                          | Read availability     |
| -------------------------------------------------------- | ------ | ---------------------------------------------- | ------------------ | ---------------------------------------------------------------- | --------------------- |
| Route                                                    | Method | Controller@action                              | Mutation domain    | Commercial write policy                                          | Read availability     | Feature entitlement    |
| ---                                                      | ---    | ---                                            | ---                | ---                                                              | ---                   | ---                    |
| `organizations/{organization}/settings`                  | GET    | `OrganizationController@edit`                  | —                  | —                                                                | `available_read_only` | core / no feature gate |
| `organizations/{organization}/settings`                  | PUT    | `OrganizationController@update`                | org_settings       | `blocked_when_read_only`                                         | —                     | —                      |
| `organizations/{organization}/activate`                  | PUT    | `OrganizationController@activate`              | org_administration | `admin_gate_only` (TASK-002 administrative flag, not commercial) | —                     | —                      |
| `organizations/{organization}/members`                   | GET    | `OrganizationMemberController@index`           | —                  | —                                                                | `available_read_only` | core / no feature gate |
| `organizations/{organization}/members`                   | POST   | `OrganizationMemberController@store`           | org_members        | `blocked_when_read_only`                                         | —                     | —                      |
| `organizations/{organization}/locations`                 | GET    | `OrganizationLocationController@index`         | —                  | —                                                                | `available_read_only` | core / no feature gate |
| `organizations/{organization}/locations`                 | POST   | `OrganizationLocationController@store`         | org_locations      | `blocked_when_read_only`                                         | —                     | —                      |
| `organizations/{organization}/locations/{location}/edit` | GET    | `OrganizationLocationController@edit`          | —                  | —                                                                | `available_read_only` | core / no feature gate |
| `organizations/{organization}/locations/{location}`      | PUT    | `OrganizationLocationController@update`        | org_locations      | `blocked_when_read_only`                                         | —                     | —                      |
| `.../locations/{location}/storage-locations`             | GET    | `OrganizationStorageLocationController@index`  | —                  | —                                                                | `available_read_only` | core / no feature gate |
| `.../locations/{location}/storage-locations`             | POST   | `OrganizationStorageLocationController@store`  | org_locations      | `blocked_when_read_only`                                         | —                     | —                      |
| `.../storage-locations/{storageLocation}/edit`           | GET    | `OrganizationStorageLocationController@edit`   | —                  | —                                                                | `available_read_only` | core / no feature gate |
| `.../storage-locations/{storageLocation}`                | PUT    | `OrganizationStorageLocationController@update` | org_locations      | `blocked_when_read_only`                                         | —                     | —                      |

Feature entitlement: none of these reads are candidates for plan-level
feature gating (org settings/members/locations are core tenant
administration, not a sellable feature); a future seat-count or
location-count _limit_ (see `subscription.plans.*.limits` in P0-004) may
still constrain `store` here, but that is a quantitative limit check, not a
route-level feature flag.

## Inventory (items, categories, units, adjustments, opening balances)

| Route                                                                   | Method  | Mutation domain        | Commercial write policy        | Read availability           | Feature entitlement          |
| ----------------------------------------------------------------------- | ------- | ---------------------- | ------------------------------ | --------------------------- | ---------------------------- |
| `inventory/items`, `inventory/items/create`                             | GET     | —                      | —                              | `available_read_only`       | core / no feature gate       |
| `inventory/items`                                                       | POST    | inventory_catalog      | `blocked_when_read_only`       | —                           | —                            |
| `inventory/items/{inventoryItem}/edit`                                  | GET     | —                      | —                              | `available_read_only`       | core / no feature gate       |
| `inventory/items/{inventoryItem}`                                       | PUT     | inventory_catalog      | `blocked_when_read_only`       | —                           | —                            |
| `inventory/items/{inventoryItem}/units`                                 | POST    | inventory_catalog      | `blocked_when_read_only`       | —                           | —                            |
| `inventory/items/{inventoryItem}/units/{inventoryItemUnit}(/edit)`      | GET/PUT | inventory_catalog      | `blocked_when_read_only` (PUT) | `available_read_only` (GET) | core / no feature gate (GET) |
| `inventory/categories`, `inventory/categories/{inventoryCategory}/edit` | GET     | —                      | —                              | `available_read_only`       | core / no feature gate       |
| `inventory/categories`                                                  | POST    | inventory_catalog      | `blocked_when_read_only`       | —                           | —                            |
| `inventory/categories/{inventoryCategory}`                              | PUT     | inventory_catalog      | `blocked_when_read_only`       | —                           | —                            |
| `inventory/units`, `inventory/units/{unitOfMeasure}/edit`               | GET     | —                      | —                              | `available_read_only`       | core / no feature gate       |
| `inventory/units`                                                       | POST    | inventory_catalog      | `blocked_when_read_only`       | —                           | —                            |
| `inventory/units/{unitOfMeasure}`                                       | PUT     | inventory_catalog      | `blocked_when_read_only`       | —                           | —                            |
| `inventory/adjustments/create`                                          | GET     | —                      | —                              | `available_read_only`       | core / no feature gate       |
| `inventory/adjustments`                                                 | POST    | inventory_ledger_write | `blocked_when_read_only`       | —                           | —                            |
| `inventory/opening-balances/create`                                     | GET     | —                      | —                              | `available_read_only`       | core / no feature gate       |
| `inventory/opening-balances`                                            | POST    | inventory_ledger_write | `blocked_when_read_only`       | —                           | —                            |

`inventory_ledger_write` routes ultimately call `RecordStockMovement`
through their actions (`AdjustInventory`, `RecordOpeningBalance`); the
future write block belongs strictly in the controller/policy/middleware
layer that guards these routes, never inside `RecordStockMovement` itself
(architectural invariant 11 and access-matrix rule 5 — see Exclusions).

Feature entitlement: catalog reads (items/categories/units/adjustments/
opening-balances) are core inventory data required to operate the
business, not a sellable feature — all are `core / no feature gate`.

## Reports and exports (read-only, feature-entitlement candidates)

| Route                                     | Method | Domain  | Read availability     | Feature entitlement candidate                      |
| ----------------------------------------- | ------ | ------- | --------------------- | -------------------------------------------------- |
| `inventory/stock-on-hand`(`/export`)      | GET    | reports | `available_read_only` | yes — reporting/export can be a plan-gated feature |
| `inventory/low-stock`                     | GET    | reports | `available_read_only` | yes                                                |
| `inventory/stock-movements`(`/export`)    | GET    | reports | `available_read_only` | yes                                                |
| `inventory/valuation`(`/export`)          | GET    | reports | `available_read_only` | yes                                                |
| `inventory/purchasing-history`(`/export`) | GET    | reports | `available_read_only` | yes                                                |
| `stock-counts/variance`(`/export`)        | GET    | reports | `available_read_only` | yes                                                |
| `stock-transfers/variance`                | GET    | reports | `available_read_only` | yes                                                |
| `waste/export`                            | GET    | reports | `available_read_only` | yes                                                |

All report/export reads must remain reachable under `read_only`
(read-only-safe per acceptance criteria); any future `features` gate on a
specific export is an additive plan entitlement, never a commercial
write-block, and must not remove baseline visibility of stock-on-hand data
needed to operate the business.

## Purchasing (suppliers, purchase orders, goods receipts)

| Route                                                                               | Method  | Domain                                              | Commercial write policy        | Read availability           | Feature entitlement          |
| ----------------------------------------------------------------------------------- | ------- | --------------------------------------------------- | ------------------------------ | --------------------------- | ---------------------------- |
| `suppliers`, `suppliers/create`, `suppliers/{supplier}/edit`                        | GET     | —                                                   | —                              | `available_read_only`       | core / no feature gate       |
| `suppliers`                                                                         | POST    | purchasing                                          | `blocked_when_read_only`       | —                           | —                            |
| `suppliers/{supplier}`                                                              | PUT     | purchasing                                          | `blocked_when_read_only`       | —                           | —                            |
| `suppliers/{supplier}/items`                                                        | POST    | purchasing                                          | `blocked_when_read_only`       | —                           | —                            |
| `suppliers/{supplier}/items/{supplierItem}(/edit)`                                  | GET/PUT | purchasing                                          | `blocked_when_read_only` (PUT) | `available_read_only` (GET) | core / no feature gate (GET) |
| `suppliers/{supplier}/items/{supplierItem}/prices`                                  | POST    | purchasing                                          | `blocked_when_read_only`       | —                           | —                            |
| `purchase-orders`, `purchase-orders/create`, `purchase-orders/{purchaseOrder}/edit` | GET     | —                                                   | —                              | `available_read_only`       | core / no feature gate       |
| `purchase-orders`                                                                   | POST    | purchasing                                          | `blocked_when_read_only`       | —                           | —                            |
| `purchase-orders/{purchaseOrder}`                                                   | PUT     | purchasing                                          | `blocked_when_read_only`       | —                           | —                            |
| `purchase-orders/{purchaseOrder}/approve`                                           | POST    | purchasing                                          | `blocked_when_read_only`       | —                           | —                            |
| `purchase-orders/{purchaseOrder}/cancel`                                            | POST    | purchasing                                          | `blocked_when_read_only`       | —                           | —                            |
| `purchase-orders/{purchaseOrder}/receipts/create`                                   | GET     | —                                                   | —                              | `available_read_only`       | core / no feature gate       |
| `purchase-orders/{purchaseOrder}/receipts`                                          | POST    | receipts (creates goods receipt)                    | `blocked_when_read_only`       | —                           | —                            |
| `goods-receipts`, `goods-receipts/{goodsReceipt}/edit`                              | GET     | —                                                   | —                              | `available_read_only`       | core / no feature gate       |
| `goods-receipts/{goodsReceipt}`                                                     | PUT     | receipts                                            | `blocked_when_read_only`       | —                           | —                            |
| `goods-receipts/{goodsReceipt}/finalize`                                            | POST    | receipts (writes stock movements via ledger action) | `blocked_when_read_only`       | —                           | —                            |
| `goods-receipts/{goodsReceipt}/cancel`                                              | POST    | receipts                                            | `blocked_when_read_only`       | —                           | —                            |

Feature entitlement: all purchasing reads (suppliers, supplier
items/prices, purchase orders, goods receipts) are core operational data
required to run purchasing — `core / no feature gate`.

## Counts, waste, and transfers

| Route                                                                               | Method | Domain                             | Commercial write policy  | Read availability     | Feature entitlement    |
| ----------------------------------------------------------------------------------- | ------ | ---------------------------------- | ------------------------ | --------------------- | ---------------------- |
| `stock-counts`, `stock-counts/create`, `stock-counts/{stockCount}/edit`             | GET    | —                                  | —                        | `available_read_only` | core / no feature gate |
| `stock-counts`                                                                      | POST   | counts                             | `blocked_when_read_only` | —                     | —                      |
| `stock-counts/{stockCount}`                                                         | PUT    | counts                             | `blocked_when_read_only` | —                     | —                      |
| `stock-counts/{stockCount}/submit`                                                  | POST   | counts                             | `blocked_when_read_only` | —                     | —                      |
| `stock-counts/{stockCount}/finalize`                                                | POST   | counts (writes stock movements)    | `blocked_when_read_only` | —                     | —                      |
| `stock-counts/{stockCount}/cancel`                                                  | POST   | counts                             | `blocked_when_read_only` | —                     | —                      |
| `waste`                                                                             | GET    | —                                  | —                        | `available_read_only` | core / no feature gate |
| `waste`                                                                             | POST   | waste (writes stock movements)     | `blocked_when_read_only` | —                     | —                      |
| `waste-reasons`                                                                     | POST   | waste_reasons                      | `blocked_when_read_only` | —                     | —                      |
| `waste-reasons/{wasteReason}`                                                       | PUT    | waste_reasons                      | `blocked_when_read_only` | —                     | —                      |
| `stock-transfers`, `stock-transfers/create`, `stock-transfers/{stockTransfer}/edit` | GET    | —                                  | —                        | `available_read_only` | core / no feature gate |
| `stock-transfers`                                                                   | POST   | transfers                          | `blocked_when_read_only` | —                     | —                      |
| `stock-transfers/{stockTransfer}`                                                   | PUT    | transfers                          | `blocked_when_read_only` | —                     | —                      |
| `stock-transfers/{stockTransfer}/ship`                                              | POST   | transfers (writes stock movements) | `blocked_when_read_only` | —                     | —                      |
| `stock-transfers/{stockTransfer}/receive`                                           | POST   | transfers (writes stock movements) | `blocked_when_read_only` | —                     | —                      |
| `stock-transfers/{stockTransfer}/cancel`                                            | POST   | transfers                          | `blocked_when_read_only` | —                     | —                      |

Feature entitlement: stock-count, waste, and transfer reads are core
operational workflows, not sellable features — `core / no feature gate`.
Their `variance`/`export` reporting views are classified separately under
Reports and exports above (feature-entitlement candidates).

## Recipes

| Route                              | Method | Domain  | Commercial write policy  | Read availability     | Feature entitlement candidate                                                                                                                 |
| ---------------------------------- | ------ | ------- | ------------------------ | --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `recipes`, `recipes/{recipe}/edit` | GET    | —       | —                        | `available_read_only` | core / no feature gate — recipe management (name/ingredients/instructions) is core operational data, distinct from the recipe-cost read below |
| `recipes`                          | POST   | recipes | `blocked_when_read_only` | —                     | recipe costing may be a plan-gated feature (unresolved, P0-004)                                                                               |
| `recipes/{recipe}`                 | PUT    | recipes | `blocked_when_read_only` | —                     | same                                                                                                                                          |
| `recipes/{recipe}/cost`            | GET    | recipes | —                        | `available_read_only` | yes — candidate for a `features` gate once a plan approves it                                                                                 |

## Dashboard

| Route       | Method | Domain    | Commercial write policy | Read availability     | Feature entitlement    |
| ----------- | ------ | --------- | ----------------------- | --------------------- | ---------------------- |
| `dashboard` | GET    | dashboard | —                       | `available_read_only` | core / no feature gate |

## Master-import entry points (console commands, not web routes)

These are not exposed by `routes/web.php`; they run as authorized
console/administrative operations against a specific organization and are
**not currently reachable through subscription-gated HTTP paths**. They
must still be recorded so P5 enforcement does not create a bypass:

| Command                                                                | Domain                                                            | Commercial write policy                                                                                                                                                                   |
| ---------------------------------------------------------------------- | ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `inventory:import-master` (`InventoryImportMaster`)                    | master_import                                                     | `always_allowed` at the console layer today (no HTTP route exists); if a future HTTP entry point wraps this action, that route must be `blocked_when_read_only` like other catalog writes |
| `inventory:import-opening-balances` (`InventoryImportOpeningBalances`) | master_import (writes stock movements via `RecordOpeningBalance`) | same as above                                                                                                                                                                             |
| `inventory:rebuild-balances` (`InventoryRebuildBalances`)              | ledger_maintenance                                                | excluded — operational/administrative ledger recovery, not a commercial write (see Exclusions)                                                                                            |
| `inventory:reconcile` (`InventoryReconcile`)                           | ledger_maintenance                                                | excluded — same as above                                                                                                                                                                  |

## Exclusions: ledger internals never branch on billing state

Per architectural invariants 10–12 and access-matrix rule 5, the following
are permanently outside subscription/`AccessMode` logic and must never
receive a commercial check:

- `App\Actions\Inventory\RecordStockMovement` — the sole ledger-append
  action; all inventory-affecting controllers already funnel through it or
  an action that wraps it (`AdjustInventory`, `RecordOpeningBalance`,
  `RecordWaste`, `ReceiveStockTransfer`, `ShipStockTransfer`, goods-receipt
  finalize, stock-count finalize).
- `App\Actions\Inventory\ReplayStockLedger` — deterministic ledger replay
  used for reconciliation/rebuild.
- `App\Models\StockBalance` — the balance projection.
- `inventory:rebuild-balances` / `inventory:reconcile` console commands —
  operational recovery tooling, not a tenant-facing commercial write.

Future `blocked_when_read_only` enforcement belongs exclusively in the
controller/policy/middleware layer that calls into these actions, never
inside the actions themselves.

## Coverage confirmation

This map covers every registered route in `routes/web.php` and
`routes/settings.php` (confirmed against `php artisan route:list
--except-vendor`) and every acceptance-criteria domain: the public home
route, authentication, organization settings/members/locations, inventory
(catalog + ledger-write + reports), purchasing
(suppliers/purchase-orders/goods-receipts), counts, waste, transfers,
recipes, reports/exports, and master-import entry points.
Authentication/organization-creation and future billing-recovery routes are
explicitly marked `always_allowed`, independent of any existing
organization's `AccessMode`.

## Explicit non-goals of this task

- No middleware, policy, or feature-gate code added.
- No changes to `RecordStockMovement`, `ReplayStockLedger`, or
  `StockBalance`.
- No plan/feature key bound to a route (plan codes remain unresolved per
  P0-004).
- No changes to `Organization.active` semantics (TASK-002 remains
  authoritative for the administrative axis).

## Downstream consumers (future phases, not implemented here)

- **P4** — HTTP middleware applying `blocked_when_read_only` to every route
  marked as such above, while leaving `always_allowed` routes untouched.
- **P5** — backend/policy checks enforcing the same classification
  server-side (defense in depth) plus P5-003 feature gates using
  "Feature entitlement candidate" routes once plan `features` are approved.
- **P6** — UI banners/disabled states reflecting `AccessMode` for
  `blocked_when_read_only` routes (presentation only).
