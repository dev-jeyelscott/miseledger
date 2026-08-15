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
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create active storage for stock-transfer tests.
 */
function createTransferStorageForTest(
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
 * Record an authoritative opening balance for one transfer endpoint.
 */
function recordTransferOpeningBalanceForTest(
    Organization $organization,
    Location $location,
    StorageLocation $storage,
    InventoryItem $inventoryItem,
    UnitOfMeasure $baseUnit,
    string $quantity,
    string $unitCost,
    string $key,
): void {
    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storage,
        inventoryItem: $inventoryItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: $quantity,
        baseUnitOfMeasure: $baseUnit,
        referenceType: 'opening_balance',
        referenceId: $inventoryItem->id,
        occurredAt: now()->subMinute(),
        idempotencyKey: $key,
        inboundUnitCost: $unitCost,
    );
}

/**
 * Persist a standard one-line transfer draft.
 */
function createTransferDraftForTest(
    Organization $organization,
    User $actor,
    Location $fromLocation,
    StorageLocation $fromStorage,
    Location $toLocation,
    StorageLocation $toStorage,
    InventoryItem $inventoryItem,
    UnitOfMeasure $unit,
    string $number = 'TR-TEST',
    string $quantity = '0.5',
): StockTransfer {
    return app(SaveStockTransfer::class)->handle(
        $organization,
        $actor,
        [
            'number' => $number,
            'from_location_id' => $fromLocation->id,
            'from_storage_location_id' => $fromStorage->id,
            'to_location_id' => $toLocation->id,
            'to_storage_location_id' => $toStorage->id,
            'notes' => null,
            'lines' => [
                [
                    'inventory_item_id' =>
                        $inventoryItem->id,
                    'requested_quantity' => $quantity,
                    'unit_id' => $unit->id,
                ],
            ],
        ],
    );
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

    $this->fromStorage = createTransferStorageForTest(
        $this->organization,
        $this->fromLocation,
        'FROM',
    );

    $this->toStorage = createTransferStorageForTest(
        $this->organization,
        $this->toLocation,
        'TO',
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
        'name' => 'Transfer Test Item',
        'sku' => 'TRANSFER-TEST',
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
    'transfer draft converts requested quantity without changing inventory',
    function () {
        $transfer = createTransferDraftForTest(
            $this->organization,
            $this->actor,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->kilogram,
            'TR-DRAFT',
            '0.5',
        );

        $line = $transfer->lines()->sole();

        expect($transfer->status)
            ->toBe(StockTransferStatus::Draft)
            ->and($line->requested_quantity)
            ->toBe('0.500000')
            ->and($line->requested_base_quantity)
            ->toBe('500.000000')
            ->and($line->shipped_base_quantity)
            ->toBeNull()
            ->and($line->received_base_quantity)
            ->toBeNull()
            ->and(StockMovement::query()->count())
            ->toBe(0)
            ->and(StockBalance::query()->count())
            ->toBe(0);
    },
);

test(
    'transfer draft rejects identical and cross tenant endpoints',
    function () {
        expect(
            fn () => createTransferDraftForTest(
                $this->organization,
                $this->actor,
                $this->fromLocation,
                $this->fromStorage,
                $this->fromLocation,
                $this->fromStorage,
                $this->inventoryItem,
                $this->kilogram,
                'TR-SAME',
            ),
        )->toThrow(ValidationException::class);

        $otherOrganization =
            Organization::factory()->create();

        $otherLocation = Location::factory()->create([
            'organization_id' =>
                $otherOrganization->id,
            'active' => true,
        ]);

        $otherStorage = createTransferStorageForTest(
            $otherOrganization,
            $otherLocation,
            'OTHER',
        );

        expect(
            fn () => createTransferDraftForTest(
                $this->organization,
                $this->actor,
                $this->fromLocation,
                $this->fromStorage,
                $otherLocation,
                $otherStorage,
                $this->inventoryItem,
                $this->kilogram,
                'TR-CROSS-TENANT',
            ),
        )->toThrow(ValidationException::class);

        expect(StockTransfer::query()->count())
            ->toBe(0);
    },
);

test(
    'shipping removes source stock only and snapshots source average cost',
    function () {
        recordTransferOpeningBalanceForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->inventoryItem,
            $this->gram,
            '1000',
            '0.2500',
            'transfer-test:source-opening',
        );

        recordTransferOpeningBalanceForTest(
            $this->organization,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->gram,
            '600',
            '0.1000',
            'transfer-test:destination-opening',
        );

        $transfer = createTransferDraftForTest(
            $this->organization,
            $this->actor,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->kilogram,
            'TR-SHIP',
        );

        $shipped = app(
            ShipStockTransfer::class,
        )->handle(
            $this->organization,
            $this->actor,
            $transfer,
        );

        $line = $shipped->lines()->sole();

        $outbound = StockMovement::query()
            ->where(
                'type',
                StockMovementType::TransferOut->value,
            )
            ->sole();

        $sourceBalance = StockBalance::query()
            ->where(
                'storage_location_id',
                $this->fromStorage->id,
            )
            ->sole();

        $destinationBalance = StockBalance::query()
            ->where(
                'storage_location_id',
                $this->toStorage->id,
            )
            ->sole();

        expect($shipped->status)
            ->toBe(StockTransferStatus::Shipped)
            ->and($line->shipped_base_quantity)
            ->toBe('500.000000')
            ->and($line->unit_cost)
            ->toBe('0.2500')
            ->and($outbound->quantity)
            ->toBe('-500.000000')
            ->and($outbound->unit_cost)
            ->toBe('0.2500')
            ->and($outbound->reference_type)
            ->toBe('stock_transfer_line')
            ->and($outbound->reference_id)
            ->toBe($line->id)
            ->and($outbound->idempotency_key)
            ->toBe(
                "stock_transfer:{$transfer->id}:line:{$line->id}:out",
            )
            ->and($sourceBalance->quantity_on_hand)
            ->toBe('500.000000')
            ->and($destinationBalance->quantity_on_hand)
            ->toBe('600.000000')
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::TransferIn->value,
                    )
                    ->count(),
            )
            ->toBe(0)
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'stock_transfer.shipped',
                    )
                    ->count(),
            )
            ->toBe(1);
    },
);

