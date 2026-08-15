<?php

use App\Actions\Inventory\ReceiveStockTransfer;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\SaveStockTransfer;
use App\Actions\Inventory\ShipStockTransfer;
use App\Enums\InventoryItemType;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->owner = User::factory()->create();

    OrganizationMembership::factory()
        ->for($this->organization)
        ->for($this->owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $this->sourceLocation = Location::factory()
        ->for($this->organization)
        ->create([
            'name' => 'Source Restaurant',
            'code' => 'SOURCE',
            'active' => true,
        ]);

    $this->destinationLocation = Location::factory()
        ->for($this->organization)
        ->create([
            'name' => 'Destination Restaurant',
            'code' => 'DEST',
            'active' => true,
        ]);

    $this->sourceStorage = new StorageLocation([
        'name' => 'Source Storage',
        'code' => 'SOURCE',
        'active' => true,
    ]);

    $this->sourceStorage
        ->organization()
        ->associate($this->organization);

    $this->sourceStorage
        ->location()
        ->associate($this->sourceLocation);

    $this->sourceStorage->save();

    $this->destinationStorage = new StorageLocation([
        'name' => 'Destination Storage',
        'code' => 'DEST',
        'active' => true,
    ]);

    $this->destinationStorage
        ->organization()
        ->associate($this->organization);

    $this->destinationStorage
        ->location()
        ->associate($this->destinationLocation);

    $this->destinationStorage->save();

    $this->baseUnit = UnitOfMeasure::factory()
        ->for($this->organization)
        ->create([
            'name' => 'Gram',
            'symbol' => 'g',
            'dimension' => 'weight',
            'active' => true,
        ]);

    $this->inventoryItem = InventoryItem::factory()
        ->for($this->organization)
        ->create([
            'base_unit_of_measure_id' => $this->baseUnit->id,
            'name' => 'Transfer Ingredient',
            'sku' => 'TRANSFER-ITEM',
            'type' => InventoryItemType::Ingredient,
            'yield_percentage' => '100.00',
            'active' => true,
        ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->sourceLocation,
        storageLocation: $this->sourceStorage,
        inventoryItem: $this->inventoryItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '10.000000',
        baseUnitOfMeasure: $this->baseUnit,
        referenceType: 'stock_transfer_deactivation_test',
        referenceId: $this->inventoryItem->id,
        occurredAt: now()->subSecond(),
        actor: $this->owner,
        idempotencyKey: "stock-transfer-deactivation:opening:{$this->inventoryItem->id}",
        inboundUnitCost: '5.0000',
    );

    $this->stockTransfer = app(SaveStockTransfer::class)->handle(
        $this->organization,
        $this->owner,
        [
            'number' => 'TR-DEACTIVATION-001',
            'from_location_id' => $this->sourceLocation->id,
            'from_storage_location_id' => $this->sourceStorage->id,
            'to_location_id' => $this->destinationLocation->id,
            'to_storage_location_id' => $this->destinationStorage->id,
            'notes' => null,
            'lines' => [
                [
                    'inventory_item_id' => $this->inventoryItem->id,
                    'requested_quantity' => '4.000000',
                    'unit_id' => $this->baseUnit->id,
                ],
            ],
        ],
    );
});

test('in transit dependencies cannot be deactivated and the transfer remains receivable', function () {
    $transfer = app(ShipStockTransfer::class)->handle(
        $this->organization,
        $this->owner,
        $this->stockTransfer,
    );

    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'organizations.locations.update',
                [
                    $this->organization,
                    $this->destinationLocation,
                ],
            ),
            [
                'name' => $this->destinationLocation->name,
                'code' => $this->destinationLocation->code,
                'active' => false,
            ],
        )
        ->assertSessionHasErrors('active');

    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'organizations.locations.storage-locations.update',
                [
                    $this->organization,
                    $this->destinationLocation,
                    $this->destinationStorage,
                ],
            ),
            [
                'name' => $this->destinationStorage->name,
                'code' => $this->destinationStorage->code,
                'active' => false,
            ],
        )
        ->assertSessionHasErrors('active');

    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'inventory.items.update',
                $this->inventoryItem,
            ),
            [
                'name' => $this->inventoryItem->name,
                'sku' => $this->inventoryItem->sku,
                'base_unit_of_measure_id' => $this->baseUnit->id,
                'type' => InventoryItemType::Ingredient->value,
                'yield_percentage' => '100.00',
                'active' => false,
            ],
        )
        ->assertSessionHasErrors('active');

    expect($this->destinationLocation->refresh()->active)
        ->toBeTrue()
        ->and($this->destinationStorage->refresh()->active)
        ->toBeTrue()
        ->and($this->inventoryItem->refresh()->active)
        ->toBeTrue();

    $line = $transfer->lines()->sole();

    $received = app(ReceiveStockTransfer::class)->handle(
        $this->organization,
        $this->owner,
        $transfer,
        [
            'lines' => [
                [
                    'id' => $line->id,
                    'received_base_quantity' => $line->shipped_base_quantity,
                ],
            ],
        ],
    );

    expect($received->status)
        ->toBe(StockTransferStatus::Received);

    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'inventory.items.update',
                $this->inventoryItem,
            ),
            [
                'name' => $this->inventoryItem->name,
                'sku' => $this->inventoryItem->sku,
                'base_unit_of_measure_id' => $this->baseUnit->id,
                'type' => InventoryItemType::Ingredient->value,
                'yield_percentage' => '100.00',
                'active' => false,
            ],
        )
        ->assertRedirect(
            route(
                'inventory.items.edit',
                $this->inventoryItem,
            ),
        );

    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'organizations.locations.storage-locations.update',
                [
                    $this->organization,
                    $this->destinationLocation,
                    $this->destinationStorage,
                ],
            ),
            [
                'name' => $this->destinationStorage->name,
                'code' => $this->destinationStorage->code,
                'active' => false,
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.storage-locations.index',
                [
                    $this->organization,
                    $this->destinationLocation,
                ],
            ),
        );

    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'organizations.locations.update',
                [
                    $this->organization,
                    $this->destinationLocation,
                ],
            ),
            [
                'name' => $this->destinationLocation->name,
                'code' => $this->destinationLocation->code,
                'active' => false,
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.index',
                $this->organization,
            ),
        );

    expect($this->inventoryItem->refresh()->active)
        ->toBeFalse()
        ->and($this->destinationStorage->refresh()->active)
        ->toBeFalse()
        ->and($this->destinationLocation->refresh()->active)
        ->toBeFalse();
});

