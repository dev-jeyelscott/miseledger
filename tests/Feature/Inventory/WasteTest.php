<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\RecordWaste;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use App\Models\WasteRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create active storage for waste tests.
 */
function createWasteStorageForTest(
    Organization $organization,
    Location $location,
    string $code = 'WASTE',
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
 * Seed authoritative stock before testing outbound waste.
 */
function recordWasteOpeningBalanceForTest(
    Organization $organization,
    Location $location,
    StorageLocation $storage,
    InventoryItem $item,
    UnitOfMeasure $baseUnit,
    string $quantity = '1000',
    string $unitCost = '0.25',
): void {
    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storage,
        inventoryItem: $item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: $quantity,
        baseUnitOfMeasure: $baseUnit,
        referenceType: 'opening_balance',
        referenceId: $item->id,
        occurredAt: now()->subHour(),
        idempotencyKey: "waste-test:opening:{$item->id}:{$storage->id}",
        inboundUnitCost: $unitCost,
    );
}

/**
 * Build one valid waste operation payload.
 *
 * @return array<string, mixed>
 */
function wastePayloadForTest(
    Organization $organization,
    Location $location,
    StorageLocation $storage,
    InventoryItem $item,
    WasteReason $reason,
    UnitOfMeasure $unit,
    string $quantity = '0.5',
    ?string $operationId = null,
): array {
    return [
        'operation_id' => $operationId ?? (string) Str::uuid(),
        'location_id' => $location->id,
        'storage_location_id' => $storage->id,
        'inventory_item_id' => $item->id,
        'waste_reason_id' => $reason->id,
        'quantity' => $quantity,
        'unit_id' => $unit->id,
        'occurred_at' => now()
            ->setTimezone($organization->timezone)
            ->format('Y-m-d\TH:i'),
        'notes' => 'Kitchen waste test',
    ];
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

    $this->storage = createWasteStorageForTest(
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

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->gram->id,
        'name' => 'Chicken',
        'sku' => 'CHICKEN',
        'active' => true,
    ]);

    $this->reason = WasteReason::query()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Spoilage',
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

    $this->kitchenUser = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->kitchenUser->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);
});

test(
    'waste reason names are unique per organization and may be deactivated',
    function () {
        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(
                route('waste-reasons.store'),
                ['name' => 'Expired'],
            )
            ->assertRedirect(route('waste.index'));

        $this->assertDatabaseHas('waste_reasons', [
            'organization_id' => $this->organization->id,
            'name' => 'Expired',
            'active' => true,
        ]);

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->from(route('waste.index'))
            ->post(
                route('waste-reasons.store'),
                ['name' => 'Expired'],
            )
            ->assertSessionHasErrors('name');

        $expired = WasteReason::query()
            ->where(
                'organization_id',
                $this->organization->id,
            )
            ->where('name', 'Expired')
            ->sole();

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->put(
                route('waste-reasons.update', $expired->id),
                ['active' => false],
            )
            ->assertRedirect(route('waste.index'));

        expect($expired->refresh()->active)->toBeFalse();

        $this
            ->actingAs($this->kitchenUser)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(
                route('waste-reasons.store'),
                ['name' => 'Unauthorized reason'],
            )
            ->assertForbidden();
    },
);

