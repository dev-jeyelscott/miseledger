<?php

use App\Enums\OrganizationRole;
use App\Enums\StockTransferStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** Create one storage location for Stock Transfers index regression tests. */
function createStockTransferIndexStorageForTest(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = "Storage {$code}";
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

/** Persist one transfer lifecycle record without invoking inventory mutation. */
function createStockTransferIndexRecordForTest(
    Organization $organization,
    Location $fromLocation,
    StorageLocation $fromStorage,
    Location $toLocation,
    StorageLocation $toStorage,
    User $actor,
    StockTransferStatus $status,
    string $number,
): StockTransfer {
    $wasShipped = in_array(
        $status,
        [StockTransferStatus::Shipped, StockTransferStatus::Received],
        true,
    );
    $wasReceived = $status === StockTransferStatus::Received;

    return StockTransfer::query()->create([
        'organization_id' => $organization->id,
        'from_location_id' => $fromLocation->id,
        'from_storage_location_id' => $fromStorage->id,
        'to_location_id' => $toLocation->id,
        'to_storage_location_id' => $toStorage->id,
        'number' => $number,
        'status' => $status,
        'requested_at' => now(),
        'shipped_at' => $wasShipped ? now() : null,
        'received_at' => $wasReceived ? now() : null,
        'created_by' => $actor->id,
        'shipped_by' => $wasShipped ? $actor->id : null,
        'received_by' => $wasReceived ? $actor->id : null,
        'notes' => null,
    ]);
}

/** Add coherent line evidence for one transfer index record. */
function addStockTransferIndexLineForTest(
    StockTransfer $transfer,
    InventoryItem $inventoryItem,
    UnitOfMeasure $unit,
    string $receivedBaseQuantity = '1.000000',
    string $varianceBaseQuantity = '0.000000',
): void {
    $wasShipped = in_array(
        $transfer->status,
        [StockTransferStatus::Shipped, StockTransferStatus::Received],
        true,
    );
    $wasReceived = $transfer->status === StockTransferStatus::Received;

    $transfer->lines()->create([
        'organization_id' => $transfer->organization_id,
        'inventory_item_id' => $inventoryItem->id,
        'requested_quantity' => '1.000000',
        'unit_id' => $unit->id,
        'requested_base_quantity' => '1.000000',
        'shipped_base_quantity' => $wasShipped ? '1.000000' : null,
        'received_base_quantity' => $wasReceived
            ? $receivedBaseQuantity
            : null,
        'unit_cost' => $wasShipped ? '1.0000' : null,
        'variance_base_quantity' => $wasReceived
            ? $varianceBaseQuantity
            : null,
    ]);
}

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $this->fromLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Main Warehouse',
        'active' => true,
    ]);

    $this->toLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Bar Store',
        'active' => true,
    ]);

    $this->fromStorage = createStockTransferIndexStorageForTest(
        $this->organization,
        $this->fromLocation,
        'MAIN',
    );

    $this->toStorage = createStockTransferIndexStorageForTest(
        $this->organization,
        $this->toLocation,
        'BAR',
    );

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Piece',
        'symbol' => 'pc',
        'dimension' => 'count',
        'active' => true,
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'name' => 'Transfer Index Item',
        'sku' => 'TRANSFER-INDEX',
        'active' => true,
    ]);

    $this->manager = User::factory()->create([
        'name' => 'MiseLedger Manager',
    ]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->manager->id,
        'role' => OrganizationRole::Manager,
    ]);
});

test(
    'stock transfers index is tenant isolated and exposes truthful lifecycle summary evidence',
    function () {
        $draft = createStockTransferIndexRecordForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->manager,
            StockTransferStatus::Draft,
            'TRANSFER-DRAFT',
        );
        addStockTransferIndexLineForTest(
            $draft,
            $this->inventoryItem,
            $this->unit,
        );

        $shipped = createStockTransferIndexRecordForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->manager,
            StockTransferStatus::Shipped,
            'TRANSFER-SHIPPED',
        );
        addStockTransferIndexLineForTest(
            $shipped,
            $this->inventoryItem,
            $this->unit,
        );

        $received = createStockTransferIndexRecordForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->manager,
            StockTransferStatus::Received,
            'TRANSFER-RECEIVED',
        );
        addStockTransferIndexLineForTest(
            $received,
            $this->inventoryItem,
            $this->unit,
            '0.500000',
            '-0.500000',
        );

        $cancelled = createStockTransferIndexRecordForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->manager,
            StockTransferStatus::Cancelled,
            'TRANSFER-CANCELLED',
        );
        addStockTransferIndexLineForTest(
            $cancelled,
            $this->inventoryItem,
            $this->unit,
        );

        $otherOrganization = Organization::factory()->create();
        $otherFromLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);
        $otherToLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);
        $otherFromStorage = createStockTransferIndexStorageForTest(
            $otherOrganization,
            $otherFromLocation,
            'OTHER-FROM',
        );
        $otherToStorage = createStockTransferIndexStorageForTest(
            $otherOrganization,
            $otherToLocation,
            'OTHER-TO',
        );
        $otherActor = User::factory()->create();

        createStockTransferIndexRecordForTest(
            $otherOrganization,
            $otherFromLocation,
            $otherFromStorage,
            $otherToLocation,
            $otherToStorage,
            $otherActor,
            StockTransferStatus::Received,
            'OTHER-TENANT-TRANSFER',
        );

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('stock-transfers.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('stock-transfers/index')
                    ->has('rows', 4)
                    ->where('summary.draftCount', 1)
                    ->where('summary.shippedCount', 1)
                    ->where('summary.receivedCount', 1)
                    ->where('summary.varianceCount', 1)
                    ->where('pagination.total', 4)
                    ->where('rows.0.number', 'TRANSFER-CANCELLED')
                    ->where('rows.1.number', 'TRANSFER-RECEIVED')
                    ->where('rows.1.varianceItemCount', 1)
                    ->where(
                        'rows.1.requestedByName',
                        $this->manager->name,
                    )
                    ->where('canCreate', true)
                    ->where('canViewReport', true),
            );
    },
);

