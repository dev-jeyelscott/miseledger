<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\WasteReason;

final class EnsureDefaultWasteReasons
{
    /** @var list<string> */
    private const DEFAULT_NAMES = [
        'Spoilage',
        'Expired',
        'Damaged',
        'Preparation Error',
        'Overproduction',
        'Customer Complaint',
        'Other',
    ];

    /**
     * Ensure approved defaults exist without changing existing configuration.
     */
    public function handle(Organization $organization): void
    {
        foreach (self::DEFAULT_NAMES as $name) {
            WasteReason::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $name,
                ],
                [
                    'active' => true,
                ],
            );
        }
    }

    /**
     * Return the approved default waste reason names.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return self::DEFAULT_NAMES;
    }
}