test(
    'waste form exposes location containment and converter backed item units',
    function () {
        $annexLocation = Location::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Annex',
            'active' => true,
        ]);

        $annexStorage = createWasteStorageForTest(
            $this->organization,
            $annexLocation,
            'ANNEX',
        );

        $bottle = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bottle',
            'symbol' => 'bottle',
            'dimension' => 'count',
            'active' => true,
        ]);

        $case = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Case',
            'symbol' => 'case',
            'dimension' => 'count',
            'active' => true,
        ]);

        $pack = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Pack',
            'symbol' => 'pack',
            'dimension' => 'count',
            'active' => true,
        ]);

        $cola = InventoryItem::factory()->create([
            'organization_id' => $this->organization->id,
            'base_unit_of_measure_id' => $bottle->id,
            'name' => 'Cola',
            'sku' => 'COLA',
            'active' => true,
        ]);

        InventoryItemUnit::factory()->create([
            'inventory_item_id' => $cola->id,
            'unit_of_measure_id' => $case->id,
            'quantity_in_base_unit' => '24.000000',
            'active' => true,
        ]);

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('waste.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('waste/index')
                    ->has('recordForm.storageLocationOptions', 2)
                    ->where(
                        'recordForm.storageLocationOptions.0.id',
                        $annexStorage->id,
                    )
                    ->where(
                        'recordForm.storageLocationOptions.0.locationId',
                        $annexLocation->id,
                    )
                    ->where(
                        'recordForm.storageLocationOptions.1.id',
                        $this->storage->id,
                    )
                    ->where(
                        'recordForm.storageLocationOptions.1.locationId',
                        $this->location->id,
                    )
                    ->has('recordForm.inventoryItemOptions', 2)
                    ->where(
                        'recordForm.inventoryItemOptions.0.id',
                        $this->item->id,
                    )
                    ->where(
                        'recordForm.inventoryItemOptions.0.validUnitIds',
                        [
                            $this->gram->id,
                            $this->kilogram->id,
                        ],
                    )
                    ->where(
                        'recordForm.inventoryItemOptions.1.id',
                        $cola->id,
                    )
                    ->where(
                        'recordForm.inventoryItemOptions.1.validUnitIds',
                        [
                            $bottle->id,
                            $case->id,
                        ],
                    ),
            );

        expect($pack->exists)->toBeTrue();
    },
);

test(
    'waste request rejects storage outside the selected location',
    function () {
        $otherLocation = Location::factory()->create([
            'organization_id' => $this->organization->id,
            'active' => true,
        ]);

        $otherStorage = createWasteStorageForTest(
            $this->organization,
            $otherLocation,
            'OTHER',
        );

        $payload = wastePayloadForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->reason,
            $this->kilogram,
        );

        $payload['storage_location_id'] = $otherStorage->id;

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->from(route('waste.index'))
            ->post(route('waste.store'), $payload)
            ->assertRedirect(route('waste.index'))
            ->assertSessionHasErrors('storage_location_id');

        expect(WasteRecord::query()->count())
            ->toBe(0)
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::Waste->value,
                    )
                    ->count(),
            )
            ->toBe(0);
    },
);

test(
    'waste request rejects a unit configured for another inventory item',
    function () {
        $portion = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Portion',
            'symbol' => 'portion',
            'dimension' => 'weight',
            'active' => true,
        ]);

        $otherItem = InventoryItem::factory()->create([
            'organization_id' => $this->organization->id,
            'base_unit_of_measure_id' => $this->gram->id,
            'name' => 'Beef',
            'sku' => 'BEEF',
            'active' => true,
        ]);

        InventoryItemUnit::factory()->create([
            'inventory_item_id' => $otherItem->id,
            'unit_of_measure_id' => $portion->id,
            'quantity_in_base_unit' => '100.000000',
            'active' => true,
        ]);

        $payload = wastePayloadForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->reason,
            $portion,
        );

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->from(route('waste.index'))
            ->post(route('waste.store'), $payload)
            ->assertRedirect(route('waste.index'))
            ->assertSessionHasErrors('unit');

        expect(WasteRecord::query()->count())
            ->toBe(0)
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::Waste->value,
                    )
                    ->count(),
            )
            ->toBe(0);
    },
);

test(
    'recording waste converts to base quantity snapshots current cost and decreases stock',
    function () {
        recordWasteOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->gram,
        );

        $record = app(RecordWaste::class)->handle(
            $this->organization,
            $this->actor,
            wastePayloadForTest(
                $this->organization,
                $this->location,
                $this->storage,
                $this->item,
                $this->reason,
                $this->kilogram,
            ),
        );

        $movement = StockMovement::query()
            ->where(
                'type',
                StockMovementType::Waste->value,
            )
            ->sole();

        expect($record->quantity)
            ->toBe('0.500000')
            ->and($record->base_quantity)
            ->toBe('500.000000')
            ->and($record->unit_cost)
            ->toBe('0.2500')
            ->and($record->total_cost)
            ->toBe('125.0000')
            ->and($record->recorded_by)
            ->toBe($this->actor->id)
            ->and($movement->quantity)
            ->toBe('-500.000000')
            ->and($movement->unit_cost)
            ->toBe('0.2500')
            ->and($movement->total_cost)
            ->toBe('125.0000')
            ->and($movement->reference_type)
            ->toBe('waste_record')
            ->and($movement->reference_id)
            ->toBe($record->id)
            ->and($movement->idempotency_key)
            ->toBe("waste:{$record->id}")
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('500.000000')
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'waste.recorded',
                    )
                    ->count(),
            )
            ->toBe(1);
    },
);

