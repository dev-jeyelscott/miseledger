<?php

use App\Actions\Purchasing\CreateAdHocPurchaseOrder;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Ad-hoc Item',
        'sku' => 'AD-HOC-ITEM',
        'active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Emergency Supplier',
        'active' => true,
    ]);

    $this->actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);
});

test('it creates an approved purchase order and line for a brand-new item', function () {
    $purchaseOrder = app(CreateAdHocPurchaseOrder::class)->handle(
        $this->organization,
        $this->actor,
        $this->supplier,
        $this->location,
        [[
            'inventory_item_id' => $this->item->id,
            'unit_id' => $this->baseUnit->id,
            'quantity' => '5',
        ]],
    );

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::Approved)
        ->and($purchaseOrder->origin)->toBe('mobile_ad_hoc')
        ->and($purchaseOrder->approved_by)->toBe($this->actor->id)
        ->and($purchaseOrder->approved_at)->not->toBeNull()
        ->and($purchaseOrder->lines)->toHaveCount(1);

    $line = $purchaseOrder->lines->first();

    expect($line->inventory_item_id)->toBe($this->item->id)
        ->and($line->ordered_quantity)->toBe('5.000000')
        ->and($line->base_quantity)->toBe('5.000000')
        ->and($line->unit_price)->toBe('0.0000')
        ->and($line->line_total)->toBe('0.00');

    $supplierItem = SupplierItem::query()
        ->where('organization_id', $this->organization->id)
        ->where('supplier_id', $this->supplier->id)
        ->where('inventory_item_id', $this->item->id)
        ->sole();

    expect($supplierItem->current_price)->toBeNull()
        ->and($supplierItem->active)->toBeTrue();
});

test('it reuses an existing supplier item and prices the line from it', function () {
    $existing = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $this->item->id,
        'supplier_sku' => 'CATALOG-SKU',
        'purchase_unit_of_measure_id' => $this->baseUnit->id,
        'base_quantity' => '1.000000',
        'current_price' => '20.0000',
        'currency' => 'PHP',
        'active' => true,
    ]);

    $purchaseOrder = app(CreateAdHocPurchaseOrder::class)->handle(
        $this->organization,
        $this->actor,
        $this->supplier,
        $this->location,
        [[
            'inventory_item_id' => $this->item->id,
            'unit_id' => $this->baseUnit->id,
            'quantity' => '3',
        ]],
    );

    $line = $purchaseOrder->lines->sole();

    expect($line->supplier_item_id)->toBe($existing->id)
        ->and($line->unit_price)->toBe('20.0000')
        ->and($line->line_total)->toBe('60.00')
        ->and(
            SupplierItem::query()
                ->where('organization_id', $this->organization->id)
                ->where('inventory_item_id', $this->item->id)
                ->count(),
        )->toBe(1);
});

test('appending to an existing ad-hoc purchase order adds a new line without creating a second PO', function () {
    $secondItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Second Ad-hoc Item',
        'sku' => 'AD-HOC-ITEM-2',
        'active' => true,
    ]);

    $purchaseOrder = app(CreateAdHocPurchaseOrder::class)->handle(
        $this->organization,
        $this->actor,
        $this->supplier,
        $this->location,
        [[
            'inventory_item_id' => $this->item->id,
            'unit_id' => $this->baseUnit->id,
            'quantity' => '5',
        ]],
    );

    $appended = app(CreateAdHocPurchaseOrder::class)->handle(
        $this->organization,
        $this->actor,
        $this->supplier,
        $this->location,
        [[
            'inventory_item_id' => $secondItem->id,
            'unit_id' => $this->baseUnit->id,
            'quantity' => '2',
        ]],
        $purchaseOrder,
    );

    expect($appended->id)->toBe($purchaseOrder->id)
        ->and($appended->lines)->toHaveCount(2)
        ->and(PurchaseOrder::query()->where('organization_id', $this->organization->id)->count())
        ->toBe(1);
});

test(
    'concurrent ad-hoc creation for the same new supplier and item does not duplicate the supplier item',
    function () {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL concurrency coverage runs in CI.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required for concurrency coverage.');
        }

        DB::commit();
        DB::disconnect();

        $resultPath = tempnam(sys_get_temp_dir(), 'miseledger-adhoc-po-');

        if ($resultPath === false) {
            throw new RuntimeException('Unable to create the concurrency result file.');
        }

        $childPid = pcntl_fork();

        if ($childPid === -1) {
            unlink($resultPath);

            throw new RuntimeException('Unable to fork the concurrency test process.');
        }

        if ($childPid === 0) {
            try {
                DB::transaction(function (): void {
                    Supplier::query()
                        ->whereKey($this->supplier->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Hold the row lock briefly to widen the race window so
                    // the parent process's ad-hoc creation must wait on it.
                    usleep(500000);

                    app(CreateAdHocPurchaseOrder::class)->handle(
                        $this->organization,
                        $this->actor,
                        $this->supplier,
                        $this->location,
                        [[
                            'inventory_item_id' => $this->item->id,
                            'unit_id' => $this->baseUnit->id,
                            'quantity' => '4',
                        ]],
                    );
                });

                file_put_contents($resultPath, 'success');
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($resultPath, get_class($exception));
                exit(1);
            }
        }

        usleep(100000);

        app(CreateAdHocPurchaseOrder::class)->handle(
            $this->organization,
            $this->actor,
            $this->supplier,
            $this->location,
            [[
                'inventory_item_id' => $this->item->id,
                'unit_id' => $this->baseUnit->id,
                'quantity' => '6',
            ]],
        );

        $childReaped = false;

        try {
            pcntl_waitpid($childPid, $status);
            $childReaped = true;

            expect(pcntl_wifexited($status))->toBeTrue()
                ->and(pcntl_wexitstatus($status))->toBe(0)
                ->and(file_get_contents($resultPath))->toBe('success')
                ->and(
                    SupplierItem::query()
                        ->where('organization_id', $this->organization->id)
                        ->where('supplier_id', $this->supplier->id)
                        ->where('inventory_item_id', $this->item->id)
                        ->count(),
                )->toBe(1)
                ->and(
                    PurchaseOrder::query()
                        ->where('organization_id', $this->organization->id)
                        ->count(),
                )->toBe(2);
        } finally {
            if (! $childReaped) {
                pcntl_waitpid($childPid, $status);
            }

            if (file_exists($resultPath)) {
                unlink($resultPath);
            }
        }
    },
);
