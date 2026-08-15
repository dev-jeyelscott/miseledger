<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use App\Models\WasteRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create tenant-contained storage for Waste reporting tests.
 */
function createWasteReportingStorage(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storage = new StorageLocation;
    $storage->organization_id = $organization->id;
    $storage->location_id = $location->id;
    $storage->name = "Storage {$code}";
    $storage->code = $code;
    $storage->active = true;
    $storage->save();

    return $storage;
}

/**
 * Persist one immutable Waste reporting evidence row with explicit snapshots.
 */
function createWasteReportingEvidence(
    Organization $organization,
    Location $location,
    StorageLocation $storage,
    InventoryItem $item,
    WasteReason $reason,
    UnitOfMeasure $baseUnit,
    ?User $recorder,
    string $baseQuantity,
    string $unitCost,
    string $totalCost,
    CarbonImmutable $occurredAt,
): WasteRecord {
    return WasteRecord::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'storage_location_id' => $storage->id,
        'inventory_item_id' => $item->id,
        'waste_reason_id' => $reason->id,
        'operation_id' => (string) Str::uuid(),
        'quantity' => $baseQuantity,
        'unit_id' => $baseUnit->id,
        'base_quantity' => $baseQuantity,
        'unit_cost' => $unitCost,
        'total_cost' => $totalCost,
        'occurred_at' => $occurredAt,
        'recorded_by' => $recorder?->id,
        'notes' => null,
    ]);
}

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $this->mainLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Main Kitchen',
        'active' => true,
    ]);

    $this->branchLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Branch Kitchen',
        'active' => true,
    ]);

    $this->mainStorage = createWasteReportingStorage(
        $this->organization,
        $this->mainLocation,
        'MAIN',
    );

    $this->branchStorage = createWasteReportingStorage(
        $this->organization,
        $this->branchLocation,
        'BRANCH',
    );

    $this->foodCategory = InventoryCategory::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Food',
        'active' => true,
    ]);

    $this->beverageCategory = InventoryCategory::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Beverage',
        'active' => true,
    ]);

    $this->gram = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->piece = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Piece',
        'symbol' => 'pc',
        'dimension' => 'count',
        'active' => true,
    ]);

    $this->chicken = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->gram->id,
        'inventory_category_id' => $this->foodCategory->id,
        'name' => 'Chicken',
        'sku' => 'CHICKEN',
        'active' => true,
    ]);

    $this->soda = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->piece->id,
        'inventory_category_id' => $this->beverageCategory->id,
        'name' => 'Soda',
        'sku' => 'SODA',
        'active' => true,
    ]);

    $this->spoilage = WasteReason::query()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Spoilage',
        'active' => true,
    ]);

    $this->preparationError = WasteReason::query()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Preparation Error',
        'active' => true,
    ]);

    $this->manager = User::factory()->create([
        'name' => 'Report Manager',
    ]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->manager->id,
        'role' => OrganizationRole::Manager,
    ]);

    $this->inventoryStaff = User::factory()->create([
        'name' => 'Inventory Reporter',
    ]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->inventoryStaff->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    $this->alex = User::factory()->create([
        'name' => 'Alex Recorder',
    ]);

    $this->bea = User::factory()->create([
        'name' => 'Bea Recorder',
    ]);
});

