<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationRole;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\StockCount;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create one active storage location for dashboard inventory fixtures.
 */
function makeDashboardStorageLocation(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = "Dashboard Storage {$code}";
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

/**
 * Create the location, storage, and UOM required by stock-ledger fixtures.
 *
 * @return array{0: Location, 1: StorageLocation, 2: UnitOfMeasure}
 */
function makeDashboardInventoryContext(
    Organization $organization,
    string $code,
): array {
    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'name' => "Dashboard Location {$code}",
        'code' => "DASH-{$code}",
        'active' => true,
    ]);

    $storageLocation = makeDashboardStorageLocation(
        $organization,
        $location,
        "DASH-{$code}",
    );

    $unit = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => "Dashboard Unit {$code}",
        'symbol' => strtolower("d{$code}"),
        'active' => true,
    ]);

    return [$location, $storageLocation, $unit];
}

/**
 * Create a purchase-order header in the requested lifecycle state.
 */
function makeDashboardPurchaseOrder(
    Organization $organization,
    Location $location,
    Supplier $supplier,
    User $creator,
    string $number,
    PurchaseOrderStatus $status,
): PurchaseOrder {
    return PurchaseOrder::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'supplier_id' => $supplier->id,
        'number' => $number,
        'status' => $status,
        'order_date' => now()->toDateString(),
        'subtotal' => '10.00',
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => '10.00',
        'created_by' => $creator->id,
    ]);
}

/**
 * Record stock through the authoritative inventory workflow for dashboard tests.
 */
function recordDashboardMovement(
    Organization $organization,
    Location $location,
    StorageLocation $storageLocation,
    UnitOfMeasure $unit,
    InventoryItem $item,
    User $actor,
    StockMovementType $type,
    string $quantity,
    string $key,
    ?string $unitCost = null,
): void {
    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $item,
        type: $type,
        baseQuantity: $quantity,
        baseUnitOfMeasure: $unit,
        referenceType: 'dashboard_test',
        referenceId: $item->id,
        occurredAt: now(),
        actor: $actor,
        idempotencyKey: $key,
        inboundUnitCost: $unitCost,
    );
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('unverified users are redirected to email verification', function () {
    $user = User::factory()->unverified()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

test('verified users without an organization receive the onboarding dashboard', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('dashboard')
                ->where('dashboard', null)
                ->where('organizationContext.active', null),
        );
});

