<?php

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Inventory\FinalizeStockCount;
use App\Actions\Inventory\ReceiveStockTransfer;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\RecordWaste;
use App\Actions\Inventory\ReplayStockLedger;
use App\Actions\Inventory\SaveStockCount;
use App\Actions\Inventory\SaveStockTransfer;
use App\Actions\Inventory\ShipStockTransfer;
use App\Actions\Inventory\SubmitStockCount;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Build one active tenant-owned storage location for integrity-suite coverage.
 */
function createInventoryIntegrityStorageLocation(
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

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->mainStorage = createInventoryIntegrityStorageLocation(
        $this->organization,
        $this->location,
        'MAIN',
    );

    $this->kitchenStorage = createInventoryIntegrityStorageLocation(
        $this->organization,
        $this->location,
        'KITCHEN',
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
        'active' => true,
    ]);

    $this->wasteReason = WasteReason::query()->create([
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
});

test(
    'ledger-derived final balances reconcile cleanly across the full inventory lifecycle',
    function () {
        $occurredAt = now()->subDay();

        // Opening balance: 2 kg converted to 2000 g at 0.0500/g.
        app(RecordOpeningBalance::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $this->item,
            quantity: '2',
            unit: $this->kilogram,
            baseUnitCost: '0.05',
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: $occurredAt,
            idempotencyKey: 'suite:opening:1',
            actor: $this->actor,
        );

        $occurredAt = $occurredAt->addMinute();

        // Purchase receipt: +1000 g @ 0.20/g moves the weighted average to 0.1000.
        app(RecordStockMovement::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $this->item,
            type: StockMovementType::PurchaseReceipt,
            baseQuantity: '1000',
            baseUnitOfMeasure: $this->gram,
            referenceType: 'goods_receipt_line',
            referenceId: 1,
            occurredAt: $occurredAt,
            actor: $this->actor,
            idempotencyKey: 'suite:receipt:1',
            inboundUnitCost: '0.20',
        );

        $occurredAt = $occurredAt->addMinute();

        // Waste: -100 g snapshotting the current average cost.
        app(RecordWaste::class)->handle(
            organization: $this->organization,
            actor: $this->actor,
            data: [
                'operation_id' => (string) Str::uuid(),
                'location_id' => $this->location->id,
                'storage_location_id' => $this->mainStorage->id,
                'inventory_item_id' => $this->item->id,
                'waste_reason_id' => $this->wasteReason->id,
                'quantity' => '100',
                'unit_id' => $this->gram->id,
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );

        $adjust = app(AdjustInventory::class);

        // Manual adjustments: +50 g found stock, -20 g duplicate-count correction.
        $adjust->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $this->item,
            quantity: '50',
            unit: $this->gram,
            reason: 'Found sealed stock during recount',
            referenceType: 'manual_adjustment',
            referenceId: 1,
            occurredAt: $occurredAt->addMinute(),
            actor: $this->actor,
            idempotencyKey: 'suite:adjustment:positive',
        );

        $adjust->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $this->item,
            quantity: '-20',
            unit: $this->gram,
            reason: 'Corrected duplicate count',
            referenceType: 'manual_adjustment',
            referenceId: 2,
            occurredAt: $occurredAt->addMinute(),
            actor: $this->actor,
            idempotencyKey: 'suite:adjustment:negative',
        );

        // Expected balance is now 2930 g. A physical count of 2900 g yields a -30 g variance.
        $stockCount = app(SaveStockCount::class)->handle(
            organization: $this->organization,
            actor: $this->actor,
            attributes: [
                'number' => 'SC-SUITE-1',
                'location_id' => $this->location->id,
                'storage_location_id' => $this->mainStorage->id,
                'lines' => [
                    [
                        'inventory_item_id' => $this->item->id,
                        'count_unit_id' => $this->gram->id,
                        'counted_quantity' => '2900',
                    ],
                ],
            ],
        );

        app(SubmitStockCount::class)->handle(
            $this->organization,
            $this->actor,
            $stockCount,
        );

        app(FinalizeStockCount::class)->handle(
            $this->organization,
            $this->actor,
            $stockCount,
        );

        // Transfer 400 g from MAIN to KITCHEN, fully received without variance.
        $transfer = app(SaveStockTransfer::class)->handle(
            organization: $this->organization,
            actor: $this->actor,
            attributes: [
                'number' => 'ST-SUITE-1',
                'from_location_id' => $this->location->id,
                'from_storage_location_id' => $this->mainStorage->id,
                'to_location_id' => $this->location->id,
                'to_storage_location_id' => $this->kitchenStorage->id,
                'lines' => [
                    [
                        'inventory_item_id' => $this->item->id,
                        'unit_id' => $this->gram->id,
                        'requested_quantity' => '400',
                    ],
                ],
            ],
        );

        app(ShipStockTransfer::class)->handle(
            $this->organization,
            $this->actor,
            $transfer,
        );

        $transferLine = $transfer->lines()->sole();

        app(ReceiveStockTransfer::class)->handle(
            organization: $this->organization,
            actor: $this->actor,
            stockTransfer: $transfer,
            attributes: [
                'lines' => [
                    [
                        'id' => $transferLine->id,
                        'received_base_quantity' => '400',
                    ],
                ],
            ],
        );

        $mainBalance = StockBalance::query()
            ->where('storage_location_id', $this->mainStorage->id)
            ->sole();

        $kitchenBalance = StockBalance::query()
            ->where('storage_location_id', $this->kitchenStorage->id)
            ->sole();

        expect($mainBalance->quantity_on_hand)
            ->toBe('2500.000000')
            ->and($mainBalance->average_unit_cost)
            ->toBe('0.1000')
            ->and($mainBalance->inventory_value)
            ->toBe('250.0000')
            ->and($kitchenBalance->quantity_on_hand)
            ->toBe('400.000000')
            ->and($kitchenBalance->average_unit_cost)
            ->toBe('0.1000')
            ->and($kitchenBalance->inventory_value)
            ->toBe('40.0000');

        $replayedMain = app(ReplayStockLedger::class)->handle(
            $this->organization->id,
            $this->location->id,
            $this->mainStorage->id,
            $this->item->id,
        );

        $replayedKitchen = app(ReplayStockLedger::class)->handle(
            $this->organization->id,
            $this->location->id,
            $this->kitchenStorage->id,
            $this->item->id,
        );

        expect($replayedMain['quantity_on_hand'])
            ->toBe($mainBalance->quantity_on_hand)
            ->and($replayedMain['average_unit_cost'])
            ->toBe($mainBalance->average_unit_cost)
            ->and($replayedMain['inventory_value'])
            ->toBe($mainBalance->inventory_value)
            ->and($replayedKitchen['quantity_on_hand'])
            ->toBe($kitchenBalance->quantity_on_hand)
            ->and($replayedKitchen['average_unit_cost'])
            ->toBe($kitchenBalance->average_unit_cost)
            ->and($replayedKitchen['inventory_value'])
            ->toBe($kitchenBalance->inventory_value);

        $this->artisan('inventory:reconcile')
            ->expectsOutput(
                'Stock balances reconcile with the authoritative ledger.',
            )
            ->assertExitCode(0);
    },
);

