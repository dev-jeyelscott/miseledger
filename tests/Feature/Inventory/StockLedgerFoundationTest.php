<?php

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function createStockLedgerStorageLocationForTest(
    Organization $organization,
    Location $location,
    string $code = 'MAIN',
): StorageLocation {
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = 'Main Storage';
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->storageLocation =
        createStockLedgerStorageLocationForTest(
            $this->organization,
            $this->location,
        );

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
    ]);

    $this->inventoryUser = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->inventoryUser->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);
});

test(
    'stock movement and balance update atomically with weighted average costing',
    function () {
        $record = app(RecordStockMovement::class);

        $first = $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::PurchaseReceipt,
            baseQuantity: '10',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'goods_receipt_line',
            referenceId: 1,
            occurredAt: now(),
            idempotencyKey: 'goods_receipt:1:line:1',
            inboundUnitCost: '100',
        );

        $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::PurchaseReceipt,
            baseQuantity: '30',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'goods_receipt_line',
            referenceId: 2,
            occurredAt: now()->addSecond(),
            idempotencyKey: 'goods_receipt:2:line:2',
            inboundUnitCost: '200',
        );

        $balance = StockBalance::query()->sole();

        expect($first->quantity)
            ->toBe('10.000000')
            ->and($first->base_unit_of_measure_id)
            ->toBe($this->baseUnit->id)
            ->and($first->unit_cost)
            ->toBe('100.0000')
            ->and($first->total_cost)
            ->toBe('1000.0000')
            ->and($balance->quantity_on_hand)
            ->toBe('40.000000')
            ->and($balance->average_unit_cost)
            ->toBe('175.0000')
            ->and($balance->inventory_value)
            ->toBe('7000.0000')
            ->and(StockBalance::query()->count())
            ->toBe(1)
            ->and(StockMovement::query()->count())
            ->toBe(2);
    },
);

test(
    'outbound movement snapshots current cost and zero stock keeps last average cost',
    function () {
        $record = app(RecordStockMovement::class);

        $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '5',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now(),
            idempotencyKey: 'opening:1',
            inboundUnitCost: '100',
        );

        $waste = $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::Waste,
            baseQuantity: '-5',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'waste_record',
            referenceId: 1,
            occurredAt: now()->addSecond(),
            idempotencyKey: 'waste:1',
        );

        $balance = StockBalance::query()->sole();

        expect($waste->unit_cost)
            ->toBe('100.0000')
            ->and($waste->total_cost)
            ->toBe('500.0000')
            ->and($balance->quantity_on_hand)
            ->toBe('0.000000')
            ->and($balance->average_unit_cost)
            ->toBe('100.0000')
            ->and($balance->inventory_value)
            ->toBe('0.0000');
    },
);

test(
    'idempotent retry cannot duplicate movement or balance quantity',
    function () {
        $record = app(RecordStockMovement::class);

        $arguments = [
            'organization' => $this->organization,
            'location' => $this->location,
            'storageLocation' => $this->storageLocation,
            'inventoryItem' => $this->inventoryItem,
            'type' => StockMovementType::PurchaseReceipt,
            'baseQuantity' => '4',
            'baseUnitOfMeasure' => $this->baseUnit,
            'referenceType' => 'goods_receipt_line',
            'referenceId' => 10,
            'occurredAt' => now(),
            'idempotencyKey' => 'goods_receipt:10:line:10',
            'inboundUnitCost' => '25',
        ];

        $first = $record->handle(...$arguments);
        $second = $record->handle(...$arguments);

        expect($second->is($first))
            ->toBeTrue()
            ->and(StockMovement::query()->count())
            ->toBe(1)
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('4.000000');

        expect(fn () => $record->handle(
            ...array_merge(
                $arguments,
                ['baseQuantity' => '5'],
            ),
        ))->toThrow(ValidationException::class);

        expect(StockMovement::query()->count())
            ->toBe(1)
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('4.000000');
    },
);

