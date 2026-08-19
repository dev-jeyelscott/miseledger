<?php

use App\Actions\Inventory\ReplayStockLedger;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RecipeVersionStatus;
use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptNonStockLine;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeVersionComponent;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\SupplierItemPrice;
use App\Models\User;
use App\Models\WasteRecord;
use Database\Seeders\DatabaseSeeder;

test('database seeder creates a connected presentation-ready demo dataset', function () {
    $this->seed(DatabaseSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Sinta Kitchen & Café')
        ->sole();

    $superAdmin = User::query()
        ->where('email', 'superadmin@miseledger.com')
        ->sole();

    expect(User::query()->count())
        ->toBe(6)
        ->and($superAdmin->organizationMemberships()->exists())
        ->toBeFalse()
        ->and(
            OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBe(count(OrganizationRole::cases()))
        ->and(
            Location::query()
                ->where('organization_id', $organization->id)
                ->where('active', true)
                ->count(),
        )
        ->toBe(3)
        ->and(
            Location::query()
                ->where('organization_id', $organization->id)
                ->where('active', false)
                ->count(),
        )
        ->toBe(1)
        ->and(
            StorageLocation::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBeGreaterThanOrEqual(10);

    foreach (OrganizationRole::cases() as $role) {
        expect(
            OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('role', $role->value)
                ->count(),
        )->toBe(1);
    }

    expect(
        InventoryCategory::query()
            ->where('organization_id', $organization->id)
            ->count(),
    )
        ->toBeGreaterThanOrEqual(8)
        ->and(
            InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBeGreaterThanOrEqual(30)
        ->and(
            InventoryItemUnit::query()
                ->whereHas(
                    'inventoryItem',
                    fn ($query) => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                )
                ->count(),
        )
        ->toBeGreaterThanOrEqual(10)
        ->and(
            InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->where('name', 'like', 'Demo Inventory Item%')
                ->exists(),
        )
        ->toBeFalse()
        ->and(
            InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->where('active', true)
                ->whereDoesntHave(
                    'baseUnitOfMeasure',
                    fn ($query) => $query
                        ->where('organization_id', $organization->id)
                        ->where('active', true),
                )
                ->count(),
        )
        ->toBe(0);

    $inactiveItem = InventoryItem::query()
        ->where('organization_id', $organization->id)
        ->where('sku', 'MANGO-PUREE')
        ->sole();

    expect($inactiveItem->active)->toBeFalse();

    expect(
        Supplier::query()
            ->where('organization_id', $organization->id)
            ->count(),
    )
        ->toBeGreaterThanOrEqual(6)
        ->and(
            Supplier::query()
                ->where('organization_id', $organization->id)
                ->where('name', 'like', 'Demo Supplier%')
                ->exists(),
        )
        ->toBeFalse()
        ->and(
            SupplierItem::query()
                ->where('organization_id', $organization->id)
                ->where('active', true)
                ->whereNull('current_price')
                ->exists(),
        )
        ->toBeFalse()
        ->and(
            SupplierItemPrice::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBeGreaterThan(
            SupplierItem::query()
                ->where('organization_id', $organization->id)
                ->count(),
        );

    foreach (PurchaseOrderStatus::cases() as $status) {
        expect(
            PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->where('status', $status->value)
                ->exists(),
        )->toBeTrue();
    }

    foreach (GoodsReceiptStatus::cases() as $status) {
        expect(
            GoodsReceipt::query()
                ->where('organization_id', $organization->id)
                ->where('status', $status->value)
                ->exists(),
        )->toBeTrue();
    }

    expect(
        GoodsReceiptNonStockLine::query()->exists(),
    )->toBeTrue();

    foreach (StockTransferStatus::cases() as $status) {
        expect(
            StockTransfer::query()
                ->where('organization_id', $organization->id)
                ->where('status', $status->value)
                ->exists(),
        )->toBeTrue();
    }

    expect(
        StockTransferLine::query()
            ->where('variance_base_quantity', '<', '0')
            ->exists(),
    )->toBeTrue();

    foreach (StockCountStatus::cases() as $status) {
        expect(
            StockCount::query()
                ->where('organization_id', $organization->id)
                ->where('status', $status->value)
                ->exists(),
        )->toBeTrue();
    }

    $countAdjustments = StockMovement::query()
        ->where('organization_id', $organization->id)
        ->where('type', StockMovementType::CountAdjustment->value);

    expect((clone $countAdjustments)->where('quantity', '>', 0)->exists())
        ->toBeTrue()
        ->and((clone $countAdjustments)->where('quantity', '<', 0)->exists())
        ->toBeTrue();

    foreach (StockMovementType::cases() as $type) {
        expect(
            StockMovement::query()
                ->where('organization_id', $organization->id)
                ->where('type', $type->value)
                ->exists(),
        )->toBeTrue();
    }

    expect(
        WasteRecord::query()
            ->where('organization_id', $organization->id)
            ->count(),
    )
        ->toBeGreaterThanOrEqual(6)
        ->and(
            Recipe::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
        ->toBeGreaterThanOrEqual(5)
        ->and(
            RecipeVersion::query()
                ->where('status', RecipeVersionStatus::Published->value)
                ->count(),
        )
        ->toBeGreaterThanOrEqual(4)
        ->and(
            RecipeVersion::query()
                ->where('status', RecipeVersionStatus::Draft->value)
                ->exists(),
        )
        ->toBeTrue()
        ->and(
            RecipeVersionComponent::query()
                ->whereNotNull('component_recipe_version_id')
                ->exists(),
        )
        ->toBeTrue()
        ->and(
            AuditLog::query()
                ->where('organization_id', $organization->id)
                ->exists(),
        )
        ->toBeTrue();

    $greenChili = InventoryItem::query()
        ->where('organization_id', $organization->id)
        ->where('sku', 'GREEN-CHILI')
        ->sole();

    $makatiChill = StorageLocation::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'MKT-CHILL')
        ->sole();

    expect(
        StockBalance::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $makatiChill->location_id)
            ->where('storage_location_id', $makatiChill->id)
            ->where('inventory_item_id', $greenChili->id)
            ->sole()
            ->quantity_on_hand,
    )->toBe('0.000000');
});

test('seeded stock balances exactly replay from their immutable movement history', function () {
    $this->seed(DatabaseSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Sinta Kitchen & Café')
        ->sole();

    $replayStockLedger = app(ReplayStockLedger::class);

    $balances = StockBalance::query()
        ->where('organization_id', $organization->id)
        ->get();

    expect($balances)->not->toBeEmpty();

    foreach ($balances as $balance) {
        $replayed = $replayStockLedger->handle(
            $balance->organization_id,
            $balance->location_id,
            $balance->storage_location_id,
            $balance->inventory_item_id,
        );

        expect($balance->quantity_on_hand)
            ->toBe($replayed['quantity_on_hand'])
            ->and($balance->average_unit_cost)
            ->toBe($replayed['average_unit_cost'])
            ->and($balance->inventory_value)
            ->toBe($replayed['inventory_value'])
            ->and($balance->last_movement_at?->toISOString())
            ->toBe($replayed['last_movement_at']?->toISOString());
    }
});

test('seeded operational relationships remain tenant and base-unit safe', function () {
    $this->seed(DatabaseSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Sinta Kitchen & Café')
        ->sole();

    $movements = StockMovement::query()
        ->with([
            'location',
            'storageLocation',
            'inventoryItem',
            'baseUnitOfMeasure',
        ])
        ->where('organization_id', $organization->id)
        ->get();

    expect($movements)->not->toBeEmpty();

    foreach ($movements as $movement) {
        expect($movement->location->organization_id)
            ->toBe($organization->id)
            ->and($movement->storageLocation->organization_id)
            ->toBe($organization->id)
            ->and($movement->storageLocation->location_id)
            ->toBe($movement->location_id)
            ->and($movement->inventoryItem->organization_id)
            ->toBe($organization->id)
            ->and($movement->baseUnitOfMeasure->organization_id)
            ->toBe($organization->id)
            ->and($movement->base_unit_of_measure_id)
            ->toBe($movement->inventoryItem->base_unit_of_measure_id);
    }

    expect(
        PurchaseOrder::query()
            ->where('organization_id', '!=', $organization->id)
            ->exists(),
    )
        ->toBeFalse()
        ->and(
            GoodsReceipt::query()
                ->where('organization_id', '!=', $organization->id)
                ->exists(),
        )
        ->toBeFalse()
        ->and(
            StockTransfer::query()
                ->where('organization_id', '!=', $organization->id)
                ->exists(),
        )
        ->toBeFalse()
        ->and(
            WasteRecord::query()
                ->where('organization_id', '!=', $organization->id)
                ->exists(),
        )
        ->toBeFalse();
});