test(
    'duplicate waste, count finalization, and transfer operations do not duplicate stock',
    function () {
        $occurredAt = now()->subHour();

        app(RecordStockMovement::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $this->item,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '500',
            baseUnitOfMeasure: $this->gram,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: $occurredAt,
            idempotencyKey: 'suite-dup:opening:1',
            inboundUnitCost: '1',
        );

        $wasteOperationId = (string) Str::uuid();
        $wastePayload = [
            'operation_id' => $wasteOperationId,
            'location_id' => $this->location->id,
            'storage_location_id' => $this->mainStorage->id,
            'inventory_item_id' => $this->item->id,
            'waste_reason_id' => $this->wasteReason->id,
            'quantity' => '50',
            'unit_id' => $this->gram->id,
            'occurred_at' => $occurredAt->addMinute()->toIso8601String(),
        ];

        $waste = app(RecordWaste::class);
        $firstWaste = $waste->handle($this->organization, $this->actor, $wastePayload);
        $secondWaste = $waste->handle($this->organization, $this->actor, $wastePayload);

        expect($secondWaste->is($firstWaste))->toBeTrue()
            ->and(
                StockMovement::query()
                    ->where('type', StockMovementType::Waste->value)
                    ->count(),
            )->toBe(1)
            ->and(
                StockBalance::query()
                    ->where('storage_location_id', $this->mainStorage->id)
                    ->sole()
                    ->quantity_on_hand,
            )->toBe('450.000000');

        $stockCount = app(SaveStockCount::class)->handle(
            organization: $this->organization,
            actor: $this->actor,
            attributes: [
                'number' => 'SC-SUITE-DUP',
                'location_id' => $this->location->id,
                'storage_location_id' => $this->mainStorage->id,
                'lines' => [
                    [
                        'inventory_item_id' => $this->item->id,
                        'count_unit_id' => $this->gram->id,
                        'counted_quantity' => '440',
                    ],
                ],
            ],
        );

        app(SubmitStockCount::class)->handle($this->organization, $this->actor, $stockCount);
        app(FinalizeStockCount::class)->handle($this->organization, $this->actor, $stockCount);

        $movementCountAfterFirstFinalize = StockMovement::query()->count();
        $balanceAfterFirstFinalize = StockBalance::query()
            ->where('storage_location_id', $this->mainStorage->id)
            ->sole()
            ->quantity_on_hand;

        $refetchedCount = StockCount::query()->findOrFail($stockCount->id);
        app(FinalizeStockCount::class)->handle($this->organization, $this->actor, $refetchedCount);

        expect(StockMovement::query()->count())
            ->toBe($movementCountAfterFirstFinalize)
            ->and(
                StockBalance::query()
                    ->where('storage_location_id', $this->mainStorage->id)
                    ->sole()
                    ->quantity_on_hand,
            )->toBe($balanceAfterFirstFinalize);

        $transfer = app(SaveStockTransfer::class)->handle(
            organization: $this->organization,
            actor: $this->actor,
            attributes: [
                'number' => 'ST-SUITE-DUP',
                'from_location_id' => $this->location->id,
                'from_storage_location_id' => $this->mainStorage->id,
                'to_location_id' => $this->location->id,
                'to_storage_location_id' => $this->kitchenStorage->id,
                'lines' => [
                    [
                        'inventory_item_id' => $this->item->id,
                        'unit_id' => $this->gram->id,
                        'requested_quantity' => '100',
                    ],
                ],
            ],
        );

        app(ShipStockTransfer::class)->handle($this->organization, $this->actor, $transfer);

        $movementCountAfterFirstShip = StockMovement::query()->count();

        $refetchedTransfer = StockTransfer::query()->findOrFail($transfer->id);
        app(ShipStockTransfer::class)->handle($this->organization, $this->actor, $refetchedTransfer);

        expect(StockMovement::query()->count())->toBe($movementCountAfterFirstShip);

        $transferLine = $transfer->lines()->sole();
        $receiptAttributes = [
            'lines' => [
                [
                    'id' => $transferLine->id,
                    'received_base_quantity' => '100',
                ],
            ],
        ];

        app(ReceiveStockTransfer::class)->handle(
            $this->organization,
            $this->actor,
            $transfer,
            $receiptAttributes,
        );

        $movementCountAfterFirstReceive = StockMovement::query()->count();
        $mainBalanceAfterReceive = StockBalance::query()
            ->where('storage_location_id', $this->mainStorage->id)
            ->sole()
            ->quantity_on_hand;
        $kitchenBalanceAfterReceive = StockBalance::query()
            ->where('storage_location_id', $this->kitchenStorage->id)
            ->sole()
            ->quantity_on_hand;

        app(ReceiveStockTransfer::class)->handle(
            $this->organization,
            $this->actor,
            $transfer,
            $receiptAttributes,
        );

        expect(StockMovement::query()->count())
            ->toBe($movementCountAfterFirstReceive)
            ->and(
                StockBalance::query()
                    ->where('storage_location_id', $this->mainStorage->id)
                    ->sole()
                    ->quantity_on_hand,
            )->toBe($mainBalanceAfterReceive)
            ->and(
                StockBalance::query()
                    ->where('storage_location_id', $this->kitchenStorage->id)
                    ->sole()
                    ->quantity_on_hand,
            )->toBe($kitchenBalanceAfterReceive);
    },
);

