<?php

use App\Actions\Inventory\FinalizeStockCount;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\SaveStockCount;
use App\Actions\Inventory\SubmitStockCount;
use App\Enums\OrganizationRole;
use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create one active storage location for physical-count tests.
 */
function createStockCountStorageLocationForTest(
    Organization $organization,
    Location $location,
    string $code = 'COUNT',
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

/**
 * Record authoritative stock before a physical count is finalized.
 */
function recordStockCountOpeningBalanceForTest(
    Organization $organization,
    Location $location,
    StorageLocation $storageLocation,
    InventoryItem $inventoryItem,
    UnitOfMeasure $baseUnit,
    string $quantity = '1000',
    string $unitCost = '0.25',
): void {
    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $inventoryItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: $quantity,
        baseUnitOfMeasure: $baseUnit,
        referenceType: 'opening_balance',
        referenceId: $inventoryItem->id,
        occurredAt: now()->subSecond(),
        idempotencyKey: "stock-count-test:opening:{$inventoryItem->id}",
        inboundUnitCost: $unitCost,
    );
}

/**
 * Persist and submit a single-line physical count.
 */
function createSubmittedStockCountForTest(
    Organization $organization,
    User $actor,
    Location $location,
    StorageLocation $storageLocation,
    InventoryItem $inventoryItem,
    UnitOfMeasure $countUnit,
    string $quantity,
    string $number,
): StockCount {
    $count = app(SaveStockCount::class)->handle(
        $organization,
        $actor,
        [
            'number' => $number,
            'location_id' => $location->id,
            'storage_location_id' => $storageLocation->id,
            'lines' => [
                [
                    'inventory_item_id' => $inventoryItem->id,
                    'counted_quantity' => $quantity,
                    'count_unit_id' => $countUnit->id,
                    'notes' => null,
                ],
            ],
        ],
    );

    return app(SubmitStockCount::class)->handle(
        $organization,
        $actor,
        $count,
    );
}

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->storageLocation =
        createStockCountStorageLocationForTest(
            $this->organization,
            $this->location,
        );

    $this->gram = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->gram->id,
        'name' => 'Count Test Item',
        'sku' => 'COUNT-TEST',
        'active' => true,
    ]);

    $this->actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    $this->manager = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->manager->id,
        'role' => OrganizationRole::Manager,
    ]);
});

test(
    'draft count retains entered practical quantity and converted base quantity without changing inventory',
    function () {
        $count = app(SaveStockCount::class)->handle(
            $this->organization,
            $this->actor,
            [
                'number' => 'COUNT-DRAFT',
                'location_id' => $this->location->id,
                'storage_location_id' => $this->storageLocation->id,
                'lines' => [
                    [
                        'inventory_item_id' => $this->inventoryItem->id,
                        'counted_quantity' => '1.5',
                        'count_unit_id' => $this->kilogram->id,
                        'notes' => 'Physical count',
                    ],
                ],
            ],
        );

        $line = $count->lines()->sole();

        expect($count->status)
            ->toBe(StockCountStatus::Draft)
            ->and($line->counted_quantity)
            ->toBe('1.500000')
            ->and($line->counted_base_quantity)
            ->toBe('1500.000000')
            ->and($line->expected_base_quantity)
            ->toBe('0.000000')
            ->and($line->variance_base_quantity)
            ->toBe('0.000000')
            ->and(StockMovement::query()->count())
            ->toBe(0)
            ->and(StockBalance::query()->count())
            ->toBe(0);
    },
);

test(
    'finalization snapshots expected balance at finalization and creates a positive adjustment',
    function () {
        $count = createSubmittedStockCountForTest(
            $this->organization,
            $this->actor,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->kilogram,
            '1.2',
            'COUNT-POSITIVE',
        );

        recordStockCountOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->gram,
        );

        $finalized = app(
            FinalizeStockCount::class,
        )->handle(
            $this->organization,
            $this->actor,
            $count,
        );

        $line = $finalized->lines()->sole();

        $movement = StockMovement::query()
            ->where(
                'type',
                StockMovementType::CountAdjustment->value,
            )
            ->sole();

        expect($finalized->status)
            ->toBe(StockCountStatus::Finalized)
            ->and($line->expected_base_quantity)
            ->toBe('1000.000000')
            ->and($line->counted_base_quantity)
            ->toBe('1200.000000')
            ->and($line->variance_base_quantity)
            ->toBe('200.000000')
            ->and($line->variance_unit_cost)
            ->toBe('0.2500')
            ->and($line->variance_total_cost)
            ->toBe('50.0000')
            ->and($movement->quantity)
            ->toBe('200.000000')
            ->and($movement->reference_type)
            ->toBe('stock_count_line')
            ->and($movement->reference_id)
            ->toBe($line->id)
            ->and($movement->idempotency_key)
            ->toBe(
                "stock_count:{$count->id}:line:{$line->id}",
            )
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('1200.000000')
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'stock_count.finalized',
                    )
                    ->count(),
            )
            ->toBe(1);
    },
);

