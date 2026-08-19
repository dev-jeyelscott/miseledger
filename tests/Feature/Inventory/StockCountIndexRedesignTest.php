<?php

use App\Enums\OrganizationRole;
use App\Enums\StockCountStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockCount;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** Create one storage location for Stock Counts index regression tests. */
function createStockCountIndexStorageForTest(
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

/** Persist one stock-count lifecycle record without invoking inventory mutation. */
function createStockCountIndexRecordForTest(
    Organization $organization,
    Location $location,
    StorageLocation $storageLocation,
    User $actor,
    StockCountStatus $status,
    string $number,
): StockCount {
    $hasCountEvidence = in_array(
        $status,
        [StockCountStatus::Submitted, StockCountStatus::Finalized],
        true,
    );

    return StockCount::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'storage_location_id' => $storageLocation->id,
        'number' => $number,
        'status' => $status,
        'counted_at' => $hasCountEvidence ? now() : null,
        'created_by' => $actor->id,
        'submitted_by' => $hasCountEvidence ? $actor->id : null,
        'finalized_by' => $status === StockCountStatus::Finalized
            ? $actor->id
            : null,
        'finalized_at' => $status === StockCountStatus::Finalized
            ? now()
            : null,
    ]);
}

/** Add persisted finalized variance evidence for one count line. */
function addStockCountIndexVarianceForTest(
    StockCount $count,
    InventoryItem $inventoryItem,
    UnitOfMeasure $unit,
    string $variance = '1.000000',
): void {
    $count->lines()->create([
        'inventory_item_id' => $inventoryItem->id,
        'expected_base_quantity' => '1.000000',
        'counted_quantity' => '2.000000',
        'count_unit_id' => $unit->id,
        'counted_base_quantity' => '2.000000',
        'variance_base_quantity' => $variance,
        'variance_unit_cost' => '1.0000',
        'variance_total_cost' => '1.0000',
        'notes' => null,
    ]);
}

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Makati Branch',
        'active' => true,
    ]);

    $this->storageLocation = createStockCountIndexStorageForTest(
        $this->organization,
        $this->location,
        'MAKATI',
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
        'name' => 'Index Count Item',
        'sku' => 'INDEX-COUNT',
        'active' => true,
    ]);

    $this->manager = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->manager->id,
        'role' => OrganizationRole::Manager,
    ]);
});

test(
    'stock counts index is tenant isolated and exposes truthful workflow summary evidence',
    function () {
        createStockCountIndexRecordForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->manager,
            StockCountStatus::Draft,
            'COUNT-DRAFT',
        );

        createStockCountIndexRecordForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->manager,
            StockCountStatus::Submitted,
            'COUNT-SUBMITTED',
        );

        $finalized = createStockCountIndexRecordForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->manager,
            StockCountStatus::Finalized,
            'COUNT-FINALIZED',
        );

        addStockCountIndexVarianceForTest(
            $finalized,
            $this->inventoryItem,
            $this->unit,
        );

        $otherOrganization = Organization::factory()->create();
        $otherLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);
        $otherStorage = createStockCountIndexStorageForTest(
            $otherOrganization,
            $otherLocation,
            'OTHER-TENANT',
        );
        $otherActor = User::factory()->create();

        createStockCountIndexRecordForTest(
            $otherOrganization,
            $otherLocation,
            $otherStorage,
            $otherActor,
            StockCountStatus::Finalized,
            'OTHER-TENANT-COUNT',
        );

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('stock-counts.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('stock-counts/index')
                    ->has('rows', 3)
                    ->where('summary.totalCount', 3)
                    ->where('summary.openCount', 2)
                    ->where('summary.finalizedTodayCount', 1)
                    ->where('summary.varianceAlertCount', 1)
                    ->where('pagination.total', 3)
                    ->where('rows.0.number', 'COUNT-FINALIZED')
                    ->where('rows.0.status', 'finalized')
                    ->where(
                        'rows.0.countedByName',
                        $this->manager->name,
                    )
                    ->where('rows.0.varianceItemCount', 1)
                    ->where('canCreate', true)
                    ->where('canViewReport', true),
            );
    },
);

test(
    'stock counts index applies server side search workflow location storage and date filters safely',
    function () {
        $finalized = createStockCountIndexRecordForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->manager,
            StockCountStatus::Finalized,
            'COUNT-ALPHA',
        );

        addStockCountIndexVarianceForTest(
            $finalized,
            $this->inventoryItem,
            $this->unit,
        );

        $otherLocation = Location::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'BGC Branch',
            'active' => true,
        ]);
        $otherStorage = createStockCountIndexStorageForTest(
            $this->organization,
            $otherLocation,
            'BGC',
        );

        createStockCountIndexRecordForTest(
            $this->organization,
            $otherLocation,
            $otherStorage,
            $this->manager,
            StockCountStatus::Submitted,
            'COUNT-BETA',
        );

        $today = now()
            ->setTimezone($this->organization->timezone)
            ->toDateString();

        $url = route('stock-counts.index', [
            'search' => 'ALPHA',
            'view' => 'variance',
            'location_id' => $this->location->id,
            'storage_location_id' => $this->storageLocation->id,
            'from' => $today,
            'to' => $today,
        ]);

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get($url)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('stock-counts/index')
                    ->has('rows', 1)
                    ->where('rows.0.number', 'COUNT-ALPHA')
                    ->where('filters.view', 'variance')
                    ->where(
                        'filters.locationId',
                        $this->location->id,
                    )
                    ->where(
                        'filters.storageLocationId',
                        $this->storageLocation->id,
                    ),
            );

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('stock-counts.index', [
                'location_id' => $this->location->id,
                'storage_location_id' => $otherStorage->id,
            ]))
            ->assertSessionHasErrors('storage_location_id');

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
            ->get(route('stock-counts.index', [
                'location_id' => $crossTenantLocation->id,
            ]))
            ->assertSessionHasErrors('location_id');
    },
);

test('stock counts index paginates and preserves query string state', function () {
    foreach (range(1, 12) as $number) {
        createStockCountIndexRecordForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->manager,
            StockCountStatus::Draft,
            sprintf('COUNT-%03d', $number),
        );
    }

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('stock-counts.index', [
            'view' => 'draft',
            'per_page' => 10,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('stock-counts/index')
                ->has('rows', 10)
                ->where('pagination.currentPage', 1)
                ->where('pagination.lastPage', 2)
                ->where('pagination.total', 12)
                ->where(
                    'pagination.nextPageUrl',
                    fn (?string $url): bool => $url !== null
                        && str_contains($url, 'page=2')
                        && str_contains($url, 'view=draft')
                        && str_contains($url, 'per_page=10'),
                ),
        );
});

test('stock counts index preserves permission aware read and action visibility', function () {
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
        ->get(route('stock-counts.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('stock-counts/index')
                ->where('canCreate', false)
                ->where('canViewReport', true),
        );

    $this
        ->actingAs($kitchenStaff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('stock-counts.index'))
        ->assertForbidden();
});
