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
use App\Support\Recipes\RecipeComponentCostStatus;
use App\Support\Recipes\SimpleRecipeCostResolver;
use App\Support\Recipes\SimpleRecipeCostResolverException;

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

    $this->kgUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'symbol' => 'kg',
        'dimension' => 'weight',
    ]);

    $this->yieldUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
    ]);

    $this->recipe = Recipe::factory()->for($this->organization)->create();
});

function publishSimpleRecipe(array $componentOverrides = []): RecipeVersion
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
                [
                    'inventory_item_id' => test()->item->id,
                    'quantity' => '1',
                    'unit_of_measure_id' => test()->kgUnit->id,
                    'yield_percentage' => '80',
                    'notes' => null,
                    ...$componentOverrides,
                ],
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

function receiveStockForCostTest(Organization $organization, Location $location, StorageLocation $storageLocation, InventoryItem $item, UnitOfMeasure $unit, string $quantity, string $unitCost): void
{
    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $item,
        type: StockMovementType::PurchaseReceipt,
        baseQuantity: $quantity,
        baseUnitOfMeasure: $unit,
        referenceType: 'test',
        referenceId: 1,
        occurredAt: now(),
        idempotencyKey: 'receipt-'.uniqid(),
        inboundUnitCost: $unitCost,
    );
}

test('a component is costed using effective quantity and the location item cost', function () {
    $version = publishSimpleRecipe();

    receiveStockForCostTest($this->organization, $this->location, $this->storageLocation, $this->item, $this->baseUnit, '5000', '0.0200');

    $result = SimpleRecipeCostResolver::resolve($this->organization, $this->location, $version);

    // 1 kg entered = 1000 g base quantity, at 80% yield: 1000 / 0.80 = 1250 g effective.
    // 1250 g * 0.02/g = 25.0000
    expect($result->complete)->toBeTrue()
        ->and($result->totalCost)->toBe('25.0000')
        ->and($result->components)->toHaveCount(1);

    $component = $result->components[0];

    expect($component->status)->toBe(RecipeComponentCostStatus::Costed)
        ->and($component->effectiveQuantity)->toBe('1250.000000')
        ->and($component->unitCost)->toBe('0.0200')
        ->and($component->extendedCost)->toBe('25.0000');
});

test('yield percentage changes the effective quantity used for costing', function () {
    $fullYieldVersion = publishSimpleRecipe(['yield_percentage' => '100']);

    receiveStockForCostTest($this->organization, $this->location, $this->storageLocation, $this->item, $this->baseUnit, '5000', '0.0200');

    $result = SimpleRecipeCostResolver::resolve($this->organization, $this->location, $fullYieldVersion);

    // 1 kg entered = 1000 g base quantity, at 100% yield the effective quantity equals the base quantity.
    expect($result->components[0]->effectiveQuantity)->toBe('1000.000000')
        ->and($result->totalCost)->toBe('20.0000');
});

test('a missing location cost is reported as an incomplete component rather than an invented cost', function () {
    $version = publishSimpleRecipe();

    $result = SimpleRecipeCostResolver::resolve($this->organization, $this->location, $version);

    expect($result->complete)->toBeFalse()
        ->and($result->totalCost)->toBe('0.0000')
        ->and($result->components[0]->status)->toBe(RecipeComponentCostStatus::MissingLocationCost)
        ->and($result->components[0]->unitCost)->toBeNull()
        ->and($result->components[0]->extendedCost)->toBeNull()
        ->and($result->components[0]->warning)->not->toBeNull();
});

test('a nested recipe version component is reported as not costed by simple recipe costing', function () {
    $nested = publishSimpleRecipe();

    receiveStockForCostTest($this->organization, $this->location, $this->storageLocation, $this->item, $this->baseUnit, '5000', '0.0200');

    $parentRecipe = Recipe::factory()->for($this->organization)->create();

    $parentVersion = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $parentRecipe,
        [
            'yield_quantity' => '5',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => [
                [
                    'recipe_version_id' => $nested->id,
                    'quantity' => '10',
                    'unit_of_measure_id' => $this->yieldUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    );

    $publishedParent = app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $parentVersion,
        ['effective_start_date' => '2026-01-01', 'effective_end_date' => null],
    );

    $result = SimpleRecipeCostResolver::resolve($this->organization, $this->location, $publishedParent);

    expect($result->complete)->toBeFalse()
        ->and($result->components[0]->status)->toBe(RecipeComponentCostStatus::NestedRecipeNotCosted)
        ->and($result->components[0]->componentRecipeVersionId)->toBe($nested->id);
});

test('intermediate precision is retained before the extended cost is rounded once', function () {
    $version = publishSimpleRecipe(['quantity' => '1', 'unit_of_measure_id' => $this->baseUnit->id, 'yield_percentage' => '33.33']);

    receiveStockForCostTest($this->organization, $this->location, $this->storageLocation, $this->item, $this->baseUnit, '5000', '0.1000');

    $result = SimpleRecipeCostResolver::resolve($this->organization, $this->location, $version);

    // base quantity 1 g / 0.3333 = 3.000300..., rounded to 3.000300 g effective, at 0.10/g = 0.3000
    expect($result->components[0]->effectiveQuantity)->toBe('3.000300')
        ->and($result->components[0]->extendedCost)->toBe('0.3000');
});

test('resolving cost for a recipe version outside the organization is rejected', function () {
    $version = publishSimpleRecipe();

    $otherOrganization = Organization::factory()->create();

    SimpleRecipeCostResolver::resolve($otherOrganization, $this->location, $version);
})->throws(SimpleRecipeCostResolverException::class);

test('resolving cost for a draft recipe version is rejected', function () {
    $draft = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => [
                [
                    'inventory_item_id' => $this->item->id,
                    'quantity' => '1',
                    'unit_of_measure_id' => $this->kgUnit->id,
                    'yield_percentage' => '80',
                    'notes' => null,
                ],
            ],
        ],
    );

    SimpleRecipeCostResolver::resolve($this->organization, $this->location, $draft);
})->throws(SimpleRecipeCostResolverException::class);

test('resolving cost for a location outside the organization is rejected', function () {
    $version = publishSimpleRecipe();

    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    SimpleRecipeCostResolver::resolve($this->organization, $otherLocation, $version);
})->throws(SimpleRecipeCostResolverException::class);