test(
    'negative physical variance creates a negative count adjustment at current average cost',
    function () {
        recordStockCountOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->gram,
        );

        $count = createSubmittedStockCountForTest(
            $this->organization,
            $this->actor,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->kilogram,
            '0.8',
            'COUNT-NEGATIVE',
        );

        $finalized = app(
            FinalizeStockCount::class,
        )->handle(
            $this->organization,
            $this->actor,
            $count,
        );

        $line = $finalized->lines()->sole();

        $movement = StockMovement::query()
            ->where(
                'type',
                StockMovementType::CountAdjustment->value,
            )
            ->sole();

        expect($line->expected_base_quantity)
            ->toBe('1000.000000')
            ->and($line->variance_base_quantity)
            ->toBe('-200.000000')
            ->and($line->variance_unit_cost)
            ->toBe('0.2500')
            ->and($line->variance_total_cost)
            ->toBe('-50.0000')
            ->and($movement->quantity)
            ->toBe('-200.000000')
            ->and($movement->unit_cost)
            ->toBe('0.2500')
            ->and($movement->total_cost)
            ->toBe('50.0000')
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('800.000000');
    },
);

test(
    'zero physical variance finalizes without creating a count adjustment',
    function () {
        recordStockCountOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->gram,
        );

        $count = createSubmittedStockCountForTest(
            $this->organization,
            $this->actor,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->kilogram,
            '1',
            'COUNT-ZERO',
        );

        $finalized = app(
            FinalizeStockCount::class,
        )->handle(
            $this->organization,
            $this->actor,
            $count,
        );

        $line = $finalized->lines()->sole();

        expect($finalized->status)
            ->toBe(StockCountStatus::Finalized)
            ->and($line->expected_base_quantity)
            ->toBe('1000.000000')
            ->and($line->variance_base_quantity)
            ->toBe('0.000000')
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::CountAdjustment->value,
                    )
                    ->count(),
            )
            ->toBe(0)
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('1000.000000');
    },
);

test(
    'duplicate finalization cannot duplicate stock movement balance change or audit',
    function () {
        recordStockCountOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->gram,
        );

        $count = createSubmittedStockCountForTest(
            $this->organization,
            $this->actor,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->kilogram,
            '1.2',
            'COUNT-IDEMPOTENT',
        );

        $finalize = app(FinalizeStockCount::class);

        $first = $finalize->handle(
            $this->organization,
            $this->actor,
            $count,
        );

        $second = $finalize->handle(
            $this->organization,
            $this->actor,
            $count,
        );

        expect($first->id)
            ->toBe($second->id)
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::CountAdjustment->value,
                    )
                    ->count(),
            )
            ->toBe(1)
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('1200.000000')
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'stock_count.finalized',
                    )
                    ->count(),
            )
            ->toBe(1);
    },
);

test(
    'finalized physical-count evidence cannot be replaced',
    function () {
        $count = createSubmittedStockCountForTest(
            $this->organization,
            $this->actor,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->kilogram,
            '1',
            'COUNT-IMMUTABLE',
        );

        $finalized = app(
            FinalizeStockCount::class,
        )->handle(
            $this->organization,
            $this->actor,
            $count,
        );

        expect(
            fn () => app(SaveStockCount::class)->handle(
                $this->organization,
                $this->actor,
                [
                    'number' => 'COUNT-CHANGED',
                    'location_id' => $this->location->id,
                    'storage_location_id' => $this->storageLocation->id,
                    'lines' => [
                        [
                            'inventory_item_id' => $this->inventoryItem->id,
                            'counted_quantity' => '2',
                            'count_unit_id' => $this->kilogram->id,
                            'notes' => null,
                        ],
                    ],
                ],
                $finalized,
            ),
        )->toThrow(ValidationException::class);

        expect($finalized->refresh()->number)
            ->toBe('COUNT-IMMUTABLE')
            ->and(
                $finalized
                    ->lines()
                    ->sole()
                    ->counted_quantity,
            )
            ->toBe('1.000000');
    },
);

