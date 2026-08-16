<?php

use App\Actions\Inventory\ReceiveStockTransfer;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\SaveStockTransfer;
use App\Actions\Inventory\ShipStockTransfer;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Create active storage for receipt replay tests.
 */
function createReceiptReplayStorageForTest(
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
 * Record source stock through the authoritative ledger boundary.
 */
function recordReceiptReplayOpeningBalanceForTest(
    Organization $organization,
    Location $location,
    StorageLocation $storage,
    InventoryItem $inventoryItem,
    UnitOfMeasure $baseUnit,
    string $unitCost,
    string $key,
): void {
    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storage,
        inventoryItem: $inventoryItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '1000',
        baseUnitOfMeasure: $baseUnit,
        referenceType: 'opening_balance',
        referenceId: $inventoryItem->id,
        occurredAt: now()->subMinute(),
        idempotencyKey: $key,
        inboundUnitCost: $unitCost,
    );
}

/**
 * Capture receipt state that must remain unchanged across safe replays and rejected conflicts.
 *
 * @return array{
 *     movement_count: int,
 *     inbound_movement_count: int,
 *     receipt_audit_count: int,
 *     balance_quantities: array<string, string>,
 *     line_variances: array<int, string|null>,
 *     received_at: string|null,
 *     received_by: int|null
 * }
 */
function receiptReplayStateForTest(
    Organization $organization,
    StockTransfer $transfer,
): array {
    $balanceQuantities = StockBalance::query()
        ->where('organization_id', $organization->id)
        ->orderBy('storage_location_id')
        ->orderBy('inventory_item_id')
        ->get()
        ->mapWithKeys(
            static fn (StockBalance $balance): array => [
                "{$balance->storage_location_id}:{$balance->inventory_item_id}" => $balance->quantity_on_hand,
            ],
        )
        ->all();

    $lineVariances = StockTransferLine::query()
        ->where('stock_transfer_id', $transfer->id)
        ->orderBy('id')
        ->get()
        ->mapWithKeys(
            static fn (StockTransferLine $line): array => [
                $line->id => $line->variance_base_quantity,
            ],
        )
        ->all();

    $freshTransfer = $transfer->fresh();

    return [
        'movement_count' => StockMovement::query()
            ->where('organization_id', $organization->id)
            ->count(),
        'inbound_movement_count' => StockMovement::query()
            ->where('organization_id', $organization->id)
            ->where('type', StockMovementType::TransferIn->value)
            ->count(),
        'receipt_audit_count' => AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('action', 'stock_transfer.received')
            ->count(),
        'balance_quantities' => $balanceQuantities,
        'line_variances' => $lineVariances,
        'received_at' => $freshTransfer?->received_at?->toIso8601String(),
        'received_by' => $freshTransfer?->received_by,
    ];
}

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $this->fromLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Branch A',
        'active' => true,
    ]);

    $this->toLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Branch B',
        'active' => true,
    ]);

    $this->fromStorage = createReceiptReplayStorageForTest(
        $this->organization,
        $this->fromLocation,
        'REPLAY-FROM',
    );

    $this->toStorage = createReceiptReplayStorageForTest(
        $this->organization,
        $this->toLocation,
        'REPLAY-TO',
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

    $this->firstItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->gram->id,
        'name' => 'Receipt Replay Item A',
        'sku' => 'RECEIPT-REPLAY-A',
        'active' => true,
    ]);

    $this->secondItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->gram->id,
        'name' => 'Receipt Replay Item B',
        'sku' => 'RECEIPT-REPLAY-B',
        'active' => true,
    ]);

    $this->actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    recordReceiptReplayOpeningBalanceForTest(
        $this->organization,
        $this->fromLocation,
        $this->fromStorage,
        $this->firstItem,
        $this->gram,
        '0.2500',
        'receipt-replay:first-opening',
    );

    recordReceiptReplayOpeningBalanceForTest(
        $this->organization,
        $this->fromLocation,
        $this->fromStorage,
        $this->secondItem,
        $this->gram,
        '0.4000',
        'receipt-replay:second-opening',
    );

    $draft = app(SaveStockTransfer::class)->handle(
        $this->organization,
        $this->actor,
        [
            'number' => 'TR-RECEIPT-REPLAY',
            'from_location_id' => $this->fromLocation->id,
            'from_storage_location_id' => $this->fromStorage->id,
            'to_location_id' => $this->toLocation->id,
            'to_storage_location_id' => $this->toStorage->id,
            'notes' => null,
            'lines' => [
                [
                    'inventory_item_id' => $this->firstItem->id,
                    'requested_quantity' => '0.5',
                    'unit_id' => $this->kilogram->id,
                ],
                [
                    'inventory_item_id' => $this->secondItem->id,
                    'requested_quantity' => '0.5',
                    'unit_id' => $this->kilogram->id,
                ],
            ],
        ],
    );

    $this->transfer = app(ShipStockTransfer::class)->handle(
        $this->organization,
        $this->actor,
        $draft,
    );

    $this->firstLine = $this->transfer
        ->lines()
        ->where('inventory_item_id', $this->firstItem->id)
        ->sole();

    $this->secondLine = $this->transfer
        ->lines()
        ->where('inventory_item_id', $this->secondItem->id)
        ->sole();

    $this->receiptAttributes = [
        'lines' => [
            [
                'id' => $this->firstLine->id,
                'received_base_quantity' => '400',
            ],
            [
                'id' => $this->secondLine->id,
                'received_base_quantity' => '500',
            ],
        ],
    ];
});