test(
    'insufficient source stock rolls back shipment completely',
    function () {
        recordTransferOpeningBalanceForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->inventoryItem,
            $this->gram,
            '100',
            '0.2500',
            'transfer-test:insufficient',
        );

        $transfer = createTransferDraftForTest(
            $this->organization,
            $this->actor,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->kilogram,
            'TR-INSUFFICIENT',
        );

        expect(
            fn () => app(
                ShipStockTransfer::class,
            )->handle(
                $this->organization,
                $this->actor,
                $transfer,
            ),
        )->toThrow(ValidationException::class);

        expect($transfer->refresh()->status)
            ->toBe(StockTransferStatus::Draft)
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::TransferOut->value,
                    )
                    ->count(),
            )
            ->toBe(0)
            ->and(
                StockBalance::query()
                    ->where(
                        'storage_location_id',
                        $this->fromStorage->id,
                    )
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('100.000000');
    },
);

test(
    'duplicate shipping does not duplicate movement balance or audit',
    function () {
        recordTransferOpeningBalanceForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->inventoryItem,
            $this->gram,
            '1000',
            '0.2500',
            'transfer-test:ship-idempotent',
        );

        $transfer = createTransferDraftForTest(
            $this->organization,
            $this->actor,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->kilogram,
            'TR-SHIP-IDEMPOTENT',
        );

        $action = app(ShipStockTransfer::class);

        $action->handle(
            $this->organization,
            $this->actor,
            $transfer,
        );

        $action->handle(
            $this->organization,
            $this->actor,
            $transfer,
        );

        expect(
            StockMovement::query()
                ->where(
                    'type',
                    StockMovementType::TransferOut->value,
                )
                ->count(),
        )
            ->toBe(1)
            ->and(
                StockBalance::query()
                    ->where(
                        'storage_location_id',
                        $this->fromStorage->id,
                    )
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('500.000000')
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'stock_transfer.shipped',
                    )
                    ->count(),
            )
            ->toBe(1);
    },
);