test(
    'stock count draft rejects cross tenant storage and inventory items',
    function () {
        $otherOrganization =
            Organization::factory()->create();

        $otherLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);

        $otherStorage =
            createStockCountStorageLocationForTest(
                $otherOrganization,
                $otherLocation,
                'OTHER',
            );

        $otherUnit = UnitOfMeasure::factory()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other gram',
            'symbol' => 'g',
            'dimension' => 'weight',
            'active' => true,
        ]);

        $otherItem = InventoryItem::factory()->create([
            'organization_id' => $otherOrganization->id,
            'base_unit_of_measure_id' => $otherUnit->id,
            'active' => true,
        ]);

        expect(
            fn () => app(SaveStockCount::class)->handle(
                $this->organization,
                $this->actor,
                [
                    'number' => 'COUNT-WRONG-STORAGE',
                    'location_id' => $this->location->id,
                    'storage_location_id' => $otherStorage->id,
                    'lines' => [
                        [
                            'inventory_item_id' => $this->inventoryItem->id,
                            'counted_quantity' => '1',
                            'count_unit_id' => $this->gram->id,
                            'notes' => null,
                        ],
                    ],
                ],
            ),
        )->toThrow(ValidationException::class);

        expect(
            fn () => app(SaveStockCount::class)->handle(
                $this->organization,
                $this->actor,
                [
                    'number' => 'COUNT-WRONG-ITEM',
                    'location_id' => $this->location->id,
                    'storage_location_id' => $this->storageLocation->id,
                    'lines' => [
                        [
                            'inventory_item_id' => $otherItem->id,
                            'counted_quantity' => '1',
                            'count_unit_id' => $this->gram->id,
                            'notes' => null,
                        ],
                    ],
                ],
            ),
        )->toThrow(ValidationException::class);

        expect(StockCount::query()->count())
            ->toBe(0);
    },
);

test(
    'variance report is tenant isolated and protects cost snapshots',
    function () {
        recordStockCountOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->gram,
        );

        $count = createSubmittedStockCountForTest(
            $this->organization,
            $this->actor,
            $this->location,
            $this->storageLocation,
            $this->inventoryItem,
            $this->kilogram,
            '1.2',
            'COUNT-REPORT',
        );

        app(FinalizeStockCount::class)->handle(
            $this->organization,
            $this->actor,
            $count,
        );

        $otherOrganization =
            Organization::factory()->create();

        $otherLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);

        $otherStorage =
            createStockCountStorageLocationForTest(
                $otherOrganization,
                $otherLocation,
                'REPORT',
            );

        $otherUnit = UnitOfMeasure::factory()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Piece',
            'symbol' => 'piece',
            'dimension' => 'count',
            'active' => true,
        ]);

        $otherItem = InventoryItem::factory()->create([
            'organization_id' => $otherOrganization->id,
            'base_unit_of_measure_id' => $otherUnit->id,
            'active' => true,
        ]);

        $otherCount = StockCount::query()->create([
            'organization_id' => $otherOrganization->id,
            'location_id' => $otherLocation->id,
            'storage_location_id' => $otherStorage->id,
            'number' => 'OTHER-COUNT',
            'status' => StockCountStatus::Finalized,
            'counted_at' => now(),
            'created_by' => null,
            'submitted_by' => null,
            'finalized_by' => null,
            'finalized_at' => now(),
        ]);

        $otherCount->lines()->create([
            'inventory_item_id' => $otherItem->id,
            'expected_base_quantity' => '1.000000',
            'counted_quantity' => '2.000000',
            'count_unit_id' => $otherUnit->id,
            'counted_base_quantity' => '2.000000',
            'variance_base_quantity' => '1.000000',
            'variance_unit_cost' => '10.0000',
            'variance_total_cost' => '10.0000',
            'notes' => null,
        ]);

        $date = now()
            ->setTimezone($this->organization->timezone)
            ->toDateString();

        $url = route(
            'stock-counts.variance',
            [
                'location_id' => $this->location->id,
                'from' => $date,
                'to' => $date,
            ],
        );

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get($url)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('stock-counts/variance')
                    ->has('rows', 1)
                    ->where(
                        'rows.0.countNumber',
                        'COUNT-REPORT',
                    )
                    ->where(
                        'rows.0.varianceBaseQuantity',
                        '200.000000',
                    )
                    ->where(
                        'rows.0.varianceUnitCost',
                        null,
                    )
                    ->where(
                        'rows.0.varianceTotalCost',
                        null,
                    )
                    ->where('canViewCosts', false),
            );

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get($url)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('stock-counts/variance')
                    ->has('rows', 1)
                    ->where(
                        'rows.0.countNumber',
                        'COUNT-REPORT',
                    )
                    ->where(
                        'rows.0.varianceUnitCost',
                        '0.2500',
                    )
                    ->where(
                        'rows.0.varianceTotalCost',
                        '50.0000',
                    )
                    ->where('canViewCosts', true),
            );
    },
);
