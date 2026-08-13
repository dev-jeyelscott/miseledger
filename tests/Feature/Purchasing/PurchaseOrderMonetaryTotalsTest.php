<?php

use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Build a valid single-line purchase-order request payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function purchaseOrderMonetaryPayloadForTest(
    Supplier $supplier,
    Location $location,
    SupplierItem $supplierItem,
    array $overrides = [],
): array {
    return array_replace([
        'number' => 'PO-MONEY-'.fake()->unique()->numerify('######'),
        'supplier_id' => $supplier->id,
        'location_id' => $location->id,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'notes' => null,
        'lines' => [
            [
                'supplier_item_id' => $supplierItem->id,
                'ordered_quantity' => '10',
            ],
        ],
    ], $overrides);
}

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Each',
        'symbol' => 'ea',
        'dimension' => 'count',
        'active' => true,
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Monetary Test Item',
        'sku' => 'MONEY-TEST',
        'active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Monetary Test Supplier',
        'active' => true,
    ]);

    $this->supplierItem = SupplierItem::factory()->create([
        'organization_id' => $this->organization->id,
        'supplier_id' => $this->supplier->id,
        'inventory_item_id' => $this->inventoryItem->id,
        'supplier_sku' => 'SUP-MONEY-TEST',
        'purchase_unit_of_measure_id' => $this->baseUnit->id,
        'base_quantity' => '1.000000',
        'current_price' => '10.0000',
        'currency' => $this->organization->currency,
        'active' => true,
    ]);

    $this->actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::Manager,
    ]);
});

test('purchase orders default tax and discount to zero', function () {
    $payload = purchaseOrderMonetaryPayloadForTest(
        $this->supplier,
        $this->location,
        $this->supplierItem,
    );

    unset($payload['tax_total'], $payload['discount_total']);

    $this->actingAs($this->actor)
        ->post(route('purchase-orders.store'), $payload)
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()
        ->where('number', $payload['number'])
        ->sole();

    expect($purchaseOrder->subtotal)
        ->toBe('100.00')
        ->and($purchaseOrder->tax_total)
        ->toBe('0.00')
        ->and($purchaseOrder->discount_total)
        ->toBe('0.00')
        ->and($purchaseOrder->total)
        ->toBe('100.00');
});

test('purchase order total includes non-zero tax', function () {
    $payload = purchaseOrderMonetaryPayloadForTest(
        $this->supplier,
        $this->location,
        $this->supplierItem,
        ['tax_total' => '12.34'],
    );

    $this->actingAs($this->actor)
        ->post(route('purchase-orders.store'), $payload)
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()
        ->where('number', $payload['number'])
        ->sole();

    expect($purchaseOrder->subtotal)
        ->toBe('100.00')
        ->and($purchaseOrder->tax_total)
        ->toBe('12.34')
        ->and($purchaseOrder->discount_total)
        ->toBe('0.00')
        ->and($purchaseOrder->total)
        ->toBe('112.34');
});

test('purchase order total includes non-zero discount', function () {
    $payload = purchaseOrderMonetaryPayloadForTest(
        $this->supplier,
        $this->location,
        $this->supplierItem,
        ['discount_total' => '7.89'],
    );

    $this->actingAs($this->actor)
        ->post(route('purchase-orders.store'), $payload)
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()
        ->where('number', $payload['number'])
        ->sole();

    expect($purchaseOrder->subtotal)
        ->toBe('100.00')
        ->and($purchaseOrder->tax_total)
        ->toBe('0.00')
        ->and($purchaseOrder->discount_total)
        ->toBe('7.89')
        ->and($purchaseOrder->total)
        ->toBe('92.11');
});