test(
    'waste reports aggregate immutable evidence by summary reason employee and item',
    function () {
        createWasteReportingEvidence(
            $this->organization,
            $this->mainLocation,
            $this->mainStorage,
            $this->chicken,
            $this->spoilage,
            $this->gram,
            $this->alex,
            '100.100000',
            '0.1000',
            '10.0100',
            CarbonImmutable::parse(
                '2026-08-10 10:00:00',
                'Asia/Manila',
            )->utc(),
        );

        createWasteReportingEvidence(
            $this->organization,
            $this->mainLocation,
            $this->mainStorage,
            $this->chicken,
            $this->preparationError,
            $this->gram,
            $this->bea,
            '50.200000',
            '0.1000',
            '5.0200',
            CarbonImmutable::parse(
                '2026-08-11 11:00:00',
                'Asia/Manila',
            )->utc(),
        );

        createWasteReportingEvidence(
            $this->organization,
            $this->mainLocation,
            $this->mainStorage,
            $this->soda,
            $this->spoilage,
            $this->piece,
            $this->alex,
            '2.000000',
            '3.0000',
            '6.0000',
            CarbonImmutable::parse(
                '2026-08-12 12:00:00',
                'Asia/Manila',
            )->utc(),
        );

        createWasteReportingEvidence(
            $this->organization,
            $this->branchLocation,
            $this->branchStorage,
            $this->chicken,
            $this->spoilage,
            $this->gram,
            $this->alex,
            '1000.000000',
            '1.0000',
            '1000.0000',
            CarbonImmutable::parse(
                '2026-08-11 12:00:00',
                'Asia/Manila',
            )->utc(),
        );

        createWasteReportingEvidence(
            $this->organization,
            $this->mainLocation,
            $this->mainStorage,
            $this->chicken,
            $this->spoilage,
            $this->gram,
            $this->alex,
            '100.000000',
            '1.0000',
            '100.0000',
            CarbonImmutable::parse(
                '2026-08-01 12:00:00',
                'Asia/Manila',
            )->utc(),
        );

        $otherOrganization = Organization::factory()->create([
            'timezone' => 'Asia/Manila',
        ]);
        $otherLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);
        $otherStorage = createWasteReportingStorage(
            $otherOrganization,
            $otherLocation,
            'OTHER',
        );
        $otherUnit = UnitOfMeasure::factory()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Unit',
            'symbol' => 'other',
            'dimension' => 'count',
            'active' => true,
        ]);
        $otherItem = InventoryItem::factory()->create([
            'organization_id' => $otherOrganization->id,
            'base_unit_of_measure_id' => $otherUnit->id,
            'active' => true,
        ]);
        $otherReason = WasteReason::query()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Tenant Reason',
            'active' => true,
        ]);

        createWasteReportingEvidence(
            $otherOrganization,
            $otherLocation,
            $otherStorage,
            $otherItem,
            $otherReason,
            $otherUnit,
            null,
            '999.000000',
            '1.0000',
            '999.0000',
            CarbonImmutable::parse(
                '2026-08-11 12:00:00',
                'Asia/Manila',
            )->utc(),
        );

        $filters = [
            'location_id' => $this->mainLocation->id,
            'from' => '2026-08-10',
            'to' => '2026-08-12',
        ];

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('waste.index', $filters))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('waste/index')
                    ->where('rows.total', 3)
                    ->where('report.summary.recordCount', 3)
                    ->where(
                        'report.summary.quantityTotals',
                        [
                            [
                                'baseUnitId' => $this->gram->id,
                                'quantity' => '150.300000',
                                'unitSymbol' => 'g',
                            ],
                            [
                                'baseUnitId' => $this->piece->id,
                                'quantity' => '2.000000',
                                'unitSymbol' => 'pc',
                            ],
                        ],
                    )
                    ->where(
                        'report.summary.totalCost',
                        '21.0300',
                    )
                    ->where('report.byReason.0.reasonName', 'Preparation Error')
                    ->where('report.byReason.0.recordCount', 1)
                    ->where(
                        'report.byReason.0.quantityTotals.0.quantity',
                        '50.200000',
                    )
                    ->where('report.byReason.0.totalCost', '5.0200')
                    ->where('report.byReason.1.reasonName', 'Spoilage')
                    ->where('report.byReason.1.recordCount', 2)
                    ->where(
                        'report.byReason.1.quantityTotals',
                        [
                            [
                                'baseUnitId' => $this->gram->id,
                                'quantity' => '100.100000',
                                'unitSymbol' => 'g',
                            ],
                            [
                                'baseUnitId' => $this->piece->id,
                                'quantity' => '2.000000',
                                'unitSymbol' => 'pc',
                            ],
                        ],
                    )
                    ->where('report.byReason.1.totalCost', '16.0100')
                    ->where('report.byEmployee.0.employeeName', 'Alex Recorder')
                    ->where('report.byEmployee.0.recordCount', 2)
                    ->where('report.byEmployee.0.totalCost', '16.0100')
                    ->where('report.byEmployee.1.employeeName', 'Bea Recorder')
                    ->where('report.byEmployee.1.recordCount', 1)
                    ->where('report.byEmployee.1.totalCost', '5.0200')
                    ->where('report.byItem.0.itemName', 'Chicken')
                    ->where('report.byItem.0.recordCount', 2)
                    ->where(
                        'report.byItem.0.totalQuantity',
                        '150.300000',
                    )
                    ->where('report.byItem.0.totalCost', '15.0300')
                    ->where('report.byItem.1.itemName', 'Soda')
                    ->where('report.byItem.1.recordCount', 1)
                    ->where(
                        'report.byItem.1.totalQuantity',
                        '2.000000',
                    )
                    ->where('report.byItem.1.totalCost', '6.0000'),
            );
    },
);