test(
    'waste creation is idempotent for the same business operation',
    function () {
        recordWasteOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->gram,
        );

        $operationId = (string) Str::uuid();

        $payload = wastePayloadForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->reason,
            $this->kilogram,
            operationId: $operationId,
        );

        $recordWaste = app(RecordWaste::class);

        $first = $recordWaste->handle(
            $this->organization,
            $this->actor,
            $payload,
        );

        $second = $recordWaste->handle(
            $this->organization,
            $this->actor,
            $payload,
        );

        expect($second->id)
            ->toBe($first->id)
            ->and(WasteRecord::query()->count())
            ->toBe(1)
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::Waste->value,
                    )
                    ->count(),
            )
            ->toBe(1)
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'waste.recorded',
                    )
                    ->count(),
            )
            ->toBe(1)
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('500.000000');

        $changedPayload = $payload;
        $changedPayload['quantity'] = '0.25';

        expect(
            fn () => $recordWaste->handle(
                $this->organization,
                $this->actor,
                $changedPayload,
            ),
        )->toThrow(ValidationException::class);
    },
);

test(
    'negative stock policy rolls back waste evidence and movement',
    function () {
        recordWasteOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->gram,
            quantity: '100',
        );

        expect(
            fn () => app(RecordWaste::class)->handle(
                $this->organization,
                $this->actor,
                wastePayloadForTest(
                    $this->organization,
                    $this->location,
                    $this->storage,
                    $this->item,
                    $this->reason,
                    $this->kilogram,
                ),
            ),
        )->toThrow(ValidationException::class);

        expect(WasteRecord::query()->count())
            ->toBe(0)
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::Waste->value,
                    )
                    ->count(),
            )
            ->toBe(0)
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('100.000000')
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'waste.recorded',
                    )
                    ->count(),
            )
            ->toBe(0);
    },
);

test(
    'waste rejects cross tenant reason evidence',
    function () {
        recordWasteOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->gram,
        );

        $otherOrganization =
            Organization::factory()->create();

        $otherReason = WasteReason::query()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other tenant reason',
            'active' => true,
        ]);

        $payload = wastePayloadForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->reason,
            $this->kilogram,
        );

        $payload['waste_reason_id'] = $otherReason->id;

        expect(
            fn () => app(RecordWaste::class)->handle(
                $this->organization,
                $this->actor,
                $payload,
            ),
        )->toThrow(ValidationException::class);

        expect(WasteRecord::query()->count())
            ->toBe(0);
    },
);

