<?php

use App\Actions\Inventory\SaveStockCount;
use App\Actions\Inventory\SaveStockTransfer;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Actions\Recipes\SaveRecipe;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RecipeType;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
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
use App\Models\WasteReason;

/**
 * Full fixture of one business record per domain, built directly through
 * models/actions (never through HTTP) so setup itself is never subject to
 * the commercial-write boundary under test.
 */
function buildOrganizationMutationAccessFixture(Organization $organization): array
{
    $owner = User::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);

    $storageLocationA = new StorageLocation;
    $storageLocationA->organization_id = $organization->id;
    $storageLocationA->location_id = $location->id;
    $storageLocationA->name = 'Storage A';
    $storageLocationA->code = 'A';
    $storageLocationA->active = true;
    $storageLocationA->save();

    $storageLocationB = new StorageLocation;
    $storageLocationB->organization_id = $organization->id;
    $storageLocationB->location_id = $location->id;
    $storageLocationB->name = 'Storage B';
    $storageLocationB->code = 'B';
    $storageLocationB->active = true;
    $storageLocationB->save();

    $category = InventoryCategory::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);

    $unit = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'dimension' => 'weight',
        'active' => true,
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $unit->id,
        'active' => true,
    ]);

    $itemUnit = InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $unit->id,
        'quantity_in_base_unit' => '1.000000',
        'active' => true,
    ]);

    $supplier = Supplier::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);

    $supplierItem = SupplierItem::factory()->create([
        'organization_id' => $organization->id,
        'supplier_id' => $supplier->id,
        'inventory_item_id' => $item->id,
        'purchase_unit_of_measure_id' => $unit->id,
        'active' => true,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'supplier_id' => $supplier->id,
        'number' => 'PO-MUTATION-'.$organization->id,
        'status' => PurchaseOrderStatus::Approved,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => null,
        'subtotal' => '100.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '100.00',
        'notes' => null,
        'created_by' => $owner->id,
        'approved_by' => $owner->id,
        'approved_at' => now(),
    ]);

    $purchaseOrderLine = PurchaseOrderLine::query()->create([
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

    $goodsReceipt = app(SaveGoodsReceipt::class)->handle(
        $organization,
        $owner,
        $purchaseOrder,
        [
            'number' => 'GR-MUTATION-'.$organization->id,
            'supplier_reference' => null,
            'notes' => null,
            'lines' => [
                [
                    'purchase_order_line_id' => $purchaseOrderLine->id,
                    'storage_location_id' => $storageLocationA->id,
                    'received_quantity' => '1',
                    'received_unit_of_measure_id' => $unit->id,
                    'notes' => null,
                ],
            ],
        ],
    );

    $stockCount = app(SaveStockCount::class)->handle(
        $organization,
        $owner,
        [
            'number' => 'SC-MUTATION-'.$organization->id,
            'location_id' => $location->id,
            'storage_location_id' => $storageLocationA->id,
            'lines' => [
                [
                    'inventory_item_id' => $item->id,
                    'count_unit_id' => $unit->id,
                    'counted_quantity' => '1',
                ],
            ],
        ],
    );

    $stockTransfer = app(SaveStockTransfer::class)->handle(
        $organization,
        $owner,
        [
            'number' => 'ST-MUTATION-'.$organization->id,
            'from_location_id' => $location->id,
            'from_storage_location_id' => $storageLocationA->id,
            'to_location_id' => $location->id,
            'to_storage_location_id' => $storageLocationB->id,
            'lines' => [
                [
                    'inventory_item_id' => $item->id,
                    'unit_id' => $unit->id,
                    'requested_quantity' => '1',
                ],
            ],
        ],
    );

    $wasteReason = WasteReason::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Spoilage',
        'active' => true,
    ]);

    $recipe = app(SaveRecipe::class)->handle($organization, [
        'code' => 'RCP-MUTATION-'.$organization->id,
        'name' => 'Mutation Recipe',
        'type' => RecipeType::MenuItem,
        'active' => true,
    ]);

    return compact(
        'owner',
        'location',
        'storageLocationA',
        'storageLocationB',
        'category',
        'unit',
        'item',
        'itemUnit',
        'supplier',
        'supplierItem',
        'purchaseOrder',
        'purchaseOrderLine',
        'goodsReceipt',
        'stockCount',
        'stockTransfer',
        'wasteReason',
        'recipe',
    );
}