test(
    'purchase order total combines tax and discount and ignores client totals',
    function () {
        $payload = purchaseOrderMonetaryPayloadForTest(
            $this->supplier,
            $this->location,
            $this->supplierItem,
            [
                'tax_total' => '12.34',
                'discount_total' => '7.89',
                'subtotal' => '0.01',
                'total' => '999999.99',
            ],
        );

        $this->actingAs($this->actor)
            ->post(route('purchase-orders.store'), $payload)
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()
            ->where('number', $payload['number'])
            ->sole();

        expect($purchaseOrder->subtotal)
            ->toBe('100.00')
            ->and($purchaseOrder->tax_total)
            ->toBe('12.34')
            ->and($purchaseOrder->discount_total)
            ->toBe('7.89')
            ->and($purchaseOrder->total)
            ->toBe('104.45');
    },
);

test('purchase order totals retain decimal-safe line rounding', function () {
    $this->supplierItem->forceFill([
        'current_price' => '10.0050',
    ])->save();

    $payload = purchaseOrderMonetaryPayloadForTest(
        $this->supplier,
        $this->location,
        $this->supplierItem,
        [
            'tax_total' => '0.10',
            'discount_total' => '0.03',
            'lines' => [
                [
                    'supplier_item_id' => $this->supplierItem->id,
                    'ordered_quantity' => '3',
                ],
            ],
        ],
    );

    $this->actingAs($this->actor)
        ->post(route('purchase-orders.store'), $payload)
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()
        ->where('number', $payload['number'])
        ->with('lines')
        ->sole();

    expect($purchaseOrder->lines->sole()->line_total)
        ->toBe('30.02')
        ->and($purchaseOrder->subtotal)
        ->toBe('30.02')
        ->and($purchaseOrder->tax_total)
        ->toBe('0.10')
        ->and($purchaseOrder->discount_total)
        ->toBe('0.03')
        ->and($purchaseOrder->total)
        ->toBe('30.09');
});

test(
    'purchase order money inputs reject negative and invalid values',
    function (string $field, string $value) {
        $payload = purchaseOrderMonetaryPayloadForTest(
            $this->supplier,
            $this->location,
            $this->supplierItem,
            [$field => $value],
        );

        $this->actingAs($this->actor)
            ->from(route('purchase-orders.create'))
            ->post(route('purchase-orders.store'), $payload)
            ->assertRedirect(route('purchase-orders.create'))
            ->assertSessionHasErrors($field);

        expect(
            PurchaseOrder::query()
                ->where('number', $payload['number'])
                ->exists(),
        )->toBeFalse();
    },
)->with([
    'negative tax' => ['tax_total', '-0.01'],
    'negative discount' => ['discount_total', '-0.01'],
    'invalid tax' => ['tax_total', 'not-a-number'],
    'excess discount precision' => ['discount_total', '1.234'],
]);

test('discount cannot make purchase order total negative', function () {
    $payload = purchaseOrderMonetaryPayloadForTest(
        $this->supplier,
        $this->location,
        $this->supplierItem,
        ['discount_total' => '100.01'],
    );

    $this->actingAs($this->actor)
        ->from(route('purchase-orders.create'))
        ->post(route('purchase-orders.store'), $payload)
        ->assertRedirect(route('purchase-orders.create'))
        ->assertSessionHasErrors('discount_total');

    expect(
        PurchaseOrder::query()
            ->where('number', $payload['number'])
            ->exists(),
    )->toBeFalse();
});