test('manager dashboard exposes bounded operational metrics and tasks', function () {
    $organization = Organization::factory()->create([
        'currency' => 'PHP',
        'timezone' => 'Asia/Manila',
    ]);

    $manager = User::factory()->create([
        'name' => 'Dashboard Manager',
    ]);

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $manager->id,
        'role' => OrganizationRole::Manager,
    ]);

    [$location, $storageLocation, $unit] = makeDashboardInventoryContext(
        $organization,
        'MAIN',
    );

    $valuedItem = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $unit->id,
        'name' => 'Valued Item',
        'sku' => 'DASH-VALUED',
        'active' => true,
    ]);

    $lowItem = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $unit->id,
        'name' => 'Low Stock Item',
        'sku' => 'DASH-LOW',
        'active' => true,
    ]);

    recordDashboardMovement(
        $organization,
        $location,
        $storageLocation,
        $unit,
        $valuedItem,
        $manager,
        StockMovementType::OpeningBalance,
        '10',
        'dashboard:valued:opening',
        '5.0000',
    );

    recordDashboardMovement(
        $organization,
        $location,
        $storageLocation,
        $unit,
        $lowItem,
        $manager,
        StockMovementType::CountAdjustment,
        '-2',
        'dashboard:low:adjustment',
    );

    $supplier = Supplier::factory()->create([
        'organization_id' => $organization->id,
    ]);

    makeDashboardPurchaseOrder(
        $organization,
        $location,
        $supplier,
        $manager,
        'PO-DRAFT',
        PurchaseOrderStatus::Draft,
    );

    $approvedPurchaseOrder = makeDashboardPurchaseOrder(
        $organization,
        $location,
        $supplier,
        $manager,
        'PO-APPROVED',
        PurchaseOrderStatus::Approved,
    );

    makeDashboardPurchaseOrder(
        $organization,
        $location,
        $supplier,
        $manager,
        'PO-PARTIAL',
        PurchaseOrderStatus::PartiallyReceived,
    );

    makeDashboardPurchaseOrder(
        $organization,
        $location,
        $supplier,
        $manager,
        'PO-RECEIVED',
        PurchaseOrderStatus::Received,
    );

    GoodsReceipt::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'purchase_order_id' => $approvedPurchaseOrder->id,
        'supplier_id' => $supplier->id,
        'number' => 'GR-DRAFT',
        'status' => GoodsReceiptStatus::Draft,
    ]);

    StockCount::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'storage_location_id' => $storageLocation->id,
        'number' => 'COUNT-DRAFT',
        'status' => StockCountStatus::Draft,
        'created_by' => $manager->id,
    ]);

    StockCount::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'storage_location_id' => $storageLocation->id,
        'number' => 'COUNT-SUBMITTED',
        'status' => StockCountStatus::Submitted,
        'created_by' => $manager->id,
        'submitted_by' => $manager->id,
    ]);

    StockCount::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'storage_location_id' => $storageLocation->id,
        'number' => 'COUNT-FINALIZED',
        'status' => StockCountStatus::Finalized,
        'created_by' => $manager->id,
        'finalized_by' => $manager->id,
        'finalized_at' => now(),
    ]);

    $this
        ->actingAs($manager)
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('dashboard')
                ->where('dashboard.currency', 'PHP')
                ->where('dashboard.timezone', 'Asia/Manila')
                ->where('dashboard.metrics.inventoryValue', '50.0000')
                ->where('dashboard.metrics.lowStockItems', 1)
                ->where('dashboard.metrics.openPurchaseOrders', 3)
                ->where('dashboard.metrics.pendingReceiving', 2)
                ->where('dashboard.metrics.openStockCounts', 2)
                ->has('dashboard.organizationStats', 1)
                ->where(
                    'dashboard.organizationStats.0.organizationId',
                    $organization->id,
                )
                ->where(
                    'dashboard.organizationStats.0.locationCount',
                    1,
                )
                ->where(
                    'dashboard.organizationStats.0.memberCount',
                    1,
                )
                ->has('dashboard.lowStockAlerts', 1)
                ->where(
                    'dashboard.lowStockAlerts.0.itemId',
                    $lowItem->id,
                )
                ->where(
                    'dashboard.lowStockAlerts.0.quantityOnHand',
                    '-2.000000',
                )
                ->has('dashboard.recentActivity', 2)
                ->where(
                    'dashboard.recentActivity.0.itemName',
                    'Low Stock Item',
                )
                ->where(
                    'dashboard.recentActivity.0.actorName',
                    'Dashboard Manager',
                )
                ->where(
                    'dashboard.pendingTasks.purchaseOrdersAwaitingApproval',
                    1,
                )
                ->where(
                    'dashboard.pendingTasks.receiptsAwaitingFinalization',
                    1,
                )
                ->where(
                    'dashboard.pendingTasks.stockCountsAwaitingFinalization',
                    1,
                ),
        );
});

