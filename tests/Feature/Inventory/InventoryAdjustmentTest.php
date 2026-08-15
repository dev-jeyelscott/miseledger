<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Create active storage for adjustment tests.
 */
function createAdjustmentStorageForTest(
    Organization $organization,
    Location $location,
    string $code = 'MAIN',
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
 * Seed authoritative stock before testing adjustments.
 */
function recordAdjustmentOpeningBalanceForTest(
    Organization $organization,
    Location $location,
    StorageLocation $storage,
    InventoryItem $item,
    UnitOfMeasure $baseUnit,
    string $quantity = '10',
    string $unitCost = '2',
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
        idempotencyKey: "adjustment-test:opening:{$item->id}:{$storage->id}",
        inboundUnitCost: $unitCost,
    );
}

/**
 * Build one valid adjustment payload.
 *
 * @return array<string, mixed>
 */
function adjustmentPayloadForTest(
    Organization $organization,
    Location $location,
    StorageLocation $storage,
    InventoryItem $item,
    UnitOfMeasure $unit,
    string $quantity = '2',
    ?string $operationId = null,
    string $reason = 'Cycle count correction',
): array {
    return [
        'operation_id' => $operationId ?? (string) Str::uuid(),
        'location_id' => $location->id,
        'storage_location_id' => $storage->id,
        'inventory_item_id' => $item->id,
        'quantity' => $quantity,
        'unit_id' => $unit->id,
        'reason' => $reason,
        'occurred_at' => now()
            ->setTimezone($organization->timezone)
            ->format('Y-m-d\TH:i'),
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

    $this->storage = createAdjustmentStorageForTest(
        $this->organization,
        $this->location,
    );

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Unit',
        'symbol' => 'ea',
        'dimension' => 'count',
        'active' => true,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'name' => 'Widget',
        'sku' => 'WIDGET',
        'active' => true,
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
    'a user without inventory.adjust permission is rejected',
    function () {
        recordAdjustmentOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->unit,
        );

        $this
            ->actingAs($this->kitchenUser)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(
                route('inventory.adjustments.store'),
                adjustmentPayloadForTest(
                    $this->organization,
                    $this->location,
                    $this->storage,
                    $this->item,
                    $this->unit,
                ),
            )
            ->assertForbidden();

        expect(StockMovement::query()->count())->toBe(1);
    },
);

test(
    'reason is required',
    function () {
        recordAdjustmentOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->unit,
        );

        $payload = adjustmentPayloadForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->unit,
        );
        $payload['reason'] = '';

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->from(route('inventory.adjustments.create'))
            ->post(
                route('inventory.adjustments.store'),
                $payload,
            )
            ->assertSessionHasErrors('reason');
    },
);

test(
    'positive and negative adjustments update the stock ledger and audit log',
    function () {
        recordAdjustmentOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->unit,
        );

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(
                route('inventory.adjustments.store'),
                adjustmentPayloadForTest(
                    $this->organization,
                    $this->location,
                    $this->storage,
                    $this->item,
                    $this->unit,
                    quantity: '3',
                    reason: 'Found sealed stock during recount',
                ),
            )
            ->assertRedirect(route('inventory.items.index'));

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(
                route('inventory.adjustments.store'),
                adjustmentPayloadForTest(
                    $this->organization,
                    $this->location,
                    $this->storage,
                    $this->item,
                    $this->unit,
                    quantity: '-2',
                    reason: 'Corrected duplicate count',
                ),
            )
            ->assertRedirect(route('inventory.items.index'));

        $balance = StockBalance::query()->sole();

        expect($balance->quantity_on_hand)
            ->toBe('11.000000')
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::ManualAdjustment->value,
                    )
                    ->count(),
            )
            ->toBe(2)
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'inventory.manual_adjustment',
                    )
                    ->count(),
            )
            ->toBe(2);
    },
);

test(
    'an adjustment that would create negative stock is rejected',
    function () {
        recordAdjustmentOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->unit,
            quantity: '5',
        );

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->from(route('inventory.adjustments.create'))
            ->post(
                route('inventory.adjustments.store'),
                adjustmentPayloadForTest(
                    $this->organization,
                    $this->location,
                    $this->storage,
                    $this->item,
                    $this->unit,
                    quantity: '-10',
                ),
            )
            ->assertSessionHasErrors('quantity');

        expect(
            StockBalance::query()
                ->sole()
                ->quantity_on_hand,
        )
            ->toBe('5.000000')
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::ManualAdjustment->value,
                    )
                    ->count(),
            )
            ->toBe(0)
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'inventory.manual_adjustment',
                    )
                    ->count(),
            )
            ->toBe(0);
    },
);

test(
    'retrying the same adjustment operation id does not duplicate the movement or audit entry',
    function () {
        recordAdjustmentOpeningBalanceForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->unit,
        );

        $operationId = (string) Str::uuid();

        $payload = adjustmentPayloadForTest(
            $this->organization,
            $this->location,
            $this->storage,
            $this->item,
            $this->unit,
            quantity: '4',
            operationId: $operationId,
        );

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(route('inventory.adjustments.store'), $payload)
            ->assertRedirect(route('inventory.items.index'));

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(route('inventory.adjustments.store'), $payload)
            ->assertRedirect(route('inventory.items.index'));

        expect(
            StockMovement::query()
                ->where(
                    'type',
                    StockMovementType::ManualAdjustment->value,
                )
                ->count(),
        )
            ->toBe(1)
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'inventory.manual_adjustment',
                    )
                    ->count(),
            )
            ->toBe(1)
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('14.000000');
    },
);