test(
    'waste report paginates newest first and preserves active filters',
    function () {
        $reportMoment = now()
            ->setTimezone($this->organization->timezone)
            ->startOfDay()
            ->addHours(12);

        $records = [];

        for ($index = 0; $index < 26; $index++) {
            $records[] = WasteRecord::query()->create([
                'organization_id' => $this->organization->id,
                'location_id' => $this->location->id,
                'storage_location_id' => $this->storage->id,
                'inventory_item_id' => $this->item->id,
                'waste_reason_id' => $this->reason->id,
                'operation_id' => (string) Str::uuid(),
                'quantity' => '1.000000',
                'unit_id' => $this->gram->id,
                'base_quantity' => '1.000000',
                'unit_cost' => '0.2500',
                'total_cost' => '0.2500',
                'occurred_at' => $reportMoment
                    ->copy()
                    ->subSeconds($index),
                'recorded_by' => $this->actor->id,
                'notes' => null,
            ]);
        }

        $date = $reportMoment->toDateString();

        $filters = [
            'location_id' => $this->location->id,
            'inventory_item_id' => $this->item->id,
            'waste_reason_id' => $this->reason->id,
            'from' => $date,
            'to' => $date,
        ];

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('waste.index', $filters))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('waste/index')
                    ->where('rows.current_page', 1)
                    ->where('rows.last_page', 2)
                    ->where('rows.per_page', 25)
                    ->where('rows.total', 26)
                    ->has('rows.data', 25)
                    ->where(
                        'rows.data.0.recordId',
                        $records[0]->id,
                    )
                    ->where(
                        'rows.data.24.recordId',
                        $records[24]->id,
                    )
                    ->where(
                        'rows.next_page_url',
                        fn (?string $nextPageUrl): bool => $nextPageUrl !== null
                            && str_contains(
                                $nextPageUrl,
                                "location_id={$this->location->id}",
                            )
                            && str_contains(
                                $nextPageUrl,
                                "inventory_item_id={$this->item->id}",
                            )
                            && str_contains(
                                $nextPageUrl,
                                "waste_reason_id={$this->reason->id}",
                            )
                            && str_contains(
                                $nextPageUrl,
                                "from={$date}",
                            )
                            && str_contains(
                                $nextPageUrl,
                                "to={$date}",
                            )
                            && str_contains(
                                $nextPageUrl,
                                'page=2',
                            ),
                    ),
            );

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('waste.index', [
                ...$filters,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('waste/index')
                    ->where('rows.current_page', 2)
                    ->where('rows.last_page', 2)
                    ->where('rows.total', 26)
                    ->has('rows.data', 1)
                    ->where(
                        'rows.data.0.recordId',
                        $records[25]->id,
                    )
                    ->where(
                        'filters.locationId',
                        $this->location->id,
                    )
                    ->where(
                        'filters.inventoryItemId',
                        $this->item->id,
                    )
                    ->where(
                        'filters.wasteReasonId',
                        $this->reason->id,
                    )
                    ->where('filters.from', $date)
                    ->where('filters.to', $date),
            );
    },
);

test(
    'waste report is tenant isolated and protects cost snapshots',
    function () {
        recordWasteOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->gram,
        );

        $record = app(RecordWaste::class)->handle(
            $this->organization,
            $this->actor,
            wastePayloadForTest(
                $this->organization,
                $this->location,
                $this->storage,
                $this->item,
                $this->reason,
                $this->kilogram,
            ),
        );

        $otherOrganization =
            Organization::factory()->create();

        $otherLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);

        $otherStorage = createWasteStorageForTest(
            $otherOrganization,
            $otherLocation,
            'OTHER',
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

        $otherReason = WasteReason::query()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other waste',
            'active' => true,
        ]);

        WasteRecord::query()->create([
            'organization_id' => $otherOrganization->id,
            'location_id' => $otherLocation->id,
            'storage_location_id' => $otherStorage->id,
            'inventory_item_id' => $otherItem->id,
            'waste_reason_id' => $otherReason->id,
            'operation_id' => (string) Str::uuid(),
            'quantity' => '1.000000',
            'unit_id' => $otherUnit->id,
            'base_quantity' => '1.000000',
            'unit_cost' => '100.0000',
            'total_cost' => '100.0000',
            'occurred_at' => now(),
            'recorded_by' => null,
            'notes' => null,
        ]);

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('waste.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('waste/index')
                    ->where('rows.total', 1)
                    ->has('rows.data', 1)
                    ->where(
                        'rows.data.0.recordId',
                        $record->id,
                    )
                    ->where(
                        'rows.data.0.baseQuantity',
                        '500.000000',
                    )
                    ->where(
                        'rows.data.0.unitCost',
                        null,
                    )
                    ->where(
                        'rows.data.0.totalCost',
                        null,
                    )
                    ->where('canViewCosts', false),
            );

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(route('waste.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('waste/index')
                    ->where('rows.total', 1)
                    ->has('rows.data', 1)
                    ->where(
                        'rows.data.0.recordId',
                        $record->id,
                    )
                    ->where(
                        'rows.data.0.unitCost',
                        '0.2500',
                    )
                    ->where(
                        'rows.data.0.totalCost',
                        '125.0000',
                    )
                    ->where('canViewCosts', true),
            );
    },
);
