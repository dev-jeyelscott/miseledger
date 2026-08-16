<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Recipes\PublishRecipeVersion;
use App\Actions\Recipes\SaveRecipeVersion;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->manager = User::factory()->create();

    OrganizationMembership::factory()
        ->for($this->organization)
        ->for($this->manager)
        ->create(['role' => OrganizationRole::Manager]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->storageLocation = new StorageLocation;
    $this->storageLocation->organization_id = $this->organization->id;
    $this->storageLocation->location_id = $this->location->id;
    $this->storageLocation->name = 'Main Storage';
    $this->storageLocation->code = 'A';
    $this->storageLocation->active = true;
    $this->storageLocation->save();

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $this->yieldUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Flour',
    ]);

    $this->recipe = Recipe::factory()->for($this->organization)->create();
});

function publishCostControllerRecipe(array $componentOverrides = []): RecipeVersion
{
    $version = app(SaveRecipeVersion::class)->handle(
        test()->organization,
        test()->manager,
        test()->recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => test()->yieldUnit->id,
            'notes' => null,
            'components' => [
                array_merge([
                    'inventory_item_id' => test()->item->id,
                    'quantity' => '1000',
                    'unit_of_measure_id' => test()->baseUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ], $componentOverrides),
            ],
        ],
    );

    return app(PublishRecipeVersion::class)->handle(
        test()->organization,
        test()->manager,
        $version,
        ['effective_start_date' => '2026-01-01', 'effective_end_date' => null],
    );
}

test('an authorized user views the current cost breakdown at an in-scope location', function () {
    publishCostControllerRecipe();

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::PurchaseReceipt,
        baseQuantity: '5000',
        baseUnitOfMeasure: $this->baseUnit,
        referenceType: 'test',
        referenceId: 1,
        occurredAt: now(),
        idempotencyKey: 'receipt-1',
        inboundUnitCost: '0.0200',
    );

    $this->withSession(['active_organization_id' => $this->organization->id])
        ->actingAs($this->manager)
        ->get(route('recipes.cost', [
            'recipe' => $this->recipe,
            'location_id' => $this->location->id,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recipes/cost')
                ->where('cost.totalCost', '20.0000')
                ->where('cost.complete', true)
                ->where('cost.costPerOutputUnit', '2.0000')
                ->where('cost.components.0.name', 'Flour')
                ->where('cost.components.0.status', 'costed')
                ->where('cost.components.0.warning', null),
        );
});

test('a missing location cost is reported as a warning without breaking the page', function () {
    publishCostControllerRecipe();

    $this->withSession(['active_organization_id' => $this->organization->id])
        ->actingAs($this->manager)
        ->get(route('recipes.cost', [
            'recipe' => $this->recipe,
            'location_id' => $this->location->id,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recipes/cost')
                ->where('cost.complete', false)
                ->where(
                    'cost.components.0.status',
                    'missing_location_cost',
                )
                ->where(
                    'cost.components.0.warning',
                    'No location item cost is available for this inventory item.',
                ),
        );
});

test('the cost view lists only in-scope locations and rejects a location from another organization', function () {
    publishCostControllerRecipe();

    $otherOrganization = Organization::factory()->create();
    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $this->withSession(['active_organization_id' => $this->organization->id])
        ->actingAs($this->manager)
        ->get(route('recipes.cost', [
            'recipe' => $this->recipe,
            'location_id' => $otherLocation->id,
        ]))
        ->assertSessionHasErrors('location_id');

    $this->withSession(['active_organization_id' => $this->organization->id])
        ->actingAs($this->manager)
        ->get(route('recipes.cost', ['recipe' => $this->recipe]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recipes/cost')
                ->has('locationOptions', 1)
                ->where('locationOptions.0.id', $this->location->id)
                ->where('cost', null),
        );
});

test('a user without cost visibility cannot view the recipe cost breakdown', function () {
    publishCostControllerRecipe();

    $kitchenUser = User::factory()->create();

    OrganizationMembership::factory()
        ->for($this->organization)
        ->for($kitchenUser)
        ->create(['role' => OrganizationRole::KitchenStaff]);

    $this->withSession(['active_organization_id' => $this->organization->id])
        ->actingAs($kitchenUser)
        ->get(route('recipes.cost', [
            'recipe' => $this->recipe,
            'location_id' => $this->location->id,
        ]))
        ->assertForbidden();
});

test('no effective recipe version reports an error instead of a cost breakdown', function () {
    $this->withSession(['active_organization_id' => $this->organization->id])
        ->actingAs($this->manager)
        ->get(route('recipes.cost', [
            'recipe' => $this->recipe,
            'location_id' => $this->location->id,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recipes/cost')
                ->where('cost', null)
                ->where('recipeVersion', null)
                ->whereNot('error', null),
        );
});