test('organization switching keeps dashboard metrics tenant isolated', function () {
    $user = User::factory()->create();

    $firstOrganization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    $secondOrganization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    $unrelatedOrganization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    $unrelatedUser = User::factory()->create();

    foreach ([$firstOrganization, $secondOrganization] as $organization) {
        OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::Manager,
        ]);
    }

    OrganizationMembership::factory()->create([
        'organization_id' => $unrelatedOrganization->id,
        'user_id' => $unrelatedUser->id,
        'role' => OrganizationRole::Manager,
    ]);

    [$firstLocation, $firstStorage, $firstUnit] =
        makeDashboardInventoryContext($firstOrganization, 'FIRST');

    [$secondLocation, $secondStorage, $secondUnit] =
        makeDashboardInventoryContext($secondOrganization, 'SECOND');

    [$unrelatedLocation, $unrelatedStorage, $unrelatedUnit] =
        makeDashboardInventoryContext($unrelatedOrganization, 'OTHER');

    $firstItem = InventoryItem::factory()->create([
        'organization_id' => $firstOrganization->id,
        'base_unit_of_measure_id' => $firstUnit->id,
        'name' => 'First Tenant Item',
        'sku' => 'TENANT-FIRST',
    ]);

    $secondItem = InventoryItem::factory()->create([
        'organization_id' => $secondOrganization->id,
        'base_unit_of_measure_id' => $secondUnit->id,
        'name' => 'Second Tenant Item',
        'sku' => 'TENANT-SECOND',
    ]);

    $unrelatedItem = InventoryItem::factory()->create([
        'organization_id' => $unrelatedOrganization->id,
        'base_unit_of_measure_id' => $unrelatedUnit->id,
        'name' => 'Unrelated Tenant Item',
        'sku' => 'TENANT-OTHER',
    ]);

    recordDashboardMovement(
        $firstOrganization,
        $firstLocation,
        $firstStorage,
        $firstUnit,
        $firstItem,
        $user,
        StockMovementType::OpeningBalance,
        '10',
        'dashboard:tenant:first',
        '10.0000',
    );

    recordDashboardMovement(
        $secondOrganization,
        $secondLocation,
        $secondStorage,
        $secondUnit,
        $secondItem,
        $user,
        StockMovementType::OpeningBalance,
        '4',
        'dashboard:tenant:second',
        '5.0000',
    );

    recordDashboardMovement(
        $unrelatedOrganization,
        $unrelatedLocation,
        $unrelatedStorage,
        $unrelatedUnit,
        $unrelatedItem,
        $unrelatedUser,
        StockMovementType::OpeningBalance,
        '999',
        'dashboard:tenant:other',
        '999.0000',
    );

    $this
        ->actingAs($user)
        ->withSession([
            'active_organization_id' => $firstOrganization->id,
        ])
        ->put(route('organizations.activate', $secondOrganization))
        ->assertRedirect(route('dashboard'));

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->where(
                    'organizationContext.active.id',
                    $secondOrganization->id,
                )
                ->where(
                    'dashboard.metrics.inventoryValue',
                    '20.0000',
                )
                ->has('dashboard.organizationStats', 2)
                ->has('dashboard.recentActivity', 1)
                ->where(
                    'dashboard.recentActivity.0.itemName',
                    'Second Tenant Item',
                ),
        );
});

test('inventory staff never receive cost values on the dashboard', function () {
    $organization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    $inventoryStaff = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $inventoryStaff->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    [$location, $storageLocation, $unit] =
        makeDashboardInventoryContext($organization, 'STAFF');

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $unit->id,
        'name' => 'Protected Cost Item',
        'sku' => 'COST-PROTECTED',
    ]);

    recordDashboardMovement(
        $organization,
        $location,
        $storageLocation,
        $unit,
        $item,
        $inventoryStaff,
        StockMovementType::OpeningBalance,
        '8',
        'dashboard:cost:protected',
        '7.5000',
    );

    $this
        ->actingAs($inventoryStaff)
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->where('dashboard.metrics.inventoryValue', null)
                ->has('dashboard.recentActivity', 1)
                ->where(
                    'dashboard.recentActivity.0.totalCost',
                    null,
                ),
        );
});

test('kitchen staff receive no unauthorized reports purchasing or count summaries', function () {
    $organization = Organization::factory()->create();
    $kitchenStaff = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $kitchenStaff->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $this
        ->actingAs($kitchenStaff)
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->where('dashboard.metrics.inventoryValue', null)
                ->where('dashboard.metrics.lowStockItems', null)
                ->where(
                    'dashboard.metrics.openPurchaseOrders',
                    null,
                )
                ->where('dashboard.metrics.pendingReceiving', null)
                ->where('dashboard.metrics.openStockCounts', null)
                ->has('dashboard.lowStockAlerts', 0)
                ->has('dashboard.recentActivity', 0)
                ->where(
                    'dashboard.pendingTasks.purchaseOrdersAwaitingApproval',
                    null,
                )
                ->where(
                    'dashboard.pendingTasks.receiptsAwaitingFinalization',
                    null,
                )
                ->where(
                    'dashboard.pendingTasks.stockCountsAwaitingFinalization',
                    null,
                ),
        );
});