test(
    'ledger rejects zero quantity cross tenant records and storage location mismatch',
    function () {
        $record = app(RecordStockMovement::class);

        expect(fn () => $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '0',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'manual_adjustment',
            referenceId: 1,
            occurredAt: now(),
        ))->toThrow(ValidationException::class);

        $wrongBaseUnit = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'dimension' => 'weight',
        ]);

        expect(fn () => $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '1',
            baseUnitOfMeasure: $wrongBaseUnit,
            referenceType: 'manual_adjustment',
            referenceId: 2,
            occurredAt: now(),
        ))->toThrow(ValidationException::class);

        $otherLocation = Location::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        expect(fn () => $record->handle(
            organization: $this->organization,
            location: $otherLocation,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '1',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'manual_adjustment',
            referenceId: 3,
            occurredAt: now(),
        ))->toThrow(ValidationException::class);

        $otherOrganization =
            Organization::factory()->create();

        $otherItem = InventoryItem::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        expect(fn () => $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $otherItem,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '1',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'manual_adjustment',
            referenceId: 4,
            occurredAt: now(),
        ))->toThrow(ValidationException::class);

        expect(StockMovement::query()->count())
            ->toBe(0)
            ->and(StockBalance::query()->count())
            ->toBe(0);
    },
);

test(
    'failed outbound mutation rolls back a newly created projection row',
    function () {
        expect(fn () => app(
            RecordStockMovement::class,
        )->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::Waste,
            baseQuantity: '-1',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'waste_record',
            referenceId: 99,
            occurredAt: now(),
            idempotencyKey: 'waste:rollback',
        ))->toThrow(ValidationException::class);

        expect(StockMovement::query()->count())
            ->toBe(0)
            ->and(StockBalance::query()->count())
            ->toBe(0);
    },
);

test(
    'opening balance converts quantity to base unit and is idempotent',
    function () {
        $kilogram = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'dimension' => 'weight',
        ]);

        $action = app(RecordOpeningBalance::class);

        $first = $action->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            quantity: '2.5',
            unit: $kilogram,
            baseUnitCost: '0.0400',
            referenceType: 'opening_balance_batch',
            referenceId: 7,
            occurredAt: now(),
            idempotencyKey: 'opening_balance:batch:7:item:1:storage:1',
            actor: $this->inventoryUser,
        );

        $second = $action->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            quantity: '2.5',
            unit: $kilogram,
            baseUnitCost: '0.0400',
            referenceType: 'opening_balance_batch',
            referenceId: 7,
            occurredAt: now()->addMinute(),
            idempotencyKey: 'opening_balance:batch:7:item:1:storage:1',
            actor: $this->inventoryUser,
        );

        $balance = StockBalance::query()->sole();

        expect($second->is($first))
            ->toBeTrue()
            ->and($first->type)
            ->toBe(StockMovementType::OpeningBalance)
            ->and($first->quantity)
            ->toBe('2500.000000')
            ->and($balance->quantity_on_hand)
            ->toBe('2500.000000')
            ->and($balance->average_unit_cost)
            ->toBe('0.0400')
            ->and($balance->inventory_value)
            ->toBe('100.0000')
            ->and(StockMovement::query()->count())
            ->toBe(1);
    },
);

test(
    'manual adjustment requires permission and reason and blocks negative stock',
    function () {
        app(RecordOpeningBalance::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            quantity: '10',
            unit: $this->baseUnit,
            baseUnitCost: '2',
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now(),
            idempotencyKey: 'opening:manual-adjustment-test',
            actor: $this->inventoryUser,
        );

        $auditor = User::factory()->create();

        OrganizationMembership::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $auditor->id,
            'role' => OrganizationRole::Auditor,
        ]);

        $adjust = app(AdjustInventory::class);

        expect(fn () => $adjust->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            quantity: '1',
            unit: $this->baseUnit,
            reason: 'Cycle correction',
            referenceType: 'manual_adjustment',
            referenceId: 1,
            occurredAt: now(),
            actor: $auditor,
            idempotencyKey: 'adjustment:auditor',
        ))->toThrow(AuthorizationException::class);

        expect(fn () => $adjust->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            quantity: '1',
            unit: $this->baseUnit,
            reason: '   ',
            referenceType: 'manual_adjustment',
            referenceId: 2,
            occurredAt: now(),
            actor: $this->inventoryUser,
            idempotencyKey: 'adjustment:no-reason',
        ))->toThrow(ValidationException::class);

        $positive = $adjust->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            quantity: '2',
            unit: $this->baseUnit,
            reason: 'Found sealed stock during recount',
            referenceType: 'manual_adjustment',
            referenceId: 3,
            occurredAt: now(),
            actor: $this->inventoryUser,
            idempotencyKey: 'adjustment:positive',
        );

        $negative = $adjust->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            quantity: '-3',
            unit: $this->baseUnit,
            reason: 'Corrected duplicate count',
            referenceType: 'manual_adjustment',
            referenceId: 4,
            occurredAt: now(),
            actor: $this->inventoryUser,
            idempotencyKey: 'adjustment:negative',
        );

        expect($positive->created_by)
            ->toBe($this->inventoryUser->id)
            ->and($positive->notes)
            ->toBe(
                'Found sealed stock during recount',
            )
            ->and($negative->type)
            ->toBe(
                StockMovementType::ManualAdjustment,
            )
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('9.000000');

        expect(fn () => $adjust->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            quantity: '-10',
            unit: $this->baseUnit,
            reason: 'Invalid excessive correction',
            referenceType: 'manual_adjustment',
            referenceId: 5,
            occurredAt: now(),
            actor: $this->inventoryUser,
            idempotencyKey: 'adjustment:too-negative',
        ))->toThrow(ValidationException::class);

        expect(
            StockBalance::query()
                ->sole()
                ->quantity_on_hand,
        )
            ->toBe('9.000000')
            ->and(StockMovement::query()->count())
            ->toBe(3);
    },
);

