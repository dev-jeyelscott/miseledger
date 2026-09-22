<?php

namespace App\Enums;

/**
 * Recognized consequential platform-owner transitions eligible for dedicated
 * platform audit evidence. Extend this list only for genuinely consequential
 * privileged transitions; read-only browsing is intentionally not audited.
 */
enum PlatformAuditAction: string
{
    case CatalogVersionDrafted = 'catalog.version.drafted';
    case CatalogVersionUpdated = 'catalog.version.updated';
    case CatalogVersionPublished = 'catalog.version.published';
    case ContentPageDrafted = 'content.page.drafted';
    case ContentRevisionUpdated = 'content.revision.updated';
    case ContentRevisionPublished = 'content.revision.published';
    case ContentRevisionRestored = 'content.revision.restored';
}
