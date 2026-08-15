<?php

use App\Actions\Inventory\RecordWaste;
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
use App\Models\WasteReason;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create active opening-balance storage.
 */
function createOpeningBalanceStorageForTest(
    Organization $organization,
    Location $location,
    string $code = 'MAIN',
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
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->storageLocation =
        createOpeningBalanceStorageForTest(
            $this->organization,
            $this->location,
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
        'name' => 'Flour',
        'sku' => 'FLOUR-001',
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
    'inventory staff can view tenant scoped opening balance options',
    function () {
        $otherOrganization = Organization::factory()->create();

        UnitOfMeasure::factory()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Gram',
            'symbol' => 'g',
            'dimension' => 'weight',
            'active' => true,
        ]);

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(
                route('inventory.opening-balances.create'),
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component(
                        'inventory/opening-balances/create',
                    )
                    ->where('currency', 'PHP')
                    ->has('locationOptions', 1)
                    ->where(
                        'locationOptions.0.id',
                        $this->location->id,
                    )
                    ->has('storageLocationOptions', 1)
                    ->where(
                        'storageLocationOptions.0.id',
                        $this->storageLocation->id,
                    )
                    ->has('inventoryItemOptions', 1)
                    ->where(
                        'inventoryItemOptions.0.id',
                        $this->item->id,
                    )
                    ->where(
                        'inventoryItemOptions.0.baseUnitSymbol',
                        'g',
                    )
                    ->has('unitOptions', 2),
            );
    },
);

test(
    'opening balance establishes cost used by later waste',
    function () {
        $operationId = (string) Str::uuid();

        $openingData = [
            'operation_id' => $operationId,
            'location_id' => $this->location->id,
            'storage_location_id' => $this
                ->storageLocation
                ->id,
            'inventory_item_id' => $this->item->id,
            'quantity' => '2.5',
            'unit_id' => $this->kilogram->id,
            'base_unit_cost' => '0.0400',
            'occurred_at' => now()
                ->setTimezone(
                    $this->organization->timezone,
                )
                ->format('Y-m-d\TH:i'),
            'notes' => 'Initial Flour inventory',
        ];

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(
                route('inventory.opening-balances.store'),
                $openingData,
            )
            ->assertRedirect(
                route('inventory.items.index'),
            );

        $openingMovement = StockMovement::query()
            ->where(
                'type',
                StockMovementType::OpeningBalance->value,
            )
            ->sole();

        $balance = StockBalance::query()->sole();

        expect($openingMovement->quantity)
            ->toBe('2500.000000')
            ->and($openingMovement->unit_cost)
            ->toBe('0.0400')
            ->and($openingMovement->total_cost)
            ->toBe('100.0000')
            ->and($openingMovement->created_by)
            ->toBe($this->actor->id)
            ->and($openingMovement->reference_type)
            ->toBe('manual_opening_balance')
            ->and($openingMovement->reference_id)
            ->toBe($this->item->id)
            ->and($openingMovement->idempotency_key)
            ->toBe(
                "opening_balance:manual:{$operationId}",
            )
            ->and($balance->quantity_on_hand)
            ->toBe('2500.000000')
            ->and($balance->average_unit_cost)
            ->toBe('0.0400')
            ->and($balance->inventory_value)
            ->toBe('100.0000');

        /*
         * Exact retry must not duplicate stock.
         */
        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(
                route('inventory.opening-balances.store'),
                $openingData,
            )
            ->assertRedirect(
                route('inventory.items.index'),
            );

        expect(
            StockMovement::query()
                ->where(
                    'type',
                    StockMovementType::OpeningBalance->value,
                )
                ->count(),
        )
            ->toBe(1)
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('2500.000000');

        $reason = WasteReason::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Expired',
            'active' => true,
        ]);

        $waste = app(RecordWaste::class)->handle(
            $this->organization,
            $this->actor,
            [
                'operation_id' => (string) Str::uuid(),
                'location_id' => $this->location->id,
                'storage_location_id' => $this
                    ->storageLocation
                    ->id,
                'inventory_item_id' => $this->item->id,
                'waste_reason_id' => $reason->id,
                'quantity' => '0.5',
                'unit_id' => $this->kilogram->id,
                'occurred_at' => now()
                    ->addMinute()
                    ->setTimezone(
                        $this->organization->timezone,
                    )
                    ->format('Y-m-d\TH:i'),
                'notes' => 'Expired flour',
            ],
        );

        $wasteMovement = StockMovement::query()
            ->where(
                'type',
                StockMovementType::Waste->value,
            )
            ->sole();

        expect($waste->base_quantity)
            ->toBe('500.000000')
            ->and($waste->unit_cost)
            ->toBe('0.0400')
            ->and($waste->total_cost)
            ->toBe('20.0000')
            ->and($wasteMovement->quantity)
            ->toBe('-500.000000')
            ->and($wasteMovement->unit_cost)
            ->toBe('0.0400')
            ->and($wasteMovement->total_cost)
            ->toBe('20.0000')
            ->and(
                StockBalance::query()
                    ->sole()
                    ->quantity_on_hand,
            )
            ->toBe('2000.000000')
            ->and(
                StockBalance::query()
                    ->sole()
                    ->average_unit_cost,
            )
            ->toBe('0.0400')
            ->and(
                StockBalance::query()
                    ->sole()
                    ->inventory_value,
            )
            ->toBe('80.0000');
    },
);

