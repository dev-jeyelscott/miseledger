<?php

namespace App\Enums;

enum RecipeVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    /**
     * Only drafts may have their yield or components changed.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
