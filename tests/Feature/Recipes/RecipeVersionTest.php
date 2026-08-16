<?php

use App\Actions\Recipes\SaveRecipeVersion;
use App\Enums\OrganizationRole;
use App\Enums\RecipeVersionStatus;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->manager = User::factory()->create();

    OrganizationMembership::factory()
        ->for($this->organization)
        ->for($this->manager)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $this->recipe = Recipe::factory()
        ->for($this->organization)
        ->create();

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->yieldUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
    ]);
});

function saveRecipeVersionComponentsPayload(
    InventoryItem $item,
    UnitOfMeasure $unit,
    string $quantity = '2.5',
    string $yieldPercentage = '90',
): array {
    return [
        [
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_of_measure_id' => $unit->id,
            'yield_percentage' => $yieldPercentage,
            'notes' => null,
        ],
    ];
}

test('a manager can create a draft recipe version with sequential numbers', function () {
    $version1 = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => saveRecipeVersionComponentsPayload(
                $this->item,
                $this->baseUnit,
            ),
        ],
    );

    expect($version1->version_number)->toBe(1)
        ->and($version1->status)->toBe(RecipeVersionStatus::Draft)
        ->and($version1->recipe_id)->toBe($this->recipe->id)
        ->and($version1->components)->toHaveCount(1);

    $version2 = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '12',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => saveRecipeVersionComponentsPayload(
                $this->item,
                $this->baseUnit,
            ),
        ],
    );

    expect($version2->version_number)->toBe(2)
        ->and($this->recipe->fresh()->code)->toBe($this->recipe->code)
        ->and($this->recipe->fresh()->name)->toBe($this->recipe->name);
});

test('draft recipe versions are editable', function () {
    $version = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => 'initial',
            'components' => saveRecipeVersionComponentsPayload(
                $this->item,
                $this->baseUnit,
            ),
        ],
    );

    $updated = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '15',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => 'revised',
            'components' => saveRecipeVersionComponentsPayload(
                $this->item,
                $this->baseUnit,
                '4',
            ),
        ],
        $version,
    );

    expect($updated->id)->toBe($version->id)
        ->and($updated->version_number)->toBe(1)
        ->and($updated->yield_quantity)->toBe('15.000000')
        ->and($updated->notes)->toBe('revised')
        ->and($updated->components()->sole()->quantity)->toBe('4.000000');
});

test('published recipe versions cannot be edited', function () {
    $version = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => saveRecipeVersionComponentsPayload(
                $this->item,
                $this->baseUnit,
            ),
        ],
    );

    $version->status = RecipeVersionStatus::Published;
    $version->published_at = now();
    $version->save();

    expect(fn () => app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '99',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => saveRecipeVersionComponentsPayload(
                $this->item,
                $this->baseUnit,
            ),
        ],
        $version,
    ))->toThrow(ValidationException::class);

    expect(RecipeVersion::query()->count())->toBe(1);
});

test('item components validate quantities, units, and yields', function () {
    $otherOrganization = Organization::factory()->create();
    $foreignUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    expect(fn () => app(SaveRecipeVersion::class)->handle(
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
                    'quantity' => '0',
                    'unit_of_measure_id' => $this->baseUnit->id,
                    'yield_percentage' => '90',
                    'notes' => null,
                ],
            ],
        ],
    ))->toThrow(ValidationException::class);

    expect(fn () => app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => saveRecipeVersionComponentsPayload(
                $this->item,
                $foreignUnit,
            ),
        ],
    ))->toThrow(ValidationException::class);

    expect(fn () => app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => saveRecipeVersionComponentsPayload(
                $this->item,
                $this->baseUnit,
                '2.5',
                '150',
            ),
        ],
    ))->toThrow(ValidationException::class);

    expect(RecipeVersion::query()->count())->toBe(0);
});

test('kitchen staff cannot create recipe versions', function () {
    $staff = User::factory()->create();

    OrganizationMembership::factory()
        ->for($this->organization)
        ->for($staff)
        ->create([
            'role' => OrganizationRole::KitchenStaff,
        ]);

    $this->withoutExceptionHandling();

    expect(fn () => app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $staff,
        $this->recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => saveRecipeVersionComponentsPayload(
                $this->item,
                $this->baseUnit,
            ),
        ],
    ))->toThrow(HttpException::class);

    expect(RecipeVersion::query()->count())->toBe(0);
});
