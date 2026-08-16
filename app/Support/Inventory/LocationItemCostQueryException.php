<?php

namespace App\Support\Inventory;

use RuntimeException;

final class LocationItemCostQueryException extends RuntimeException
{
    /**
     * The requested location does not belong to the requesting organization.
     */
    public static function locationNotInOrganization(int $locationId, int $organizationId): self
    {
        return new self(
            "Location {$locationId} does not belong to organization {$organizationId}.",
        );
    }

    /**
     * The requested inventory item does not belong to the requesting organization.
     */
    public static function inventoryItemNotInOrganization(int $inventoryItemId, int $organizationId): self
    {
        return new self(
            "Inventory item {$inventoryItemId} does not belong to organization {$organizationId}.",
        );
    }
}
