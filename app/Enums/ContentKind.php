<?php

namespace App\Enums;

/**
 * Recognized CMS scopes. Release Notes and User Guide are code-owned/static
 * modules and are never represented as a ContentKind.
 */
enum ContentKind: string
{
    case Marketing = 'marketing';
    case Legal = 'legal';
}
