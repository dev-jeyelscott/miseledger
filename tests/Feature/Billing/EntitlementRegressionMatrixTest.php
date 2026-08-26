<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * P5-006 regression matrix: proves subscription enforcement, feature
 * gates, quantitative limits, RBAC, tenant isolation, and stock-ledger
 * invariants keep working together, not merely in isolation.
 */
function matrixSubscribe(Organization $organization, array $attributes = []): void
{
    $organization->subscriptions()->create(array_merge([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_matrix_growth',
        'quantity' => 1,
    ], $attributes));
}

/**
 * Build storage, unit, and a seeded item ready to receive one opening
 * balance movement, so mutation attempts have real stock to protect.
 *
 * @return array{location: Location, storage: StorageLocation, unit: UnitOfMeasure, item: InventoryItem}
 */
function matrixInventoryFixture(Organization $organization): array
{
    $location = Location::factory()->for($organization)->create(['active' => true]);

    $storage = new StorageLocation;
    $storage->organization_id = $organization->id;
    $storage->location_id = $location->id;
    $storage->name = 'Main Storage';
    $storage->code = 'MAIN';
    $storage->active = true;
    $storage->save();

    $unit = UnitOfMeasure::factory()->for($organization)->create([
        'dimension' => 'count',
        'active' => true,
    ]);

    $item = InventoryItem::factory()->for($organization)->create([
        'base_unit_of_measure_id' => $unit->id,
        'active' => true,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storage,
        inventoryItem: $item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '10',
        baseUnitOfMeasure: $unit,
        referenceType: 'opening_balance',
        referenceId: $item->id,
        occurredAt: now()->subHour(),
        idempotencyKey: "matrix:opening:{$item->id}:{$storage->id}",
        inboundUnitCost: '2',
    );

    return ['location' => $location, 'storage' => $storage, 'unit' => $unit, 'item' => $item];
}

/**
 * @return array<string, mixed>
 */
function matrixAdjustmentPayload(Organization $organization, array $fixture, string $quantity = '2'): array
{
    return [
        'operation_id' => (string) Str::uuid(),
        'location_id' => $fixture['location']->id,
        'storage_location_id' => $fixture['storage']->id,
        'inventory_item_id' => $fixture['item']->id,
        'quantity' => $quantity,
        'unit_id' => $fixture['unit']->id,
        'reason' => 'Regression matrix adjustment',
        'occurred_at' => now()->setTimezone($organization->timezone)->format('Y-m-d\TH:i'),
    ];
}

test('active payment never bypasses RBAC across owner, manager, staff, and auditor roles in every commercial state', function (
    OrganizationRole $role,
    string $commercialState,
    bool $expectedAllowed,
) {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => $role]);

    $fixture = matrixInventoryFixture($organization);

    match ($commercialState) {
        'trial' => null,
        'active' => matrixSubscribe($organization, ['stripe_status' => 'active']),
        'past_due' => matrixSubscribe($organization, ['stripe_status' => 'past_due']),
        'read_only' => $organization->update(['trial_ends_at' => now()->subDay()]),
    };

    $response = $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.adjustments.store'), matrixAdjustmentPayload($organization, $fixture));

    if ($expectedAllowed) {
        $response->assertRedirect(route('inventory.items.index'));
        expect(StockMovement::query()->count())->toBe(2);
        expect(StockBalance::query()->sole()->quantity_on_hand)->toBe('12.000000');
    } else {
        $response->assertForbidden();
        expect(StockMovement::query()->count())->toBe(1);
        expect(StockBalance::query()->sole()->quantity_on_hand)->toBe('10.000000');
    }
})->with([
    'owner, active subscription, allowed' => [OrganizationRole::Owner, 'active', true],
    'owner, trial, allowed' => [OrganizationRole::Owner, 'trial', true],
    'owner, past due (grace), allowed' => [OrganizationRole::Owner, 'past_due', true],
    'owner, read-only, denied by commercial state' => [OrganizationRole::Owner, 'read_only', false],
    'manager, active subscription, allowed' => [OrganizationRole::Manager, 'active', true],
    'manager, read-only, denied by commercial state' => [OrganizationRole::Manager, 'read_only', false],
    'inventory staff, active subscription, allowed' => [OrganizationRole::InventoryStaff, 'active', true],
    'kitchen staff, active subscription, denied by RBAC despite active payment' => [OrganizationRole::KitchenStaff, 'active', false],
    'auditor, active subscription, denied by RBAC despite active payment' => [OrganizationRole::Auditor, 'active', false],
    'auditor, read-only, denied by both RBAC and commercial state' => [OrganizationRole::Auditor, 'read_only', false],
]);

