<?php

namespace App\Support\Recipes;

use RuntimeException;

final class EffectiveRecipeVersionResolutionException extends RuntimeException
{
    /**
     * No published recipe version is effective for the requested timestamp.
     */
    public static function noneEffective(int $recipeId, string $asOf): self
    {
        return new self(
            "No published recipe version is effective for recipe {$recipeId} at {$asOf}.",
        );
    }

    /**
     * More than one published recipe version is effective for the requested
     * timestamp, meaning an effective-period invariant was violated.
     */
    public static function multipleEffective(int $recipeId, string $asOf): self
    {
        return new self(
            "Multiple published recipe versions are effective for recipe {$recipeId} at {$asOf}.",
        );
    }
}