test('shipped transfer does not prevent source deactivation after stock has left', function () {
    $transfer = app(ShipStockTransfer::class)->handle(
        $this->organization,
        $this->owner,
        $this->stockTransfer,
    );

    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'organizations.locations.storage-locations.update',
                [
                    $this->organization,
                    $this->sourceLocation,
                    $this->sourceStorage,
                ],
            ),
            [
                'name' => $this->sourceStorage->name,
                'code' => $this->sourceStorage->code,
                'active' => false,
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.storage-locations.index',
                [
                    $this->organization,
                    $this->sourceLocation,
                ],
            ),
        );

    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'organizations.locations.update',
                [
                    $this->organization,
                    $this->sourceLocation,
                ],
            ),
            [
                'name' => $this->sourceLocation->name,
                'code' => $this->sourceLocation->code,
                'active' => false,
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.index',
                $this->organization,
            ),
        );

    expect($this->sourceStorage->refresh()->active)
        ->toBeFalse()
        ->and($this->sourceLocation->refresh()->active)
        ->toBeFalse();

    $line = $transfer->lines()->sole();

    $received = app(ReceiveStockTransfer::class)->handle(
        $this->organization,
        $this->owner,
        $transfer,
        [
            'lines' => [
                [
                    'id' => $line->id,
                    'received_base_quantity' => $line->shipped_base_quantity,
                ],
            ],
        ],
    );

    expect($received->status)
        ->toBe(StockTransferStatus::Received);
});

test('draft transfer cannot be shipped after its destination location is deactivated', function () {
    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'organizations.locations.update',
                [
                    $this->organization,
                    $this->destinationLocation,
                ],
            ),
            [
                'name' => $this->destinationLocation->name,
                'code' => $this->destinationLocation->code,
                'active' => false,
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.index',
                $this->organization,
            ),
        );

    try {
        app(ShipStockTransfer::class)->handle(
            $this->organization,
            $this->owner,
            $this->stockTransfer,
        );

        $this->fail(
            'Shipping should reject an inactive destination location.',
        );
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('to_location_id');
    }

    expect($this->stockTransfer->refresh()->status)
        ->toBe(StockTransferStatus::Draft);

    expect(
        StockMovement::query()
            ->where(
                'organization_id',
                $this->organization->id,
            )
            ->where(
                'type',
                StockMovementType::TransferOut->value,
            )
            ->count(),
    )->toBe(0);
});

test('draft transfer cannot be shipped after its destination storage is deactivated', function () {
    $this->withSession([
        'active_organization_id' => $this->organization->id,
    ])
        ->actingAs($this->owner)
        ->put(
            route(
                'organizations.locations.storage-locations.update',
                [
                    $this->organization,
                    $this->destinationLocation,
                    $this->destinationStorage,
                ],
            ),
            [
                'name' => $this->destinationStorage->name,
                'code' => $this->destinationStorage->code,
                'active' => false,
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.storage-locations.index',
                [
                    $this->organization,
                    $this->destinationLocation,
                ],
            ),
        );

    try {
        app(ShipStockTransfer::class)->handle(
            $this->organization,
            $this->owner,
            $this->stockTransfer,
        );

        $this->fail(
            'Shipping should reject an inactive destination storage location.',
        );
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('to_storage_location_id');
    }

    expect($this->stockTransfer->refresh()->status)
        ->toBe(StockTransferStatus::Draft);

    expect(
        StockMovement::query()
            ->where(
                'organization_id',
                $this->organization->id,
            )
            ->where(
                'type',
                StockMovementType::TransferOut->value,
            )
            ->count(),
    )->toBe(0);
});
