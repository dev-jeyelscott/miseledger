# Subscription Access Matrix (P0-003)

This is the single authoritative contract mapping trial and Cashier/Stripe
subscription lifecycle state to application **commercial access**. It is the
execution contract for the future access resolver (P2), middleware (P4),
backend/policy checks (P5), and UI (P6). No provider integration, middleware,
policy, or UI code is implemented in this phase.

## Two independent axes

Commercial access is layered **on top of**, and is fully independent from,
administrative access (TASK-002):

1. **Administrative axis** — `Organization.active`. A manager/operator
   enable-disable flag. If `false`, the organization is not resolvable at
   all (see `ResolveActiveOrganization` / `OrganizationPolicy`), regardless
   of commercial state. This document does not touch, encode, or override
   that gate.
2. **Commercial axis** — this document. Applies only to organizations that
   already pass the administrative gate. It never sets or reads
   `Organization.active`.

## Access modes

| Mode | Write access | Notes |
|---|---|---|
| `full` | enabled | no billing warning |
| `full_with_warning` | enabled | billing warning surfaced |
| `read_only` | disabled | billing recovery routes remain reachable |

`AccessMode` is derived at request time from Cashier/Stripe subscription
state (and, pre-subscription, generic trial state). It is **not** a
persisted column or a competing subscription state machine; Cashier/Stripe
remains the sole source of truth for subscription status.

## Matrix

| Lifecycle state | Source of truth | Access mode | Billing recovery reachable |
|---|---|---|---|
| Generic trial (no Cashier subscription yet, within trial window) | app-level trial fields | `full` | n/a |
| Stripe trialing (`onTrial()`) | Cashier | `full` | n/a |
| active (`valid()` / active subscription) | Cashier | `full` | n/a |
| past_due (`pastDue()`) | Cashier | `full_with_warning` | yes |
| scheduled cancellation / grace period (`onGracePeriod()`, canceled with `ends_at` in the future) | Cashier | `full_with_warning` | yes |
| ended (trial expired with no subscription, or canceled subscription past `ends_at`) | app trial expiry / Cashier | `read_only` | yes |
| unpaid (`stripe_status === 'unpaid'`) | Cashier | `read_only` | yes |

## Rules

1. Cashier/Stripe subscription status is read-only input to this mapping;
   nothing in this contract writes subscription state.
2. `past_due` never drops to `read_only`: it stays `full_with_warning` so
   day-to-day operations continue while billing is recovered.
3. A canceled subscription stays `full_with_warning` for the remainder of
   its paid term (`onGracePeriod()`), and only becomes `read_only` once
   `ends_at` passes without reactivation.
4. `ended` and `unpaid` are `read_only`, not inaccessible: billing recovery
   routes (checkout / billing portal) must stay reachable so the
   organization can restore `full` access without losing data.
5. This matrix carries no inventory-specific exceptions. Ledger actions
   (`RecordStockMovement`, `StockBalance`, and related actions) must never
   branch on billing state directly; any `read_only` enforcement belongs in
   the future access resolver/middleware/policy layer that consumes
   `AccessMode`, not in ledger code.
6. The administrative gate (`Organization.active`) and this commercial
   matrix are evaluated independently. An inactive organization is
   unresolvable regardless of `AccessMode`; an active organization in
   `read_only` mode remains resolvable and readable.

## Downstream consumers (future phases, not implemented here)

- **P2** — access resolver that computes `AccessMode` from an
  `Organization`'s Cashier subscription plus generic trial fields.
- **P4** — HTTP middleware that blocks write requests when `AccessMode` is
  `read_only`, while leaving billing-recovery routes unaffected.
- **P5** — backend/policy checks gating specific write actions by
  `AccessMode`.
- **P6** — UI banners/read-only indicators reflecting `AccessMode`
  (presentation only; server-side enforcement remains authoritative).

## Future state-transition test matrix (to be implemented by later tasks)

- generic trial → Stripe trialing → active
- generic trial expires with no subscription → `ended`
- active → `past_due` (failed charge) → active (payment recovered)
- active → `past_due` → `unpaid` (retries exhausted) → `read_only`
- active → canceled (`onGracePeriod()`) → reactivated before `ends_at` → `full`
- active → canceled (`onGracePeriod()`) → `ends_at` passes unresolved → `ended` / `read_only`
- `unpaid` → payment resolved → `active` / `full`
- `read_only`: billing-recovery route reachable, non-recovery write route blocked
- administrative `active = false` combined with any commercial state →
  organization unresolvable (confirms axis independence from TASK-002)

## Explicit non-goals of this task

- No Cashier/Stripe integration, webhook handling, or subscription code.
- No middleware, policy, or UI changes.
- No changes to `Organization.active` or ledger (`StockMovement` /
  `StockBalance`) behavior.