test(
    'receipt carries source cost recalculates destination average and retains variance',
    function () {
        recordTransferOpeningBalanceForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->inventoryItem,
            $this->gram,
            '1000',
            '0.2500',
            'transfer-test:receive-source',
        );

        recordTransferOpeningBalanceForTest(
            $this->organization,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->gram,
            '600',
            '0.1000',
            'transfer-test:receive-destination',
        );

        $transfer = createTransferDraftForTest(
            $this->organization,
            $this->actor,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->kilogram,
            'TR-RECEIVE',
        );

        $shipped = app(
            ShipStockTransfer::class,
        )->handle(
            $this->organization,
            $this->actor,
            $transfer,
        );

        $line = $shipped->lines()->sole();

        $received = app(
            ReceiveStockTransfer::class,
        )->handle(
            $this->organization,
            $this->actor,
            $shipped,
            [
                'lines' => [
                    [
                        'id' => $line->id,
                        'received_base_quantity' =>
                            '400',
                    ],
                ],
            ],
        );

        $line = $received->lines()->sole();

        $inbound = StockMovement::query()
            ->where(
                'type',
                StockMovementType::TransferIn->value,
            )
            ->sole();

        $destinationBalance = StockBalance::query()
            ->where(
                'storage_location_id',
                $this->toStorage->id,
            )
            ->sole();

        expect($received->status)
            ->toBe(StockTransferStatus::Received)
            ->and($line->received_base_quantity)
            ->toBe('400.000000')
            ->and($line->variance_base_quantity)
            ->toBe('-100.000000')
            ->and($line->unit_cost)
            ->toBe('0.2500')
            ->and($inbound->quantity)
            ->toBe('400.000000')
            ->and($inbound->unit_cost)
            ->toBe('0.2500')
            ->and($destinationBalance->quantity_on_hand)
            ->toBe('1000.000000')
            ->and($destinationBalance->average_unit_cost)
            ->toBe('0.1600')
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
    'zero receipt retains full shortage without creating zero inbound movement',
    function () {
        recordTransferOpeningBalanceForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->inventoryItem,
            $this->gram,
            '1000',
            '0.2500',
            'transfer-test:zero-receive',
        );

        $transfer = createTransferDraftForTest(
            $this->organization,
            $this->actor,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->kilogram,
            'TR-ZERO-RECEIVE',
        );

        $shipped = app(
            ShipStockTransfer::class,
        )->handle(
            $this->organization,
            $this->actor,
            $transfer,
        );

        $line = $shipped->lines()->sole();

        $received = app(
            ReceiveStockTransfer::class,
        )->handle(
            $this->organization,
            $this->actor,
            $shipped,
            [
                'lines' => [
                    [
                        'id' => $line->id,
                        'received_base_quantity' =>
                            '0',
                    ],
                ],
            ],
        );

        $line = $received->lines()->sole();

        expect($line->received_base_quantity)
            ->toBe('0.000000')
            ->and($line->variance_base_quantity)
            ->toBe('-500.000000')
            ->and(
                StockMovement::query()
                    ->where(
                        'type',
                        StockMovementType::TransferIn->value,
                    )
                    ->count(),
            )
            ->toBe(0)
            ->and(
                StockBalance::query()
                    ->where(
                        'storage_location_id',
                        $this->toStorage->id,
                    )
                    ->count(),
            )
            ->toBe(0);
    },
);