test(
    'waste reports apply category filters and preserve them through pagination',
    function () {
        $occurredAt = CarbonImmutable::parse(
            '2026-08-15 12:00:00',
            'Asia/Manila',
        )->utc();

        for ($index = 0; $index < 26; $index++) {
            createWasteReportingEvidence(
                $this->organization,
                $this->mainLocation,
                $this->mainStorage,
                $this->chicken,
                $this->spoilage,
                $this->gram,
                $this->alex,
                '1.000000',
                '0.2500',
                '0.2500',
                $occurredAt->subSeconds($index),
            );
        }

        createWasteReportingEvidence(
            $this->organization,
            $this->mainLocation,
            $this->mainStorage,
            $this->soda,
            $this->spoilage,
            $this->piece,
            $this->alex,
            '1.000000',
            '100.0000',
            '100.0000',
            $occurredAt,
        );

        $filters = [
            'location_id' => $this->mainLocation->id,
            'inventory_category_id' => $this->foodCategory->id,
            'inventory_item_id' => $this->chicken->id,
            'waste_reason_id' => $this->spoilage->id,
            'from' => '2026-08-15',
            'to' => '2026-08-15',
        ];

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('waste.index', $filters))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('waste/index')
                    ->where('filters.inventoryCategoryId', $this->foodCategory->id)
                    ->where('rows.current_page', 1)
                    ->where('rows.last_page', 2)
                    ->where('rows.total', 26)
                    ->where('report.summary.recordCount', 26)
                    ->where(
                        'report.summary.quantityTotals.0.quantity',
                        '26.000000',
                    )
                    ->where('report.summary.totalCost', '6.5000')
                    ->where(
                        'rows.next_page_url',
                        fn (?string $url): bool => $url !== null
                            && str_contains(
                                $url,
                                "inventory_category_id={$this->foodCategory->id}",
                            )
                            && str_contains(
                                $url,
                                "inventory_item_id={$this->chicken->id}",
                            )
                            && str_contains(
                                $url,
                                "waste_reason_id={$this->spoilage->id}",
                            )
                            && str_contains($url, 'page=2'),
                    ),
            );
    },
);

test(
    'waste aggregate reports never expose protected cost values',
    function () {
        createWasteReportingEvidence(
            $this->organization,
            $this->mainLocation,
            $this->mainStorage,
            $this->chicken,
            $this->spoilage,
            $this->gram,
            $this->alex,
            '10.000000',
            '50.0000',
            '500.0000',
            CarbonImmutable::parse(
                '2026-08-15 12:00:00',
                'Asia/Manila',
            )->utc(),
        );

        $this
            ->actingAs($this->inventoryStaff)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('waste.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('waste/index')
                    ->where('canViewCosts', false)
                    ->where('report.summary.recordCount', 1)
                    ->where('report.summary.totalCost', null)
                    ->where('report.byReason.0.totalCost', null)
                    ->where('report.byEmployee.0.totalCost', null)
                    ->where('report.byItem.0.totalCost', null)
                    ->where('rows.data.0.unitCost', null)
                    ->where('rows.data.0.totalCost', null),
            );
    },
);