/**
 * Every organization business mutation route with URL and HTTP method,
 * expressed as a resolver so it can be built from a freshly seeded
 * fixture. New mutation routes must be added here to stay covered.
 *
 * @return array<string, callable(array): array{0: string, 1: string}>
 */
function organizationMutationAccessRouteMatrix(): array
{
    return [
        'organizations.settings.update' => fn (Organization $organization, array $f) => [
            'put',
            route('organizations.settings.update', $organization),
        ],
        'organizations.members.store' => fn (Organization $organization, array $f) => [
            'post',
            route('organizations.members.store', $organization),
        ],
        'organizations.locations.store' => fn (Organization $organization, array $f) => [
            'post',
            route('organizations.locations.store', $organization),
        ],
        'organizations.locations.update' => fn (Organization $organization, array $f) => [
            'put',
            route('organizations.locations.update', [$organization, $f['location']]),
        ],
        'organizations.locations.storage-locations.store' => fn (Organization $organization, array $f) => [
            'post',
            route('organizations.locations.storage-locations.store', [$organization, $f['location']]),
        ],
        'organizations.locations.storage-locations.update' => fn (Organization $organization, array $f) => [
            'put',
            route('organizations.locations.storage-locations.update', [$organization, $f['location'], $f['storageLocationA']]),
        ],
        'inventory.categories.store' => fn () => ['post', route('inventory.categories.store')],
        'inventory.categories.update' => fn (Organization $organization, array $f) => [
            'put',
            route('inventory.categories.update', $f['category']),
        ],
        'inventory.items.store' => fn () => ['post', route('inventory.items.store')],
        'inventory.items.update' => fn (Organization $organization, array $f) => [
            'put',
            route('inventory.items.update', $f['item']),
        ],
        'inventory.items.units.store' => fn (Organization $organization, array $f) => [
            'post',
            route('inventory.items.units.store', $f['item']),
        ],
        'inventory.items.units.update' => fn (Organization $organization, array $f) => [
            'put',
            route('inventory.items.units.update', [$f['item'], $f['itemUnit']]),
        ],
        'inventory.units.store' => fn () => ['post', route('inventory.units.store')],
        'inventory.units.update' => fn (Organization $organization, array $f) => [
            'put',
            route('inventory.units.update', $f['unit']),
        ],
        'inventory.opening-balances.store' => fn () => ['post', route('inventory.opening-balances.store')],
        'inventory.adjustments.store' => fn () => ['post', route('inventory.adjustments.store')],
        'suppliers.store' => fn () => ['post', route('suppliers.store')],
        'suppliers.update' => fn (Organization $organization, array $f) => [
            'put',
            route('suppliers.update', $f['supplier']),
        ],
        'suppliers.items.store' => fn (Organization $organization, array $f) => [
            'post',
            route('suppliers.items.store', $f['supplier']),
        ],
        'suppliers.items.update' => fn (Organization $organization, array $f) => [
            'put',
            route('suppliers.items.update', [$f['supplier'], $f['supplierItem']]),
        ],
        'suppliers.items.prices.store' => fn (Organization $organization, array $f) => [
            'post',
            route('suppliers.items.prices.store', [$f['supplier'], $f['supplierItem']]),
        ],
        'purchase-orders.store' => fn () => ['post', route('purchase-orders.store')],
        'purchase-orders.update' => fn (Organization $organization, array $f) => [
            'put',
            route('purchase-orders.update', $f['purchaseOrder']),
        ],
        'purchase-orders.approve' => fn (Organization $organization, array $f) => [
            'post',
            route('purchase-orders.approve', $f['purchaseOrder']),
        ],
        'purchase-orders.cancel' => fn (Organization $organization, array $f) => [
            'post',
            route('purchase-orders.cancel', $f['purchaseOrder']),
        ],
        'purchase-orders.receipts.store' => fn (Organization $organization, array $f) => [
            'post',
            route('purchase-orders.receipts.store', $f['purchaseOrder']),
        ],
        'goods-receipts.update' => fn (Organization $organization, array $f) => [
            'put',
            route('goods-receipts.update', $f['goodsReceipt']),
        ],
        'goods-receipts.finalize' => fn (Organization $organization, array $f) => [
            'post',
            route('goods-receipts.finalize', $f['goodsReceipt']),
        ],
        'goods-receipts.cancel' => fn (Organization $organization, array $f) => [
            'post',
            route('goods-receipts.cancel', $f['goodsReceipt']),
        ],
        'stock-counts.store' => fn () => ['post', route('stock-counts.store')],
        'stock-counts.update' => fn (Organization $organization, array $f) => [
            'put',
            route('stock-counts.update', $f['stockCount']),
        ],
        'stock-counts.submit' => fn (Organization $organization, array $f) => [
            'post',
            route('stock-counts.submit', $f['stockCount']),
        ],
        'stock-counts.finalize' => fn (Organization $organization, array $f) => [
            'post',
            route('stock-counts.finalize', $f['stockCount']),
        ],
        'stock-counts.cancel' => fn (Organization $organization, array $f) => [
            'post',
            route('stock-counts.cancel', $f['stockCount']),
        ],
        'waste.store' => fn () => ['post', route('waste.store')],
        'waste-reasons.store' => fn () => ['post', route('waste-reasons.store')],
        'waste-reasons.update' => fn (Organization $organization, array $f) => [
            'put',
            route('waste-reasons.update', $f['wasteReason']),
        ],
        'stock-transfers.store' => fn () => ['post', route('stock-transfers.store')],
        'stock-transfers.update' => fn (Organization $organization, array $f) => [
            'put',
            route('stock-transfers.update', $f['stockTransfer']),
        ],
        'stock-transfers.ship' => fn (Organization $organization, array $f) => [
            'post',
            route('stock-transfers.ship', $f['stockTransfer']),
        ],
        'stock-transfers.receive' => fn (Organization $organization, array $f) => [
            'post',
            route('stock-transfers.receive', $f['stockTransfer']),
        ],
        'stock-transfers.cancel' => fn (Organization $organization, array $f) => [
            'post',
            route('stock-transfers.cancel', $f['stockTransfer']),
        ],
        'recipes.store' => fn () => ['post', route('recipes.store')],
        'recipes.update' => fn (Organization $organization, array $f) => [
            'put',
            route('recipes.update', $f['recipe']),
        ],
    ];
}

