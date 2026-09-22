---
paths:
  - 'app/Http/Controllers/Platform/PlatformContentController.php'
  - 'app/Http/Controllers/PublicContentController.php'
  - 'app/Models/ContentPage.php'
  - 'app/Models/ContentRevision.php'
---

# Versioned Marketing/Legal CMS (POC-V6)

## Scope is `marketing`/`legal` only
Release Notes and User Guide are separate, code-owned/static modules. Never
add them to `ContentKind`, `content_pages`, or the platform Content nav.

## Publish pointer, never rewrite
`content_pages.published_revision_id` is the only pointer public routes may
render. `ContentRevision::booted()` blocks mutating `title`/`body_markdown`/
`metadata` once `published_at` is set, and blocks all deletes. Rollback
(`PlatformContentController::restore`) always creates a new draft revision
copied from historical content; it never repoints `published_revision_id`
directly and never auto-publishes.

## One draft per page
Both `store` (implicitly, new page) and `restore` reject creating a second
unpublished revision for the same page — mirrors the one-draft-per-plan rule
in `PlatformProductCatalogController`.

## Legal publication stays gated
`TERMS_OF_SERVICE.md`/`PRIVACY_POLICY.md` import via
`content:bootstrap-legal-drafts --apply` (idempotent, dry-run by default)
always lands as an unpublished draft. Never infer legal approval from
repository presence; real publication requires the business/legal review
gate in `COMMERCIAL_POLICY_DECISIONS.md`.

## Markdown rendering
`resources/js/components/content-markdown.tsx` is the single renderer for
both draft preview and public pages. It intentionally omits `rehype-raw` and
never uses `dangerouslySetInnerHTML`, so literal HTML in Markdown renders as
inert text. Reuse it rather than adding a second renderer.