test('an owner of organization A cannot manage billing or mutate organization B even with an active subscription on A', function () {
    $owner = User::factory()->create();

    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organizationA)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    matrixSubscribe($organizationA, ['stripe_status' => 'active']);

    $this->actingAs($owner)
        ->post(route('organizations.billing.portal', $organizationB))
        ->assertForbidden();

    $this->actingAs($owner)
        ->put(route('organizations.settings.update', $organizationB), [
            'name' => $organizationB->name,
            'slug' => $organizationB->slug,
            'timezone' => $organizationB->timezone,
            'currency' => $organizationB->currency,
            'active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('organizations', [
        'id' => $organizationB->id,
        'name' => $organizationB->name,
    ]);
});

test('a read-only organization blocks a stock-affecting purchasing mutation but still permits authorized historical reads and billing recovery', function () {
    Config::set('billing.plans', [
        'growth' => [
            'name' => 'Growth',
            'tier' => 1,
            'prices' => ['monthly' => 'price_matrix_growth', 'yearly' => null],
            'features' => ['purchasing'],
            'limits' => [],
        ],
    ]);

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    matrixSubscribe($organization, ['stripe_status' => 'unpaid']);

    $supplier = Supplier::factory()->for($organization)->create(['active' => true]);
    $fixture = matrixInventoryFixture($organization);

    $supplierItem = SupplierItem::factory()->create([
        'organization_id' => $organization->id,
        'supplier_id' => $supplier->id,
        'inventory_item_id' => $fixture['item']->id,
        'purchase_unit_of_measure_id' => $fixture['unit']->id,
        'base_quantity' => '1.000000',
        'active' => true,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $fixture['location']->id,
        'supplier_id' => $supplier->id,
        'number' => 'PO-MATRIX',
        'status' => PurchaseOrderStatus::Approved,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'subtotal' => '10.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '10.00',
        'notes' => null,
        'created_by' => $owner->id,
        'approved_by' => $owner->id,
        'approved_at' => now(),
    ]);

    $purchaseOrderLine = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_item_id' => $supplierItem->id,
        'inventory_item_id' => $fixture['item']->id,
        'item_name_snapshot' => $fixture['item']->name,
        'supplier_sku_snapshot' => $supplierItem->supplier_sku,
        'ordered_quantity' => '5.000000',
        'purchase_unit_of_measure_id' => $fixture['unit']->id,
        'base_quantity' => '5.000000',
        'unit_price' => '2.0000',
        'line_total' => '10.00',
        'received_base_quantity' => '0.000000',
    ]);

    $receipt = app(SaveGoodsReceipt::class)->handle(
        $organization,
        $owner,
        $purchaseOrder,
        [
            'number' => 'GR-MATRIX',
            'supplier_reference' => null,
            'notes' => null,
            'lines' => [[
                'purchase_order_line_id' => $purchaseOrderLine->id,
                'storage_location_id' => $fixture['storage']->id,
                'received_quantity' => '5',
                'received_unit_of_measure_id' => $fixture['unit']->id,
                'notes' => null,
            ]],
        ],
    );

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->post(route('goods-receipts.finalize', $receipt))
        ->assertForbidden();

    expect(StockMovement::query()->where('type', StockMovementType::PurchaseReceipt->value)->count())->toBe(0);
    expect(StockBalance::query()->where('inventory_item_id', $fixture['item']->id)->sole()->quantity_on_hand)->toBe('10.000000');

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->get(route('goods-receipts.index'))
        ->assertOk();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->get(route('inventory.stock-movements.index'))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('organizations.billing.show', $organization))
        ->assertOk();
});