test('approved purchase order totals are immutable historical snapshots', function () {
    $payload = purchaseOrderMonetaryPayloadForTest(
        $this->supplier,
        $this->location,
        $this->supplierItem,
        [
            'tax_total' => '5.55',
            'discount_total' => '2.25',
        ],
    );

    $this->actingAs($this->actor)
        ->post(route('purchase-orders.store'), $payload)
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()
        ->where('number', $payload['number'])
        ->sole();

    $this->actingAs($this->actor)
        ->post(route('purchase-orders.approve', $purchaseOrder))
        ->assertRedirect(route('purchase-orders.edit', $purchaseOrder));

    $updatedPayload = purchaseOrderMonetaryPayloadForTest(
        $this->supplier,
        $this->location,
        $this->supplierItem,
        [
            'number' => $purchaseOrder->number,
            'tax_total' => '50.00',
            'discount_total' => '0.00',
            'lines' => [
                [
                    'supplier_item_id' => $this->supplierItem->id,
                    'ordered_quantity' => '20',
                ],
            ],
        ],
    );

    $this->actingAs($this->actor)
        ->from(route('purchase-orders.edit', $purchaseOrder))
        ->put(
            route('purchase-orders.update', $purchaseOrder),
            $updatedPayload,
        )
        ->assertRedirect(route('purchase-orders.edit', $purchaseOrder))
        ->assertSessionHasErrors('purchase_order');

    $purchaseOrder->refresh()->load('lines');

    expect($purchaseOrder->status)
        ->toBe(PurchaseOrderStatus::Approved)
        ->and($purchaseOrder->subtotal)
        ->toBe('100.00')
        ->and($purchaseOrder->tax_total)
        ->toBe('5.55')
        ->and($purchaseOrder->discount_total)
        ->toBe('2.25')
        ->and($purchaseOrder->total)
        ->toBe('103.30')
        ->and($purchaseOrder->lines->sole()->line_total)
        ->toBe('100.00');

    $this->actingAs($this->actor)
        ->get(route('purchase-orders.edit', $purchaseOrder))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('purchase-orders/form')
                ->where('purchaseOrder.subtotal', '100.00')
                ->where('purchaseOrder.taxTotal', '5.55')
                ->where('purchaseOrder.discountTotal', '2.25')
                ->where('purchaseOrder.total', '103.30'),
        );
});

test('purchase order monetary updates remain tenant isolated', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherSupplier = Supplier::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $foreignPurchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $otherOrganization->id,
        'location_id' => $otherLocation->id,
        'supplier_id' => $otherSupplier->id,
        'number' => 'PO-FOREIGN-MONEY',
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'subtotal' => '25.00',
        'tax_total' => '2.00',
        'discount_total' => '1.00',
        'total' => '26.00',
        'notes' => null,
        'created_by' => null,
    ]);

    $payload = purchaseOrderMonetaryPayloadForTest(
        $this->supplier,
        $this->location,
        $this->supplierItem,
        [
            'number' => $foreignPurchaseOrder->number,
            'tax_total' => '99.00',
            'discount_total' => '0.00',
        ],
    );

    $this->actingAs($this->actor)
        ->put(
            route('purchase-orders.update', $foreignPurchaseOrder),
            $payload,
        )
        ->assertForbidden();

    $foreignPurchaseOrder->refresh();

    expect($foreignPurchaseOrder->tax_total)
        ->toBe('2.00')
        ->and($foreignPurchaseOrder->discount_total)
        ->toBe('1.00')
        ->and($foreignPurchaseOrder->total)
        ->toBe('26.00');
});

test('purchase order creation and approval remain inventory neutral', function () {
    $payload = purchaseOrderMonetaryPayloadForTest(
        $this->supplier,
        $this->location,
        $this->supplierItem,
        [
            'tax_total' => '5.00',
            'discount_total' => '2.00',
        ],
    );

    expect(StockMovement::query()->count())
        ->toBe(0)
        ->and(StockBalance::query()->count())
        ->toBe(0);

    $this->actingAs($this->actor)
        ->post(route('purchase-orders.store'), $payload)
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()
        ->where('number', $payload['number'])
        ->sole();

    expect(StockMovement::query()->count())
        ->toBe(0)
        ->and(StockBalance::query()->count())
        ->toBe(0);

    $this->actingAs($this->actor)
        ->post(route('purchase-orders.approve', $purchaseOrder))
        ->assertRedirect(route('purchase-orders.edit', $purchaseOrder));

    expect($purchaseOrder->refresh()->status)
        ->toBe(PurchaseOrderStatus::Approved)
        ->and(StockMovement::query()->count())
        ->toBe(0)
        ->and(StockBalance::query()->count())
        ->toBe(0);
});
