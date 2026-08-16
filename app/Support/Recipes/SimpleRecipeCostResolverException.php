<?php

namespace App\Support\Recipes;

use RuntimeException;

final class SimpleRecipeCostResolverException extends RuntimeException
{
    /**
     * The requested recipe version does not belong to the requesting
     * organization.
     */
    public static function recipeVersionNotInOrganization(int $recipeVersionId, int $organizationId): self
    {
        return new self(
            "Recipe version {$recipeVersionId} does not belong to organization {$organizationId}.",
        );
    }

    /**
     * Only published recipe versions carry an effective, costable
     * component snapshot.
     */
    public static function recipeVersionNotPublished(int $recipeVersionId): self
    {
        return new self(
            "Recipe version {$recipeVersionId} is not published.",
        );
    }

    /**
     * The requested location does not belong to the requesting
     * organization.
     */
    public static function locationNotInOrganization(int $locationId, int $organizationId): self
    {
        return new self(
            "Location {$locationId} does not belong to organization {$organizationId}.",
        );
    }
}