test('one user reaches organizations in different commercial states without any write or ledger leakage between them', function () {
    $user = User::factory()->create();

    $writableOrganization = Organization::factory()->create();
    $readOnlyOrganization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    OrganizationMembership::factory()
        ->for($writableOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    OrganizationMembership::factory()
        ->for($readOnlyOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $writableFixture = matrixInventoryFixture($writableOrganization);
    $readOnlyFixture = matrixInventoryFixture($readOnlyOrganization);

    $this->withSession(['active_organization_id' => $writableOrganization->id])
        ->actingAs($user)
        ->post(route('inventory.adjustments.store'), matrixAdjustmentPayload($writableOrganization, $writableFixture))
        ->assertRedirect(route('inventory.items.index'));

    $this->withSession(['active_organization_id' => $readOnlyOrganization->id])
        ->actingAs($user)
        ->post(route('inventory.adjustments.store'), matrixAdjustmentPayload($readOnlyOrganization, $readOnlyFixture))
        ->assertForbidden();

    expect(
        StockBalance::query()
            ->where('organization_id', $writableOrganization->id)
            ->sole()
            ->quantity_on_hand,
    )->toBe('12.000000');

    expect(
        StockBalance::query()
            ->where('organization_id', $readOnlyOrganization->id)
            ->sole()
            ->quantity_on_hand,
    )->toBe('10.000000');

    expect(StockMovement::query()->where('organization_id', $writableOrganization->id)->count())->toBe(2);
    expect(StockMovement::query()->where('organization_id', $readOnlyOrganization->id)->count())->toBe(1);
});

test('a plan lacking the purchasing feature blocks goods receipt finalization for a fully permitted owner and leaves the ledger unchanged, while a granted plan allows it', function () {
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'prices' => ['monthly' => 'price_matrix_starter', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
        'growth' => [
            'name' => 'Growth',
            'tier' => 1,
            'prices' => ['monthly' => 'price_matrix_growth', 'yearly' => null],
            'features' => ['purchasing'],
            'limits' => [],
        ],
    ]);

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    matrixSubscribe($organization, ['stripe_status' => 'active', 'stripe_price' => 'price_matrix_starter']);

    $supplier = Supplier::factory()->for($organization)->create(['active' => true]);
    $fixture = matrixInventoryFixture($organization);

    $supplierItem = SupplierItem::factory()->create([
        'organization_id' => $organization->id,
        'supplier_id' => $supplier->id,
        'inventory_item_id' => $fixture['item']->id,
        'purchase_unit_of_measure_id' => $fixture['unit']->id,
        'base_quantity' => '1.000000',
        'active' => true,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $fixture['location']->id,
        'supplier_id' => $supplier->id,
        'number' => 'PO-FEATURE-MATRIX',
        'status' => PurchaseOrderStatus::Approved,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'subtotal' => '10.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '10.00',
        'notes' => null,
        'created_by' => $owner->id,
        'approved_by' => $owner->id,
        'approved_at' => now(),
    ]);

    $purchaseOrderLine = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_item_id' => $supplierItem->id,
        'inventory_item_id' => $fixture['item']->id,
        'item_name_snapshot' => $fixture['item']->name,
        'supplier_sku_snapshot' => $supplierItem->supplier_sku,
        'ordered_quantity' => '5.000000',
        'purchase_unit_of_measure_id' => $fixture['unit']->id,
        'base_quantity' => '5.000000',
        'unit_price' => '2.0000',
        'line_total' => '10.00',
        'received_base_quantity' => '0.000000',
    ]);

    $receipt = app(SaveGoodsReceipt::class)->handle(
        $organization,
        $owner,
        $purchaseOrder,
        [
            'number' => 'GR-FEATURE-MATRIX',
            'supplier_reference' => null,
            'notes' => null,
            'lines' => [[
                'purchase_order_line_id' => $purchaseOrderLine->id,
                'storage_location_id' => $fixture['storage']->id,
                'received_quantity' => '5',
                'received_unit_of_measure_id' => $fixture['unit']->id,
                'notes' => null,
            ]],
        ],
    );

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->post(route('goods-receipts.finalize', $receipt))
        ->assertForbidden();

    expect(StockMovement::query()->where('type', StockMovementType::PurchaseReceipt->value)->count())->toBe(0);
    expect(StockBalance::query()->where('inventory_item_id', $fixture['item']->id)->sole()->quantity_on_hand)->toBe('10.000000');

    $organization->subscription(config('billing.subscription_type'))->update([
        'stripe_price' => 'price_matrix_growth',
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->post(route('goods-receipts.finalize', $receipt))
        ->assertRedirect();

    expect(StockMovement::query()->where('type', StockMovementType::PurchaseReceipt->value)->count())->toBe(1);
    expect(StockBalance::query()->where('inventory_item_id', $fixture['item']->id)->sole()->quantity_on_hand)->toBe('15.000000');
});
