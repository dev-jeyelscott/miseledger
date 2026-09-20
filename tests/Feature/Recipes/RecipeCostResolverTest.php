<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Recipes\PublishRecipeVersion;
use App\Actions\Recipes\SaveRecipeVersion;
use App\Enums\OrganizationRole;
use App\Enums\RecipeVersionStatus;
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
use App\Support\Recipes\RecipeCostResolver;
use App\Support\Recipes\RecipeCostResolverException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

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
    ]);
});

function receiveStockForNestedCostTest(Organization $organization, Location $location, StorageLocation $storageLocation, InventoryItem $item, UnitOfMeasure $unit, string $quantity, string $unitCost): void
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

function publishBaseRecipeForCostTest(Organization $organization, User $manager, InventoryItem $item, UnitOfMeasure $baseUnit, UnitOfMeasure $yieldUnit, string $yieldQuantity, string $itemQuantity): RecipeVersion
{
    $recipe = Recipe::factory()->for($organization)->create();

    $version = app(SaveRecipeVersion::class)->handle(
        $organization,
        $manager,
        $recipe,
        [
            'yield_quantity' => $yieldQuantity,
            'yield_unit_id' => $yieldUnit->id,
            'notes' => null,
            'components' => [
                [
                    'inventory_item_id' => $item->id,
                    'quantity' => $itemQuantity,
                    'unit_of_measure_id' => $baseUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    );

    return app(PublishRecipeVersion::class)->handle(
        $organization,
        $manager,
        $version,
        ['effective_start_date' => '2026-01-01', 'effective_end_date' => null],
    );
}

function publishNestedRecipeForCostTest(Organization $organization, User $manager, RecipeVersion $nestedVersion, UnitOfMeasure $nestedOutputUnit, string $yieldQuantity, string $nestedQuantity): RecipeVersion
{
    $recipe = Recipe::factory()->for($organization)->create();

    $version = app(SaveRecipeVersion::class)->handle(
        $organization,
        $manager,
        $recipe,
        [
            'yield_quantity' => $yieldQuantity,
            'yield_unit_id' => $nestedOutputUnit->id,
            'notes' => null,
            'components' => [
                [
                    'recipe_version_id' => $nestedVersion->id,
                    'quantity' => $nestedQuantity,
                    'unit_of_measure_id' => $nestedOutputUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    );

    return app(PublishRecipeVersion::class)->handle(
        $organization,
        $manager,
        $version,
        ['effective_start_date' => '2026-01-01', 'effective_end_date' => null],
    );
}

test('a nested recipe component is costed proportionally from its own cost per output unit', function () {
    // Base recipe: 1000 g of item consumed, yields 10 (yieldUnit) of output.
    $baseVersion = publishBaseRecipeForCostTest(
        $this->organization,
        $this->manager,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
        yieldQuantity: '10',
        itemQuantity: '1000',
    );

    receiveStockForNestedCostTest($this->organization, $this->location, $this->storageLocation, $this->item, $this->baseUnit, '5000', '0.0200');

    // Base recipe cost: 1000 g * 0.02/g = 20.0000, cost per output unit = 20.0000 / 10 = 2.0000.
    $parentVersion = publishNestedRecipeForCostTest(
        $this->organization,
        $this->manager,
        $baseVersion,
        $this->yieldUnit,
        yieldQuantity: '5',
        nestedQuantity: '4',
    );

    $result = RecipeCostResolver::resolve($this->organization, $this->location, $parentVersion);

    expect($result->complete)->toBeTrue()
        ->and($result->totalCost)->toBe('8.0000')
        ->and($result->costPerOutputUnit)->toBe('1.6000')
        ->and($result->components)->toHaveCount(1);

    $component = $result->components[0];

    expect($component->status)->toBe(RecipeComponentCostStatus::Costed)
        ->and($component->componentRecipeVersionId)->toBe($baseVersion->id)
        ->and($component->effectiveQuantity)->toBe('4.000000')
        ->and($component->unitCost)->toBe('2.0000')
        ->and($component->extendedCost)->toBe('8.0000')
        ->and($component->nestedCost)->not->toBeNull()
        ->and($component->nestedCost->totalCost)->toBe('20.0000')
        ->and($component->nestedCost->costPerOutputUnit)->toBe('2.0000');
});

test('a multi-level nested recipe resolves cost through every level', function () {
    // Level 0: base recipe consumes item, yields 10.
    $level0 = publishBaseRecipeForCostTest(
        $this->organization,
        $this->manager,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
        yieldQuantity: '10',
        itemQuantity: '1000',
    );

    receiveStockForNestedCostTest($this->organization, $this->location, $this->storageLocation, $this->item, $this->baseUnit, '5000', '0.0200');

    // Level 1: nests level 0, cost per output unit 2.0000, consumes 4 units, yields 5.
    $level1 = publishNestedRecipeForCostTest(
        $this->organization,
        $this->manager,
        $level0,
        $this->yieldUnit,
        yieldQuantity: '5',
        nestedQuantity: '4',
    );

    // Level 2: nests level 1, cost per output unit 1.6000, consumes 2 units, yields 4.
    $level2 = publishNestedRecipeForCostTest(
        $this->organization,
        $this->manager,
        $level1,
        $this->yieldUnit,
        yieldQuantity: '4',
        nestedQuantity: '2',
    );

    $result = RecipeCostResolver::resolve($this->organization, $this->location, $level2);

    // Level 1 total: 4 * 2.0000 = 8.0000, cost per output unit = 8.0000 / 5 = 1.6000.
    // Level 2 total: 2 * 1.6000 = 3.2000, cost per output unit = 3.2000 / 4 = 0.8000.
    expect($result->complete)->toBeTrue()
        ->and($result->totalCost)->toBe('3.2000')
        ->and($result->costPerOutputUnit)->toBe('0.8000');

    $level2Component = $result->components[0];

    expect($level2Component->unitCost)->toBe('1.6000')
        ->and($level2Component->extendedCost)->toBe('3.2000');

    $level1Cost = $level2Component->nestedCost;

    expect($level1Cost->totalCost)->toBe('8.0000')
        ->and($level1Cost->costPerOutputUnit)->toBe('1.6000');

    $level0Cost = $level1Cost->components[0]->nestedCost;

    expect($level0Cost->totalCost)->toBe('20.0000')
        ->and($level0Cost->costPerOutputUnit)->toBe('2.0000');
});

test('an incomplete nested recipe cost is reported without pricing the parent component', function () {
    $baseVersion = publishBaseRecipeForCostTest(
        $this->organization,
        $this->manager,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
        yieldQuantity: '10',
        itemQuantity: '1000',
    );

    // No stock received, so the base recipe cannot be fully costed.
    $parentVersion = publishNestedRecipeForCostTest(
        $this->organization,
        $this->manager,
        $baseVersion,
        $this->yieldUnit,
        yieldQuantity: '5',
        nestedQuantity: '4',
    );

    $result = RecipeCostResolver::resolve($this->organization, $this->location, $parentVersion);

    expect($result->complete)->toBeFalse()
        ->and($result->totalCost)->toBe('0.0000')
        ->and($result->costPerOutputUnit)->toBeNull()
        ->and($result->components[0]->status)->toBe(RecipeComponentCostStatus::NestedRecipeIncomplete)
        ->and($result->components[0]->extendedCost)->toBeNull()
        ->and($result->components[0]->nestedCost->complete)->toBeFalse();
});

test('a reference cycle in the stored component graph is rejected rather than recursing indefinitely', function () {
    $recipeA = Recipe::factory()->for($this->organization)->create();
    $recipeB = Recipe::factory()->for($this->organization)->create();

    $versionA = $recipeA->versions()->create([
        'version_number' => 1,
        'status' => RecipeVersionStatus::Published,
        'yield_quantity' => '5',
        'yield_unit_id' => $this->yieldUnit->id,
        'effective_start_date' => '2026-01-01',
        'effective_end_date' => null,
    ]);

    $versionB = $recipeB->versions()->create([
        'version_number' => 1,
        'status' => RecipeVersionStatus::Published,
        'yield_quantity' => '5',
        'yield_unit_id' => $this->yieldUnit->id,
        'effective_start_date' => '2026-01-01',
        'effective_end_date' => null,
    ]);

    // Bypass SaveRecipeVersion's cycle guard to simulate a corrupted graph.
    $versionA->components()->create([
        'component_recipe_version_id' => $versionB->id,
        'inventory_item_id' => null,
        'quantity' => '1',
        'unit_of_measure_id' => $this->yieldUnit->id,
        'base_quantity' => '1',
        'yield_percentage' => '100',
        'notes' => null,
    ]);

    $versionB->components()->create([
        'component_recipe_version_id' => $versionA->id,
        'inventory_item_id' => null,
        'quantity' => '1',
        'unit_of_measure_id' => $this->yieldUnit->id,
        'base_quantity' => '1',
        'yield_percentage' => '100',
        'notes' => null,
    ]);

    RecipeCostResolver::resolve($this->organization, $this->location, $versionA);
})->throws(RecipeCostResolverException::class);

test('resolving nested cost for a recipe version outside the organization is rejected', function () {
    $baseVersion = publishBaseRecipeForCostTest(
        $this->organization,
        $this->manager,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
        yieldQuantity: '10',
        itemQuantity: '1000',
    );

    $otherOrganization = Organization::factory()->create();

    RecipeCostResolver::resolve($otherOrganization, $this->location, $baseVersion);
})->throws(RecipeCostResolverException::class);

test('costing a graph with multiple inventory leaves and a shared nested recipe batches stock balance and component queries', function () {
    $secondItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
    ]);

    // Shared base recipe: 1000 g of item consumed, yields 10 (yieldUnit) of output.
    $sharedBase = publishBaseRecipeForCostTest(
        $this->organization,
        $this->manager,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
        yieldQuantity: '10',
        itemQuantity: '1000',
    );

    receiveStockForNestedCostTest($this->organization, $this->location, $this->storageLocation, $this->item, $this->baseUnit, '5000', '0.0200');
    receiveStockForNestedCostTest($this->organization, $this->location, $this->storageLocation, $secondItem, $this->baseUnit, '5000', '0.0500');

    // Two branches, A and B, each nest the same shared base recipe, forming a diamond.
    $branchA = publishNestedRecipeForCostTest(
        $this->organization,
        $this->manager,
        $sharedBase,
        $this->yieldUnit,
        yieldQuantity: '5',
        nestedQuantity: '4',
    );

    $branchB = publishNestedRecipeForCostTest(
        $this->organization,
        $this->manager,
        $sharedBase,
        $this->yieldUnit,
        yieldQuantity: '8',
        nestedQuantity: '2',
    );

    $recipe = Recipe::factory()->for($this->organization)->create();

    $rootVersionDraft = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $recipe,
        [
            'yield_quantity' => '20',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => [
                [
                    'inventory_item_id' => $secondItem->id,
                    'quantity' => '100',
                    'unit_of_measure_id' => $this->baseUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
                [
                    'recipe_version_id' => $branchA->id,
                    'quantity' => '3',
                    'unit_of_measure_id' => $this->yieldUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
                [
                    'recipe_version_id' => $branchB->id,
                    'quantity' => '1',
                    'unit_of_measure_id' => $this->yieldUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    );

    $rootVersion = app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $rootVersionDraft,
        ['effective_start_date' => '2026-01-01', 'effective_end_date' => null],
    );

    $queries = [];

    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $result = RecipeCostResolver::resolve($this->organization, $this->location, $rootVersion);

    $stockBalanceQueries = collect($queries)->filter(
        static fn (string $sql): bool => str_contains($sql, 'from "stock_balances"') || str_contains($sql, 'from stock_balances'),
    );

    $componentQueries = collect($queries)->filter(
        static fn (string $sql): bool => str_contains($sql, 'from "recipe_version_components"') || str_contains($sql, 'from recipe_version_components'),
    );

    expect($stockBalanceQueries)->toHaveCount(1)
        ->and($componentQueries)->toHaveCount(4); // root, branch A, branch B, shared base (loaded once).

    expect($result->complete)->toBeTrue();

    // Shared base cost per output unit: 1000 g * 0.02/g = 20.0000 / 10 = 2.0000.
    // Branch A: 4 * 2.0000 = 8.0000 total, / 5 yield = 1.6000 per unit.
    // Branch B: 2 * 2.0000 = 4.0000 total, / 8 yield = 0.5000 per unit.
    // Second item: 100 g * 0.05/g = 5.0000.
    // Root total: 5.0000 + (3 * 1.6000) + (1 * 0.5000) = 5.0000 + 4.8000 + 0.5000 = 10.3000.
    expect($result->totalCost)->toBe('10.3000');
});

test('resolving nested cost for a location outside the organization is rejected', function () {
    $baseVersion = publishBaseRecipeForCostTest(
        $this->organization,
        $this->manager,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
        yieldQuantity: '10',
        itemQuantity: '1000',
    );

    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    RecipeCostResolver::resolve($this->organization, $otherLocation, $baseVersion);
})->throws(RecipeCostResolverException::class);
