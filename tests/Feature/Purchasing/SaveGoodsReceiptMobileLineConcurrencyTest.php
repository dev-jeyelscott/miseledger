<?php

use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->owner = User::factory()->create();

    OrganizationMembership::factory()
        ->for($this->organization)
        ->for($this->owner)
        ->create(['role' => OrganizationRole::Owner]);

    $this->location = Location::factory()
        ->for($this->organization)
        ->create(['active' => true]);

    $this->storageLocation = new StorageLocation([
        'name' => 'Main Storage',
        'code' => 'MAIN',
        'active' => true,
    ]);
    $this->storageLocation->organization()->associate($this->organization);
    $this->storageLocation->location()->associate($this->location);
    $this->storageLocation->save();

    $this->baseUnit = UnitOfMeasure::factory()
        ->for($this->organization)
        ->create(['dimension' => 'count', 'active' => true]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->itemA = InventoryItem::factory()->for($this->organization)->create([
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'active' => true,
    ]);

    $this->itemB = InventoryItem::factory()->for($this->organization)->create([
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'active' => true,
    ]);

    $this->itemC = InventoryItem::factory()->for($this->organization)->create([
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'active' => true,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'supplier_id' => $this->supplier->id,
        'number' => 'PO-MOBILE-RACE-001',
        'status' => PurchaseOrderStatus::Approved,
        'origin' => 'desktop',
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'subtotal' => '0.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '0.00',
        'notes' => null,
        'created_by' => $this->owner->id,
        'approved_by' => $this->owner->id,
        'approved_at' => now(),
    ]);

    $this->poLineA = makePurchaseOrderLineFor($this->organization, $this->purchaseOrder, $this->itemA, $this->baseUnit, $this->supplier);
    $this->poLineB = makePurchaseOrderLineFor($this->organization, $this->purchaseOrder, $this->itemB, $this->baseUnit, $this->supplier);
    $this->poLineC = makePurchaseOrderLineFor($this->organization, $this->purchaseOrder, $this->itemC, $this->baseUnit, $this->supplier);

    $this->receipt = app(SaveGoodsReceipt::class)->handle(
        $this->organization,
        $this->owner,
        $this->purchaseOrder,
        [
            'number' => 'GR-MOBILE-RACE-001',
            'supplier_reference' => null,
            'notes' => null,
            'lines' => [[
                'purchase_order_line_id' => $this->poLineA->id,
                'storage_location_id' => $this->storageLocation->id,
                'received_quantity' => '1.000000',
                'received_unit_of_measure_id' => $this->baseUnit->id,
                'notes' => null,
            ]],
        ],
    );
});

function makePurchaseOrderLineFor(Organization $organization, PurchaseOrder $purchaseOrder, InventoryItem $item, UnitOfMeasure $unit, Supplier $supplier): PurchaseOrderLine
{
    $supplierItem = SupplierItem::factory()->create([
        'organization_id' => $organization->id,
        'supplier_id' => $supplier->id,
        'inventory_item_id' => $item->id,
        'purchase_unit_of_measure_id' => $unit->id,
        'base_quantity' => '1.000000',
        'current_price' => '10.0000',
        'currency' => 'PHP',
        'active' => true,
    ]);

    return PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_item_id' => $supplierItem->id,
        'inventory_item_id' => $item->id,
        'item_name_snapshot' => $item->name,
        'supplier_sku_snapshot' => $supplierItem->supplier_sku,
        'ordered_quantity' => '10.000000',
        'purchase_unit_of_measure_id' => $unit->id,
        'base_quantity' => '10.000000',
        'unit_price' => '10.0000',
        'line_total' => '100.00',
        'received_base_quantity' => '0.000000',
    ]);
}

test('two concurrent mobile scans against the same draft goods receipt both land instead of one silently overwriting the other', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL concurrency coverage runs in CI.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for concurrency coverage.');
    }

    $receiptId = $this->receipt->id;
    $organization = $this->organization;
    $owner = $this->owner;
    $purchaseOrder = $this->purchaseOrder;
    $poLineBId = $this->poLineB->id;
    $poLineCId = $this->poLineC->id;
    $storageLocationId = $this->storageLocation->id;
    $unitId = $this->baseUnit->id;

    DB::commit();
    DB::disconnect();

    $resultPath = tempnam(sys_get_temp_dir(), 'miseledger-mobile-receipt-line-race-');

    if ($resultPath === false) {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        throw new RuntimeException('Unable to create the concurrency result file.');
    }

    $childPid = pcntl_fork();

    if ($childPid === -1) {
        unlink($resultPath);
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        throw new RuntimeException('Unable to fork the concurrency test process.');
    }

    if ($childPid === 0) {
        try {
            DB::transaction(function () use (
                $organization,
                $owner,
                $purchaseOrder,
                $receiptId,
                $poLineBId,
                $storageLocationId,
                $unitId,
            ): void {
                // Acquire the same row lock `addMobileLine()` acquires, then
                // hold it briefly to force the parent process's concurrent
                // scan to block on it, widening the race window that the
                // pre-fix unlocked read-then-replace was vulnerable to.
                $receipt = GoodsReceipt::query()
                    ->whereKey($receiptId)
                    ->lockForUpdate()
                    ->firstOrFail();

                usleep(500000);

                app(SaveGoodsReceipt::class)->addMobileLine(
                    $organization,
                    $owner,
                    $purchaseOrder,
                    [
                        'number' => $receipt->number,
                        'supplier_reference' => $receipt->supplier_reference,
                        'notes' => $receipt->notes,
                    ],
                    [
                        'purchase_order_line_id' => $poLineBId,
                        'storage_location_id' => $storageLocationId,
                        'received_quantity' => '2.000000',
                        'received_unit_of_measure_id' => $unitId,
                        'notes' => null,
                    ],
                    $receipt,
                );
            });

            file_put_contents($resultPath, 'success');
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultPath, get_class($exception).': '.$exception->getMessage());
            exit(1);
        }
    }

    // Give the child a head start so it acquires the draft's row lock before
    // this process attempts its own concurrent scan.
    usleep(100000);

    app(SaveGoodsReceipt::class)->addMobileLine(
        $organization,
        $owner,
        $purchaseOrder,
        [
            'number' => $this->receipt->number,
            'supplier_reference' => $this->receipt->supplier_reference,
            'notes' => $this->receipt->notes,
        ],
        [
            'purchase_order_line_id' => $poLineCId,
            'storage_location_id' => $storageLocationId,
            'received_quantity' => '3.000000',
            'received_unit_of_measure_id' => $unitId,
            'notes' => null,
        ],
        $this->receipt,
    );

    $childReaped = false;

    try {
        pcntl_waitpid($childPid, $status);
        $childReaped = true;

        expect(pcntl_wifexited($status))->toBeTrue()
            ->and(pcntl_wexitstatus($status))->toBe(0)
            ->and(file_get_contents($resultPath))->toBe('success');

        $lines = GoodsReceipt::query()
            ->whereKey($receiptId)
            ->firstOrFail()
            ->lines()
            ->pluck('purchase_order_line_id')
            ->all();

        expect($lines)->toEqualCanonicalizing([$this->poLineA->id, $poLineBId, $poLineCId]);
    } finally {
        if (! $childReaped) {
            pcntl_waitpid($childPid, $status);
        }

        if (file_exists($resultPath)) {
            unlink($resultPath);
        }

        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
    }
});