test(
    'stock transfers index applies server side search lifecycle location and requested date filters safely',
    function () {
        $alpha = createStockTransferIndexRecordForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->manager,
            StockTransferStatus::Received,
            'TRANSFER-ALPHA',
        );
        addStockTransferIndexLineForTest(
            $alpha,
            $this->inventoryItem,
            $this->unit,
            '0.500000',
            '-0.500000',
        );

        $otherFromLocation = Location::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Prep Kitchen',
            'active' => true,
        ]);
        $otherToLocation = Location::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Freezer Room',
            'active' => true,
        ]);
        $otherFromStorage = createStockTransferIndexStorageForTest(
            $this->organization,
            $otherFromLocation,
            'PREP',
        );
        $otherToStorage = createStockTransferIndexStorageForTest(
            $this->organization,
            $otherToLocation,
            'FREEZER',
        );

        $beta = createStockTransferIndexRecordForTest(
            $this->organization,
            $otherFromLocation,
            $otherFromStorage,
            $otherToLocation,
            $otherToStorage,
            $this->manager,
            StockTransferStatus::Shipped,
            'TRANSFER-BETA',
        );
        addStockTransferIndexLineForTest(
            $beta,
            $this->inventoryItem,
            $this->unit,
        );

        $today = now()
            ->setTimezone($this->organization->timezone)
            ->toDateString();

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('stock-transfers.index', [
                'search' => 'ALPHA',
                'view' => 'variance',
                'from_location_id' => $this->fromLocation->id,
                'to_location_id' => $this->toLocation->id,
                'from' => $today,
                'to' => $today,
            ]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('stock-transfers/index')
                    ->has('rows', 1)
                    ->where('rows.0.number', 'TRANSFER-ALPHA')
                    ->where('filters.view', 'variance')
                    ->where(
                        'filters.fromLocationId',
                        $this->fromLocation->id,
                    )
                    ->where(
                        'filters.toLocationId',
                        $this->toLocation->id,
                    ),
            );

        $otherOrganization = Organization::factory()->create();
        $crossTenantLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('stock-transfers.index', [
                'from_location_id' => $crossTenantLocation->id,
            ]))
            ->assertSessionHasErrors('from_location_id');

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('stock-transfers.index', [
                'from' => '2026-08-20',
                'to' => '2026-08-19',
            ]))
            ->assertSessionHasErrors('from');
    },
);

test('stock transfers index paginates sorts and preserves query string state', function () {
    foreach (range(1, 12) as $number) {
        $transfer = createStockTransferIndexRecordForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->manager,
            StockTransferStatus::Draft,
            sprintf('TRANSFER-%03d', $number),
        );

        addStockTransferIndexLineForTest(
            $transfer,
            $this->inventoryItem,
            $this->unit,
        );
    }

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('stock-transfers.index', [
            'view' => 'draft',
            'sort' => 'number',
            'direction' => 'asc',
            'per_page' => 10,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('stock-transfers/index')
                ->has('rows', 10)
                ->where('rows.0.number', 'TRANSFER-001')
                ->where('pagination.currentPage', 1)
                ->where('pagination.lastPage', 2)
                ->where('pagination.total', 12)
                ->where(
                    'pagination.nextPageUrl',
                    fn (?string $url): bool => $url !== null
                        && str_contains($url, 'page=2')
                        && str_contains($url, 'view=draft')
                        && str_contains($url, 'sort=number')
                        && str_contains($url, 'direction=asc')
                        && str_contains($url, 'per_page=10'),
                ),
        );
});

test('stock transfers index preserves permission aware read and action visibility', function () {
    $auditor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $auditor->id,
        'role' => OrganizationRole::Auditor,
    ]);

    $kitchenStaff = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $kitchenStaff->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $this
        ->actingAs($auditor)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('stock-transfers.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('stock-transfers/index')
                ->where('canCreate', false)
                ->where('canViewReport', true),
        );

    $this
        ->actingAs($kitchenStaff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('stock-transfers.index'))
        ->assertForbidden();
});
