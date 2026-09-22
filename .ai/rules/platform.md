---
paths:
  - 'app/Http/Controllers/Platform/**'
  - 'routes/platform.php'
---

# Platform Owner Console

## Consequential mutations require three layers, not just `platform.admin`

Any route that mutates state with customer/commercial/security consequence
(product catalog draft/update/publish today; any future CMS or
platform-grant-adjacent mutation) must have all three, on top of the
existing `auth`, `verified`, `platform.admin` group:

1. `password.confirm` (Fortify recent-auth; session `auth.password_confirmed_at`,
   window is the app-wide `AUTH_PASSWORD_TIMEOUT`/`password_timeout` config —
   do not invent a separate confirmation timestamp).
2. `throttle:platform-mutation` (registered in
   `AppServiceProvider::configurePlatformRateLimiting()`).
3. A call to `App\Actions\Platform\RecordPlatformAuditEvent` after the
   mutation succeeds, writing to `platform_audit_events`
   (`App\Models\PlatformAuditEvent`, immutable via model `booted()` guards).

Read-only pages (Users/Organizations/Billing/Health/Alerts/Observability)
only need the base `platform.admin` gate; do not add recent-auth or the
mutation rate limiter to GET routes.

## Three separate audit trails — do not merge them

- `AuditLog` — tenant/organization-scoped business events.
- `PlatformAdminAuditEvent` — platform grant/revoke history only
  (`platform-admin:grant`/`platform-admin:revoke`).
- `PlatformAuditEvent` — everything else consequential and
  non-organization-scoped (catalog/CMS/security transitions).

## Production security config

`php artisan platform:verify-security-config` is the fail-closed deploy/CI
gate for session/cookie/debug/URL settings (POC-V10.4/V10.7). It is a
standalone command, not hooked into `AppServiceProvider::boot()`, because
existing billing-config tests fake `app()->isProduction()` without a fully
production-safe session/debug/URL config and would otherwise break.
