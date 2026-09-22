# Platform Owner Console Production Activation Checklist (POC-V10.7)

## Purpose

This is the objective go/no-go gate for exposing the Platform Owner Console
(`/admin/**`) to real customer PII/billing data or allowing live Product
Catalog/CMS mutations. It is mandatory before production activation
(`POC-V10` in the Phase 10 vertical spec).

Activation is operator-controlled and happens through deployment
configuration. There is no in-browser "Enable Production" control, and none
should ever be added.

## How to Use This Checklist

Work through every item below and record actual evidence (a command's real
output, a screenshot, a linked CI run, a reviewed list) next to it. A row
with no evidence is not satisfied. This checklist itself is never modified
to mark an item done; evidence is captured separately (an incident/release
ticket, a deploy record, or equivalent).

## 1. Strong-Factor Enrollment

- [ ] Every row in `platform_admins` belongs to a user for whom
      `User::hasApprovedStrongFactor()` is true (confirmed TOTP or a
      registered passkey). Verify with:

  ```
  php artisan tinker --execute '
  App\Models\PlatformAdmin::with("user")->get()
      ->each(fn ($grant) => print($grant->user->email." strong_factor=".($grant->user->hasApprovedStrongFactor() ? "yes" : "NO")."\n"));
  '
  ```

- [ ] Every platform admin who fails that check has been enrolled or the
      grant has been revoked (`platform-admin:revoke`) before go-live.

## 2. Platform Grants Reviewed

- [ ] `platform_admins` contains only currently-authorized individuals.
      Confirm the full history and current roster:

  ```
  php artisan tinker --execute '
  App\Models\PlatformAdminAuditEvent::orderByDesc("created_at")->limit(20)->get(
      ["target_email", "action", "source", "reason", "created_at"]
  )->each(fn ($e) => print("{$e->created_at} {$e->action->value} {$e->target_email} ({$e->source}): {$e->reason}\n"));
  '
  ```

- [ ] Every grant has a business justification recorded in
      `platform_admin_audit_events.reason`.

## 3. Recent-Authentication Gates Active

- [ ] `routes/platform.php` confirms `password.confirm` and
      `throttle:platform-mutation` on every consequential mutation route
      (product-catalog draft create/update/publish, and any future
      CMS/security-consequence mutation added under this same contract):

  ```
  php artisan route:list --path=admin --method=POST --method=PUT
  ```

  Verify each row's middleware list includes `password.confirm`.

## 4. HTTPS / Session / Debug Configuration

- [ ] Run the scriptable fail-closed gate against the production
      environment configuration:

  ```
  php artisan platform:verify-security-config
  ```

  Exit code `0` is required. A non-zero exit blocks activation; the command
  never prints the actual configured secret/URL value, only which setting
  failed.

- [ ] `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`,
      `SESSION_SAME_SITE` is `lax` or `strict`.
- [ ] `APP_URL` uses `https://`.
- [ ] Trusted proxy/HTTPS termination matches the deployment described in
      `docs/deployment.md` (Coolify terminates TLS; the app trusts that
      proxy boundary only, per `bootstrap/app.php`).

## 5. Backups and Restore Readiness

- [ ] The Platform Health page's backup evidence (`/admin/health`,
      `CheckBackupHealth`) shows a recent successful backup with a checked
      timestamp inside the expected window.
- [ ] A restore drill has been performed and its evidence (date, operator,
      restored target) is recorded per `docs/deployment.md`'s backup
      boundary.

## 6. Observability

- [ ] Pulse and Horizon dashboards (`/admin/observability`) are reachable
      and gated by the same platform-admin authority
      (`HorizonServiceProvider::gate()`, `config/pulse.php`).
- [ ] The Platform Health page (`/admin/health`) reports database,
      migration, slow-query, table-growth, backup, Redis, and queue signals
      with no unexplained failure state.
- [ ] The Platform Alerts console (`/admin/alerts`) has no unresolved
      `critical` alert at activation time.

## 7. Platform Audit Writes Verified

- [ ] A dry-run draft/publish cycle against a non-production plan (or a
      staging environment with production-equivalent configuration)
      produces rows in `platform_audit_events` with a correct
      `actor_user_id`, `action`, `subject_type`/`subject_id`, and
      `occurred_at`:

  ```
  php artisan tinker --execute '
  App\Models\PlatformAuditEvent::latest("id")->limit(5)->get()
      ->each(fn ($e) => print("{$e->occurred_at} {$e->action->value} {$e->subject_type}#{$e->subject_id} by user#{$e->actor_user_id}\n"));
  '
  ```

## 8. Legal Pages

- [ ] Any customer-facing legal page changes are published only if they
      have been separately approved outside this engineering checklist. Do
      not treat platform security hardening as approval to publish legal
      content.

## 9. Migrations and CI

- [ ] `php artisan migrate --pretend` (or equivalent staging dry-run) shows
      no unexpected destructive operation.
- [ ] Current migrations are applied on the target environment.
- [ ] Targeted platform security suite is green:

  ```
  php artisan test --compact tests/Feature/Platform
  ```

- [ ] Full CI (`composer ci:check` / repository-defined full gate,
      including tenant auth, billing, and stock-ledger suites) is green on
      the commit being deployed.

## 10. No Unresolved P0 Security Findings

- [ ] The most recent security review (`/code-review` or an equivalent
      audit) for the Platform Console has no open P0/critical finding.

## Rollback

Rollback never touches customer tenant data. It only affects the platform
authority layer:

1. **Disable a specific admin immediately:**
   `php artisan platform-admin:revoke {email} --reason="<why>"`. This takes
   effect on the admin's next request (`EnsurePlatformAdmin` re-resolves the
   grant every request; no session invalidation or tenant-role change is
   required).
2. **Disable the whole console without a code change:** redeploy with the
   platform routes' web server path blocked at the edge/proxy, or revoke
   every row in `platform_admins` via the command above. Either path leaves
   `organizations`, `stock_movements`, `stock_balances`, and all other
   tenant business data untouched.
3. **Roll back a bad catalog publish:** publishing supersedes the prior
   current `BillingPlanVersion` rather than deleting it; existing
   subscribers stay pinned to their already-resolved plan version and are
   never repriced by a publish or its rollback. To roll back, publish a new
   corrected version; do not attempt to un-supersede a row directly.
4. **Roll back a code deployment:** follow the standard image-rollback path
   in `docs/deployment.md`. No platform-specific migration rollback is
   required unless the deployed commit itself changed
   `platform_admins`/`platform_admin_audit_events`/`platform_audit_events`
   schema, in which case the standard reversible-migration rollback applies.

Go-live requires every checkbox above to carry real evidence and no
unresolved P0 finding. This document is never the mechanism that grants
production access; it is the record that access was safe to grant.
