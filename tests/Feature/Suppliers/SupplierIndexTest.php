<?php

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
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create one organization-scoped purchase order for supplier index assertions.
 */
function createSupplierIndexPurchaseOrderForTest(
    Organization $organization,
    Location $location,
    Supplier $supplier,
    User $actor,
    string $number,
    PurchaseOrderStatus $status,
    string $total,
    string $orderDate,
): PurchaseOrder {
    $approved = $status !== PurchaseOrderStatus::Draft;

    return PurchaseOrder::query()->create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'supplier_id' => $supplier->id,
        'number' => $number,
        'status' => $status,
        'order_date' => $orderDate,
        'expected_delivery_date' => null,
        'subtotal' => $total,
        'tax_total' => '0.00',
        'discount_total' => '0.00',
        'total' => $total,
        'notes' => null,
        'created_by' => $actor->id,
        'approved_by' => $approved
            ? $actor->id
            : null,
        'approved_at' => $approved
            ? now()
            : null,
    ]);
}

test(
    'supplier index exposes tenant scoped operational summary and latest purchase order',
    function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create([
            'currency' => 'PHP',
        ]);

        OrganizationMembership::factory()
            ->for($organization)
            ->for($user)
            ->create([
                'role' => OrganizationRole::Owner,
            ]);

        $location = Location::factory()->create([
            'organization_id' => $organization->id,
            'active' => true,
        ]);

        $alphaSupplier = Supplier::factory()
            ->for($organization)
            ->create([
                'name' => 'Alpha Foods',
                'code' => 'ALPHA',
                'active' => true,
            ]);

        $betaSupplier = Supplier::factory()
            ->for($organization)
            ->create([
                'name' => 'Beta Foods',
                'code' => 'BETA',
                'active' => false,
            ]);

        $unit = UnitOfMeasure::factory()
            ->for($organization)
            ->create();

        $inventoryItem = InventoryItem::factory()
            ->for($organization)
            ->create([
                'base_unit_of_measure_id' => $unit->id,
            ]);

        SupplierItem::factory()
            ->for($organization)
            ->for($alphaSupplier)
            ->for($inventoryItem)
            ->create([
                'purchase_unit_of_measure_id' => $unit->id,
            ]);

        $orderDate = now(
            $organization->timezone,
        )->toDateString();

        createSupplierIndexPurchaseOrderForTest(
            $organization,
            $location,
            $alphaSupplier,
            $user,
            'PO-ALPHA-OLD',
            PurchaseOrderStatus::Received,
            '100.00',
            $orderDate,
        );

        createSupplierIndexPurchaseOrderForTest(
            $organization,
            $location,
            $alphaSupplier,
            $user,
            'PO-ALPHA-LATEST',
            PurchaseOrderStatus::Approved,
            '250.00',
            $orderDate,
        );

        createSupplierIndexPurchaseOrderForTest(
            $organization,
            $location,
            $betaSupplier,
            $user,
            'PO-BETA-DRAFT',
            PurchaseOrderStatus::Draft,
            '50.00',
            $orderDate,
        );

        $otherOrganization = Organization::factory()->create();

        $otherLocation = Location::factory()->create([
            'organization_id' => $otherOrganization->id,
            'active' => true,
        ]);

        $otherSupplier = Supplier::factory()
            ->for($otherOrganization)
            ->create([
                'name' => 'Other Tenant Supplier',
            ]);

        $otherUser = User::factory()->create();

        createSupplierIndexPurchaseOrderForTest(
            $otherOrganization,
            $otherLocation,
            $otherSupplier,
            $otherUser,
            'PO-OTHER',
            PurchaseOrderStatus::Approved,
            '999.00',
            $orderDate,
        );

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->get(route('suppliers.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('suppliers/index')
                    ->has('suppliers', 2)
                    ->where(
                        'suppliers.0.id',
                        $alphaSupplier->id,
                    )
                    ->where(
                        'suppliers.0.itemCount',
                        1,
                    )
                    ->where(
                        'suppliers.0.lastPurchaseOrderNumber',
                        'PO-ALPHA-LATEST',
                    )
                    ->where(
                        'suppliers.0.lastPurchaseOrderDate',
                        $orderDate,
                    )
                    ->where(
                        'summary.totalSuppliers',
                        2,
                    )
                    ->where(
                        'summary.activeSuppliers',
                        1,
                    )
                    ->where(
                        'summary.linkedItems',
                        1,
                    )
                    ->where(
                        'summary.openPurchaseOrders',
                        2,
                    )
                    ->where(
                        'summary.purchaseValueYtd',
                        '350.00',
                    )
                    ->where(
                        'pagination.total',
                        2,
                    )
                    ->where(
                        'canViewCosts',
                        true,
                    )
                    ->where(
                        'canManage',
                        true,
                    ),
            );
    },
);