test(
    'tenant isolation keeps ledgers and balances scoped to their own organization',
    function () {
        $otherOrganization = Organization::factory()->create();

        $otherLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);

        $otherStorage = createInventoryIntegrityStorageLocation(
            $otherOrganization,
            $otherLocation,
            'MAIN',
        );

        $otherGram = UnitOfMeasure::factory()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'dimension' => 'weight',
            'active' => true,
        ]);

        $otherItem = InventoryItem::factory()->create([
            'organization_id' => $otherOrganization->id,
            'base_unit_of_measure_id' => $otherGram->id,
            'active' => true,
        ]);

        $otherActor = User::factory()->create();

        OrganizationMembership::factory()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherActor->id,
            'role' => OrganizationRole::InventoryStaff,
        ]);

        app(RecordStockMovement::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $this->item,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '300',
            baseUnitOfMeasure: $this->gram,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now()->subHour(),
            idempotencyKey: 'suite-tenant:a:opening',
            inboundUnitCost: '1',
        );

        app(RecordStockMovement::class)->handle(
            organization: $otherOrganization,
            location: $otherLocation,
            storageLocation: $otherStorage,
            inventoryItem: $otherItem,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '900',
            baseUnitOfMeasure: $otherGram,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now()->subHour(),
            idempotencyKey: 'suite-tenant:b:opening',
            inboundUnitCost: '1',
        );

        expect(
            StockBalance::query()
                ->where('organization_id', $this->organization->id)
                ->sum('quantity_on_hand'),
        )->toBe('300.000000')
            ->and(
                StockBalance::query()
                    ->where('organization_id', $otherOrganization->id)
                    ->sum('quantity_on_hand'),
            )->toBe('900.000000');

        // Organization A's location cannot be used to move organization B's item.
        expect(fn () => app(RecordStockMovement::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $otherItem,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '1',
            baseUnitOfMeasure: $otherGram,
            referenceType: 'manual_adjustment',
            referenceId: 99,
            occurredAt: now(),
            idempotencyKey: 'suite-tenant:cross-item',
        ))->toThrow(ValidationException::class);

        // Organization B's location cannot be used from organization A's context.
        expect(fn () => app(RecordStockMovement::class)->handle(
            organization: $this->organization,
            location: $otherLocation,
            storageLocation: $otherStorage,
            inventoryItem: $this->item,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '1',
            baseUnitOfMeasure: $this->gram,
            referenceType: 'manual_adjustment',
            referenceId: 98,
            occurredAt: now(),
            idempotencyKey: 'suite-tenant:cross-location',
        ))->toThrow(ValidationException::class);

        expect(StockMovement::query()->where('organization_id', $this->organization->id)->count())
            ->toBe(1)
            ->and(StockMovement::query()->where('organization_id', $otherOrganization->id)->count())
            ->toBe(1);
    },
);

