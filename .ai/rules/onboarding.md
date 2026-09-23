---
paths:
  - 'app/Support/Onboarding/**'
  - 'app/Actions/Onboarding/**'
  - 'app/Http/Controllers/OnboardingController.php'
  - 'app/Http/Middleware/EnsureOrganizationSetupComplete.php'
  - 'routes/web.php'
  - 'routes/mobile.php'
---

# Onboarding

## Setup readiness is derived, and stock-workflow mutations sit behind `setup.complete`
`OrganizationSetupReadiness` derives readiness from current records (active location, active item, every active item with a stock movement or `opening_stock_waived_at`). `onboarding_completed_at` only latches first completion so items created after launch do not re-block; never treat it as the sole readiness source. New operational stock-mutation routes (purchasing, receiving, counts, transfers, waste, adjustments, and their mobile equivalents) must carry the `setup.complete` middleware; setup's own mutations (locations, units, items, opening balances, suppliers, members) must not. Setup opening stock uses the per-item idempotency key `opening_balance:onboarding:{item}` through `RecordOpeningBalance`.