test(
    'exact receipt replay accepts the same normalized line set without side effects',
    function () {
        $action = app(ReceiveStockTransfer::class);

        $received = $action->handle(
            $this->organization,
            $this->actor,
            $this->transfer,
            $this->receiptAttributes,
        );

        $beforeReplay = receiptReplayStateForTest(
            $this->organization,
            $received,
        );

        $replayed = $action->handle(
            $this->organization,
            $this->actor,
            $received,
            [
                'lines' => [
                    [
                        'id' => $this->secondLine->id,
                        'received_base_quantity' => '500.000000',
                    ],
                    [
                        'id' => $this->firstLine->id,
                        'received_base_quantity' => '400.0',
                    ],
                ],
            ],
        );

        expect($replayed->status)
            ->toBe(StockTransferStatus::Received)
            ->and(
                receiptReplayStateForTest(
                    $this->organization,
                    $replayed,
                ),
            )
            ->toBe($beforeReplay);
    },
);

test(
    'receipt replay rejects a changed quantity without changing receipt evidence',
    function () {
        $action = app(ReceiveStockTransfer::class);

        $received = $action->handle(
            $this->organization,
            $this->actor,
            $this->transfer,
            $this->receiptAttributes,
        );

        $beforeConflict = receiptReplayStateForTest(
            $this->organization,
            $received,
        );

        expect(
            fn () => $action->handle(
                $this->organization,
                $this->actor,
                $received,
                [
                    'lines' => [
                        [
                            'id' => $this->firstLine->id,
                            'received_base_quantity' => '401',
                        ],
                        [
                            'id' => $this->secondLine->id,
                            'received_base_quantity' => '500',
                        ],
                    ],
                ],
            ),
        )->toThrow(ValidationException::class);

        expect(
            receiptReplayStateForTest(
                $this->organization,
                $received,
            ),
        )->toBe($beforeConflict);
    },
);

test(
    'receipt replay rejects missing and extra line payloads without side effects',
    function () {
        $action = app(ReceiveStockTransfer::class);

        $received = $action->handle(
            $this->organization,
            $this->actor,
            $this->transfer,
            $this->receiptAttributes,
        );

        $beforeConflicts = receiptReplayStateForTest(
            $this->organization,
            $received,
        );

        expect(
            fn () => $action->handle(
                $this->organization,
                $this->actor,
                $received,
                [
                    'lines' => [
                        [
                            'id' => $this->firstLine->id,
                            'received_base_quantity' => '400',
                        ],
                    ],
                ],
            ),
        )->toThrow(ValidationException::class);

        expect(
            receiptReplayStateForTest(
                $this->organization,
                $received,
            ),
        )->toBe($beforeConflicts);

        expect(
            fn () => $action->handle(
                $this->organization,
                $this->actor,
                $received,
                [
                    'lines' => [
                        [
                            'id' => $this->firstLine->id,
                            'received_base_quantity' => '400',
                        ],
                        [
                            'id' => $this->secondLine->id,
                            'received_base_quantity' => '500',
                        ],
                        [
                            'id' => 999999999,
                            'received_base_quantity' => '1',
                        ],
                    ],
                ],
            ),
        )->toThrow(ValidationException::class);

        expect(
            receiptReplayStateForTest(
                $this->organization,
                $received,
            ),
        )->toBe($beforeConflicts);
    },
);

test(
    'receipt replay remains tenant isolated',
    function () {
        $action = app(ReceiveStockTransfer::class);

        $received = $action->handle(
            $this->organization,
            $this->actor,
            $this->transfer,
            $this->receiptAttributes,
        );

        $beforeCrossTenantAttempt = receiptReplayStateForTest(
            $this->organization,
            $received,
        );

        $otherOrganization = Organization::factory()->create();
        $otherActor = User::factory()->create();

        OrganizationMembership::factory()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherActor->id,
            'role' => OrganizationRole::InventoryStaff,
        ]);

        expect(
            fn () => $action->handle(
                $otherOrganization,
                $otherActor,
                $received,
                $this->receiptAttributes,
            ),
        )->toThrow(ModelNotFoundException::class);

        expect(
            receiptReplayStateForTest(
                $this->organization,
                $received,
            ),
        )->toBe($beforeCrossTenantAttempt);
    },
);