test(
    'committed stock movement cannot be edited or deleted',
    function () {
        $movement = app(
            RecordStockMovement::class,
        )->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '1',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now(),
            idempotencyKey: 'opening:immutable',
            inboundUnitCost: '1',
        );

        expect(
            function () use ($movement): void {
                $movement->notes = 'mutated';
                $movement->save();
            },
        )->toThrow(LogicException::class);

        $movement->refresh();

        expect(
            fn () => $movement->delete(),
        )->toThrow(LogicException::class);
    },
);

test(
    'backdated movement is rejected until an audited reconciliation workflow exists',
    function () {
        $record = app(RecordStockMovement::class);
        $occurredAt = now();

        $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '5',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: $occurredAt,
            idempotencyKey: 'opening:backdate',
            inboundUnitCost: '1',
        );

        expect(fn () => $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '1',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'manual_adjustment',
            referenceId: 2,
            occurredAt: $occurredAt->copy()->subSecond(),
            idempotencyKey: 'adjustment:backdated',
        ))->toThrow(ValidationException::class);

        expect(StockMovement::query()->count())
            ->toBe(1)
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('5.000000');
    },
);

test(
    'record stock movement issues postgres row locks for concurrent balance protection',
    function () {
        if (
            DB::connection()->getDriverName()
            !== 'pgsql'
        ) {
            $this->markTestSkipped(
                'PostgreSQL row-lock SQL is verified in CI.',
            );
        }

        $queries = [];

        DB::listen(
            function (
                QueryExecuted $query,
            ) use (&$queries): void {
                $queries[] = strtolower($query->sql);
            },
        );

        app(RecordStockMovement::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '1',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now(),
            idempotencyKey: 'opening:lock-test',
            inboundUnitCost: '1',
        );

        expect(
            collect($queries)->contains(
                fn (string $sql): bool => str_contains(
                    $sql,
                    'stock_balances',
                )
                    && str_contains(
                        $sql,
                        'for update',
                    ),
            ),
        )->toBeTrue();
    },
);

test(
    'reconciliation detects and rebuild repairs projection without changing ledger history',
    function () {
        app(RecordStockMovement::class)->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '10',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now(),
            idempotencyKey: 'opening:reconcile',
            inboundUnitCost: '2',
        );

        $movementIds = StockMovement::query()
            ->pluck('id')
            ->all();

        $balance = StockBalance::query()->sole();

        DB::table(
            (new StockBalance)->getTable(),
        )
            ->where('id', $balance->id)
            ->update([
                'quantity_on_hand' => '999.000000',
                'average_unit_cost' => '3.0000',
                'inventory_value' => '2997.0000',
            ]);

        $this->artisan('inventory:reconcile')
            ->expectsOutput(
                'Stock balance discrepancies detected.',
            )
            ->assertExitCode(1);

        $this->artisan(
            'inventory:rebuild-balances',
        )
            ->expectsOutput(
                'Rebuilt 1 stock balance projection row.',
            )
            ->assertExitCode(0);

        $balance->refresh();

        expect($balance->quantity_on_hand)
            ->toBe('10.000000')
            ->and($balance->average_unit_cost)
            ->toBe('2.0000')
            ->and($balance->inventory_value)
            ->toBe('20.0000')
            ->and(
                StockMovement::query()
                    ->pluck('id')
                    ->all(),
            )
            ->toBe($movementIds);

        $this->artisan('inventory:reconcile')
            ->expectsOutput(
                'Stock balances reconcile with the authoritative ledger.',
            )
            ->assertExitCode(0);
    },
);
