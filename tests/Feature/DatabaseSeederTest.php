<?php

use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WasteRecord;
use Database\Seeders\DatabaseSeeder;

test('database seeder creates one coherent local demo dataset', function () {
    $this->seed(DatabaseSeeder::class);

    $organization = Organization::query()->sole();

    $superAdmin = User::query()
        ->where('email', 'superadmin@miseledger.com')
        ->sole();

    expect(User::query()->count())
        ->toBe(6)
        ->and(OrganizationMembership::query()->count())
        ->toBe(count(OrganizationRole::cases()))
        ->and($superAdmin->organizationMemberships()->exists())
        ->toBeFalse()
        ->and(
            Location::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->count(),
        )
        ->toBe(2)
        ->and(
            StorageLocation::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->count(),
        )
        ->toBe(2)
        ->and(
            InventoryItem::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->count(),
        )
        ->toBe(50)
        ->and(
            Supplier::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->count(),
        )
        ->toBe(5)
        ->and(
            PurchaseOrder::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->count(),
        )
        ->toBe(5)
        ->and(
            GoodsReceipt::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->count(),
        )
        ->toBe(5)
        ->and(
            StockCount::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->count(),
        )
        ->toBe(5)
        ->and(
            WasteRecord::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->count(),
        )
        ->toBe(5);

    foreach (OrganizationRole::cases() as $role) {
        expect(
            OrganizationMembership::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where(
                    'role',
                    $role->value,
                )
                ->count(),
        )->toBe(1);
    }

    expect(
        InventoryItem::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->whereDoesntHave(
                'baseUnitOfMeasure',
                fn ($query) => $query
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where('active', true),
            )
            ->count(),
    )
        ->toBe(0)
        ->and(
            PurchaseOrder::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where(
                    'status',
                    PurchaseOrderStatus::Received->value,
                )
                ->count(),
        )
        ->toBe(5)
        ->and(
            GoodsReceipt::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where(
                    'status',
                    GoodsReceiptStatus::Finalized->value,
                )
                ->count(),
        )
        ->toBe(5)
        ->and(
            StockCount::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where(
                    'status',
                    StockCountStatus::Finalized->value,
                )
                ->count(),
        )
        ->toBe(5)
        ->and(
            StockMovement::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where(
                    'type',
                    StockMovementType::PurchaseReceipt->value,
                )
                ->count(),
        )
        ->toBe(5)
        ->and(
            StockMovement::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where(
                    'type',
                    StockMovementType::Waste->value,
                )
                ->count(),
        )
        ->toBe(5)
        ->and(
            StockMovement::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where(
                    'type',
                    StockMovementType::CountAdjustment->value,
                )
                ->count(),
        )
        ->toBe(0)
        ->and(
            StockBalance::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->count(),
        )
        ->toBe(5);

    foreach (
        StockBalance::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->get() as $stockBalance
    ) {
        expect(
            $stockBalance->quantity_on_hand,
        )->toBe('98.000000');
    }
});