test(
    'supplier index never exposes purchase value without costs permission',
    function () {
        $user = User::factory()->create();

        $organization = Organization::factory()->create([
            'currency' => 'PHP',
        ]);

        OrganizationMembership::factory()
            ->for($organization)
            ->for($user)
            ->create([
                'role' => OrganizationRole::InventoryStaff,
            ]);

        $location = Location::factory()->create([
            'organization_id' => $organization->id,
            'active' => true,
        ]);

        $supplier = Supplier::factory()
            ->for($organization)
            ->create();

        createSupplierIndexPurchaseOrderForTest(
            $organization,
            $location,
            $supplier,
            $user,
            'PO-HIDDEN-COST',
            PurchaseOrderStatus::Approved,
            '500.00',
            now($organization->timezone)->toDateString(),
        );

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->get(route('suppliers.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('suppliers/index')
                    ->has('suppliers', 1)
                    ->where(
                        'summary.purchaseValueYtd',
                        null,
                    )
                    ->where(
                        'canViewCosts',
                        false,
                    )
                    ->where(
                        'canManage',
                        false,
                    ),
            );
    },
);

test(
    'supplier index supports server side searching status filtering sorting and pagination',
    function () {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMembership::factory()
            ->for($organization)
            ->for($user)
            ->create([
                'role' => OrganizationRole::Owner,
            ]);

        foreach (range(1, 12) as $index) {
            Supplier::factory()
                ->for($organization)
                ->create([
                    'name' => sprintf(
                        'Supplier %02d',
                        $index,
                    ),
                    'code' => sprintf(
                        'SUP-%02d',
                        $index,
                    ),
                    'active' => true,
                ]);
        }

        $needleSupplier = Supplier::factory()
            ->for($organization)
            ->create([
                'name' => 'Needle Vendor',
                'code' => 'NEEDLE',
                'email' => 'needle@example.com',
                'active' => false,
            ]);

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->get(
                route(
                    'suppliers.index',
                    [
                        'sort' => 'name_desc',
                        'per_page' => 10,
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('suppliers/index')
                    ->has('suppliers', 10)
                    ->where(
                        'suppliers.0.name',
                        'Supplier 12',
                    )
                    ->where(
                        'pagination.currentPage',
                        1,
                    )
                    ->where(
                        'pagination.lastPage',
                        2,
                    )
                    ->where(
                        'pagination.total',
                        13,
                    )
                    ->where(
                        'filters.sort',
                        'name_desc',
                    )
                    ->where(
                        'filters.perPage',
                        10,
                    ),
            );

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->get(
                route(
                    'suppliers.index',
                    [
                        'sort' => 'name_desc',
                        'per_page' => 10,
                        'page' => 2,
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->has('suppliers', 3)
                    ->where(
                        'pagination.currentPage',
                        2,
                    )
                    ->where(
                        'pagination.from',
                        11,
                    )
                    ->where(
                        'pagination.to',
                        13,
                    ),
            );

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->get(
                route(
                    'suppliers.index',
                    [
                        'search' => 'needle@example.com',
                        'status' => 'inactive',
                    ],
                ),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->has('suppliers', 1)
                    ->where(
                        'suppliers.0.id',
                        $needleSupplier->id,
                    )
                    ->where(
                        'filters.search',
                        'needle@example.com',
                    )
                    ->where(
                        'filters.status',
                        'inactive',
                    ),
            );
    },
);