test(
    'concurrent-safe balance mutations lock the projection row and preserve summed quantities',
    function () {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'PostgreSQL row-lock SQL is verified in CI.',
            );
        }

        app(RecordStockMovement::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $this->item,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '1000',
            baseUnitOfMeasure: $this->gram,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now()->subHour(),
            idempotencyKey: 'suite-concurrency:opening',
            inboundUnitCost: '1',
        );

        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $adjust = app(AdjustInventory::class);

        // Two representative concurrent requests racing for the same balance row.
        $adjust->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $this->item,
            quantity: '30',
            unit: $this->gram,
            reason: 'Concurrent request A',
            referenceType: 'manual_adjustment',
            referenceId: 101,
            occurredAt: now(),
            actor: $this->actor,
            idempotencyKey: 'suite-concurrency:a',
        );

        $adjust->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->mainStorage,
            inventoryItem: $this->item,
            quantity: '-10',
            unit: $this->gram,
            reason: 'Concurrent request B',
            referenceType: 'manual_adjustment',
            referenceId: 102,
            occurredAt: now(),
            actor: $this->actor,
            idempotencyKey: 'suite-concurrency:b',
        );

        expect(
            collect($queries)->contains(
                fn (string $sql): bool => str_contains($sql, 'stock_balances')
                    && str_contains($sql, 'for update'),
            ),
        )->toBeTrue();

        // No lost update: both racing mutations are reflected in the final balance.
        expect(
            StockBalance::query()
                ->where('storage_location_id', $this->mainStorage->id)
                ->sole()
                ->quantity_on_hand,
        )->toBe('1020.000000')
            ->and(StockMovement::query()->count())
            ->toBe(3);
    },
);