test('every organization business mutation route is blocked for a commercially read-only organization', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    $fixture = buildOrganizationMutationAccessFixture($organization);

    $movementsBefore = StockMovement::query()->count();
    $balancesBefore = StockBalance::query()->count();

    foreach (organizationMutationAccessRouteMatrix() as $routeName => $resolve) {
        [$method, $uri] = $resolve($organization, $fixture);

        $this->withSession(['active_organization_id' => $organization->id])
            ->actingAs($fixture['owner'])
            ->{$method}($uri, [])
            ->assertForbidden("Expected [{$routeName}] to be forbidden for a read-only organization.");
    }

    expect(StockMovement::query()->count())
        ->toBe($movementsBefore)
        ->and(StockBalance::query()->count())
        ->toBe($balancesBefore);
});

test('existing RBAC still decides whether an otherwise writable member may perform an action', function () {
    $organization = Organization::factory()->create();

    $kitchenStaff = User::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($kitchenStaff)
        ->create(['role' => OrganizationRole::KitchenStaff]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($kitchenStaff)
        ->post(route('inventory.adjustments.store'), [])
        ->assertForbidden();
});

test('one users read-only organization does not affect another accessible organization', function () {
    $user = User::factory()->create();

    $readOnlyOrganization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    $writableOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($readOnlyOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    OrganizationMembership::factory()
        ->for($writableOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->withSession(['active_organization_id' => $readOnlyOrganization->id])
        ->actingAs($user)
        ->post(route('inventory.categories.store'), [
            'name' => 'Blocked category',
            'active' => true,
        ])
        ->assertForbidden();

    $this->withSession(['active_organization_id' => $writableOrganization->id])
        ->actingAs($user)
        ->post(route('inventory.categories.store'), [
            'name' => 'Allowed category',
            'active' => true,
        ])
        ->assertRedirect(route('inventory.categories.index'));

    $this->assertDatabaseMissing('inventory_categories', [
        'organization_id' => $readOnlyOrganization->id,
        'name' => 'Blocked category',
    ]);

    $this->assertDatabaseHas('inventory_categories', [
        'organization_id' => $writableOrganization->id,
        'name' => 'Allowed category',
    ]);
});