test(
    'duplicate receipt does not duplicate destination stock or audit',
    function () {
        recordTransferOpeningBalanceForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->inventoryItem,
            $this->gram,
            '1000',
            '0.2500',
            'transfer-test:receive-idempotent',
        );

        $transfer = createTransferDraftForTest(
            $this->organization,
            $this->actor,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->kilogram,
            'TR-RECEIVE-IDEMPOTENT',
        );

        $shipped = app(
            ShipStockTransfer::class,
        )->handle(
            $this->organization,
            $this->actor,
            $transfer,
        );

        $line = $shipped->lines()->sole();

        $attributes = [
            'lines' => [
                [
                    'id' => $line->id,
                    'received_base_quantity' => '500',
                ],
            ],
        ];

        $action = app(ReceiveStockTransfer::class);

        $action->handle(
            $this->organization,
            $this->actor,
            $shipped,
            $attributes,
        );

        $action->handle(
            $this->organization,
            $this->actor,
            $shipped,
            $attributes,
        );

        expect(
            StockMovement::query()
                ->where(
                    'type',
                    StockMovementType::TransferIn->value,
                )
                ->count(),
        )
            ->toBe(1)
            ->and(
                StockBalance::query()
                    ->where(
                        'storage_location_id',
                        $this->toStorage->id,
                    )
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('500.000000')
            ->and(
                AuditLog::query()
                    ->where(
                        'action',
                        'stock_transfer.received',
                    )
                    ->count(),
            )
            ->toBe(1);
    },
);

test(
    'variance report is tenant isolated and protects transfer cost',
    function () {
        recordTransferOpeningBalanceForTest(
            $this->organization,
            $this->fromLocation,
            $this->fromStorage,
            $this->inventoryItem,
            $this->gram,
            '1000',
            '0.2500',
            'transfer-test:report',
        );

        $transfer = createTransferDraftForTest(
            $this->organization,
            $this->actor,
            $this->fromLocation,
            $this->fromStorage,
            $this->toLocation,
            $this->toStorage,
            $this->inventoryItem,
            $this->kilogram,
            'TR-REPORT',
        );

        $shipped = app(
            ShipStockTransfer::class,
        )->handle(
            $this->organization,
            $this->actor,
            $transfer,
        );

        $line = $shipped->lines()->sole();

        app(ReceiveStockTransfer::class)->handle(
            $this->organization,
            $this->actor,
            $shipped,
            [
                'lines' => [
                    [
                        'id' => $line->id,
                        'received_base_quantity' =>
                            '400',
                    ],
                ],
            ],
        );

        $date = now()
            ->setTimezone(
                $this->organization->timezone,
            )
            ->toDateString();

        $url = route(
            'stock-transfers.variance',
            [
                'location_id' =>
                    $this->fromLocation->id,
                'from' => $date,
                'to' => $date,
            ],
        );

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' =>
                    $this->organization->id,
            ])
            ->get($url)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component(
                        'stock-transfers/variance',
                    )
                    ->has('rows', 1)
                    ->where(
                        'rows.0.transferNumber',
                        'TR-REPORT',
                    )
                    ->where(
                        'rows.0.varianceBaseQuantity',
                        '-100.000000',
                    )
                    ->where(
                        'rows.0.unitCost',
                        null,
                    )
                    ->where(
                        'rows.0.varianceValue',
                        null,
                    )
                    ->where(
                        'canViewCosts',
                        false,
                    ),
            );

        $this
            ->actingAs($this->manager)
            ->withSession([
                'active_organization_id' =>
                    $this->organization->id,
            ])
            ->get($url)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component(
                        'stock-transfers/variance',
                    )
                    ->has('rows', 1)
                    ->where(
                        'rows.0.unitCost',
                        '0.2500',
                    )
                    ->where(
                        'rows.0.varianceValue',
                        '-25.0000',
                    )
                    ->where(
                        'canViewCosts',
                        true,
                    ),
            );
    },
);
