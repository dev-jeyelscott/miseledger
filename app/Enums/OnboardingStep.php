<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case Organization = 'organization';
    case Location = 'location';
    case Units = 'units';
    case Inventory = 'inventory';
    case OpeningStock = 'opening_stock';
    case Suppliers = 'suppliers';
    case Team = 'team';

    /**
     * Whether the step must be satisfied before operational workflows unlock.
     */
    public function isRequired(): bool
    {
        return match ($this) {
            self::Suppliers, self::Team => false,
            default => true,
        };
    }
}