test(
    'opening balance rejects cross tenant inventory references',
    function () {
        $otherOrganization =
            Organization::factory()->create();

        $otherUnit = UnitOfMeasure::factory()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Gram',
            'symbol' => 'g',
            'dimension' => 'weight',
            'active' => true,
        ]);

        $otherItem = InventoryItem::factory()->create([
            'organization_id' => $otherOrganization->id,
            'base_unit_of_measure_id' => $otherUnit->id,
            'active' => true,
        ]);

        $this
            ->actingAs($this->actor)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->from(
                route('inventory.opening-balances.create'),
            )
            ->post(
                route('inventory.opening-balances.store'),
                [
                    'operation_id' => (string) Str::uuid(),
                    'location_id' => $this->location->id,
                    'storage_location_id' => $this
                        ->storageLocation
                        ->id,
                    'inventory_item_id' => $otherItem->id,
                    'quantity' => '1',
                    'unit_id' => $otherUnit->id,
                    'base_unit_cost' => '1.0000',
                    'occurred_at' => now()
                        ->setTimezone(
                            $this->organization->timezone,
                        )
                        ->format('Y-m-d\TH:i'),
                    'notes' => null,
                ],
            )
            ->assertSessionHasErrors([
                'inventory_item_id',
                'unit_id',
            ]);

        expect(StockMovement::query()->count())
            ->toBe(0)
            ->and(StockBalance::query()->count())
            ->toBe(0);
    },
);

test(
    'users without inventory adjustment permission cannot record opening stock',
    function () {
        $kitchenUser = User::factory()->create();

        OrganizationMembership::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $kitchenUser->id,
            'role' => OrganizationRole::KitchenStaff,
        ]);

        $this
            ->actingAs($kitchenUser)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->get(
                route('inventory.opening-balances.create'),
            )
            ->assertForbidden();

        $this
            ->actingAs($kitchenUser)
            ->withSession([
                'active_organization_id' => $this->organization->id,
            ])
            ->post(
                route('inventory.opening-balances.store'),
                [
                    'operation_id' => (string) Str::uuid(),
                    'location_id' => $this->location->id,
                    'storage_location_id' => $this
                        ->storageLocation
                        ->id,
                    'inventory_item_id' => $this->item->id,
                    'quantity' => '1',
                    'unit_id' => $this->kilogram->id,
                    'base_unit_cost' => '0.0400',
                    'occurred_at' => now()
                        ->setTimezone(
                            $this->organization->timezone,
                        )
                        ->format('Y-m-d\TH:i'),
                    'notes' => null,
                ],
            )
            ->assertForbidden();

        expect(StockMovement::query()->count())
            ->toBe(0);
    },
);
