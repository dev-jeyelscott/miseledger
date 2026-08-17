<?php

use App\Actions\Recipes\PublishRecipeVersion;
use App\Actions\Recipes\SaveRecipeVersion;
use App\Enums\OrganizationRole;
use App\Enums\RecipeVersionStatus;
use App\Models\AuditLog;
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

function publishRecipeVersion(RecipeVersion $version): RecipeVersion
{
    $version->status = RecipeVersionStatus::Published;
    $version->published_at = now();
    $version->save();

    return $version->fresh();
}

test('a recipe version can nest a published recipe version as a component', function () {
    $nestedRecipe = Recipe::factory()
        ->for($this->organization)
        ->create();

    $nestedVersion = publishRecipeVersion(
        app(SaveRecipeVersion::class)->handle(
            $this->organization,
            $this->manager,
            $nestedRecipe,
            [
                'yield_quantity' => '10',
                'yield_unit_id' => $this->yieldUnit->id,
                'notes' => null,
                'components' => saveRecipeVersionComponentsPayload(
                    $this->item,
                    $this->baseUnit,
                ),
            ],
        ),
    );

    $version = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => [
                [
                    'recipe_version_id' => $nestedVersion->id,
                    'quantity' => '3',
                    'unit_of_measure_id' => $this->yieldUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    );

    $component = $version->components()->sole();

    expect($component->component_recipe_version_id)->toBe($nestedVersion->id)
        ->and($component->inventory_item_id)->toBeNull()
        ->and($component->base_quantity)->toBe('3.000000');
});

test('only published recipe versions can be nested as components', function () {
    $nestedRecipe = Recipe::factory()
        ->for($this->organization)
        ->create();

    $draftNestedVersion = app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $nestedRecipe,
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
                    'recipe_version_id' => $draftNestedVersion->id,
                    'quantity' => '3',
                    'unit_of_measure_id' => $this->yieldUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    ))->toThrow(ValidationException::class);
});

test('a nested recipe version output is required', function () {
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
                    'recipe_version_id' => null,
                    'inventory_item_id' => null,
                    'quantity' => '3',
                    'unit_of_measure_id' => $this->yieldUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    ))->toThrow(ValidationException::class);
});

test('cross-tenant recipe version references fail', function () {
    $otherOrganization = Organization::factory()->create();

    $otherYieldUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $otherBaseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $otherOrganization->id,
        'base_unit_of_measure_id' => $otherBaseUnit->id,
    ]);

    OrganizationMembership::factory()
        ->for($otherOrganization)
        ->for($this->manager)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $foreignRecipe = Recipe::factory()
        ->for($otherOrganization)
        ->create();

    $foreignVersion = publishRecipeVersion(
        app(SaveRecipeVersion::class)->handle(
            $otherOrganization,
            $this->manager,
            $foreignRecipe,
            [
                'yield_quantity' => '10',
                'yield_unit_id' => $otherYieldUnit->id,
                'notes' => null,
                'components' => saveRecipeVersionComponentsPayload(
                    $otherItem,
                    $otherBaseUnit,
                ),
            ],
        ),
    );

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
                    'recipe_version_id' => $foreignVersion->id,
                    'quantity' => '3',
                    'unit_of_measure_id' => $this->yieldUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    ))->toThrow(ValidationException::class);
});

test('direct nested recipe version cycles fail', function () {
    $publishedVersion = publishRecipeVersion(
        app(SaveRecipeVersion::class)->handle(
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
        ),
    );

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
                    'recipe_version_id' => $publishedVersion->id,
                    'quantity' => '3',
                    'unit_of_measure_id' => $this->yieldUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    ))->toThrow(ValidationException::class);

    expect(RecipeVersion::query()->count())->toBe(1);
});

test('indirect nested recipe version cycles fail', function () {
    $middleRecipe = Recipe::factory()
        ->for($this->organization)
        ->create();

    $rootVersion = publishRecipeVersion(
        app(SaveRecipeVersion::class)->handle(
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
        ),
    );

    $middleVersion = publishRecipeVersion(
        app(SaveRecipeVersion::class)->handle(
            $this->organization,
            $this->manager,
            $middleRecipe,
            [
                'yield_quantity' => '10',
                'yield_unit_id' => $this->yieldUnit->id,
                'notes' => null,
                'components' => [
                    [
                        'recipe_version_id' => $rootVersion->id,
                        'quantity' => '3',
                        'unit_of_measure_id' => $this->yieldUnit->id,
                        'yield_percentage' => '100',
                        'notes' => null,
                    ],
                ],
            ],
        ),
    );

    expect(fn () => app(SaveRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $this->recipe,
        [
            'yield_quantity' => '12',
            'yield_unit_id' => $this->yieldUnit->id,
            'notes' => null,
            'components' => [
                [
                    'recipe_version_id' => $middleVersion->id,
                    'quantity' => '2',
                    'unit_of_measure_id' => $this->yieldUnit->id,
                    'yield_percentage' => '100',
                    'notes' => null,
                ],
            ],
        ],
    ))->toThrow(ValidationException::class);
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

function draftRecipeVersionForTest(
    Organization $organization,
    User $actor,
    Recipe $recipe,
    InventoryItem $item,
    UnitOfMeasure $baseUnit,
    UnitOfMeasure $yieldUnit,
): RecipeVersion {
    return app(SaveRecipeVersion::class)->handle(
        $organization,
        $actor,
        $recipe,
        [
            'yield_quantity' => '10',
            'yield_unit_id' => $yieldUnit->id,
            'notes' => null,
            'components' => saveRecipeVersionComponentsPayload(
                $item,
                $baseUnit,
            ),
        ],
    );
}

test('a manager can publish a valid draft recipe version', function () {
    $version = draftRecipeVersionForTest(
        $this->organization,
        $this->manager,
        $this->recipe,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
    );

    $published = app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $version,
        [
            'effective_start_date' => '2026-09-01',
            'effective_end_date' => null,
        ],
    );

    expect($published->status)->toBe(RecipeVersionStatus::Published)
        ->and($published->published_by)->toBe($this->manager->id)
        ->and($published->published_at)->not->toBeNull()
        ->and($published->effective_start_date->toDateString())->toBe('2026-09-01')
        ->and($published->effective_end_date)->toBeNull();

    $entry = AuditLog::query()
        ->where('organization_id', $this->organization->id)
        ->where('entity_type', 'recipe_version')
        ->where('entity_id', $published->id)
        ->where('action', 'recipe_version.published')
        ->sole();

    expect($entry->actor_id)->toBe($this->manager->id);
});

test('an already published recipe version cannot be published again', function () {
    $version = publishRecipeVersion(
        draftRecipeVersionForTest(
            $this->organization,
            $this->manager,
            $this->recipe,
            $this->item,
            $this->baseUnit,
            $this->yieldUnit,
        ),
    );

    expect(fn () => app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $version,
        [
            'effective_start_date' => '2026-09-01',
            'effective_end_date' => null,
        ],
    ))->toThrow(ValidationException::class);
});

test('published recipe versions are immutable once published', function () {
    $version = app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        draftRecipeVersionForTest(
            $this->organization,
            $this->manager,
            $this->recipe,
            $this->item,
            $this->baseUnit,
            $this->yieldUnit,
        ),
        [
            'effective_start_date' => '2026-09-01',
            'effective_end_date' => null,
        ],
    );

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

    expect($version->fresh()->yield_quantity)->toBe('10.000000');
});

test('overlapping effective periods on the same recipe fail to publish', function () {
    publishRecipeVersion(
        draftRecipeVersionForTest(
            $this->organization,
            $this->manager,
            $this->recipe,
            $this->item,
            $this->baseUnit,
            $this->yieldUnit,
        ),
    )->forceFill([
        'effective_start_date' => '2026-01-01',
        'effective_end_date' => '2026-12-31',
    ])->save();

    $secondVersion = draftRecipeVersionForTest(
        $this->organization,
        $this->manager,
        $this->recipe,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
    );

    expect(fn () => app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $secondVersion,
        [
            'effective_start_date' => '2026-06-01',
            'effective_end_date' => null,
        ],
    ))->toThrow(ValidationException::class);

    expect($secondVersion->fresh()->status)->toBe(RecipeVersionStatus::Draft);
});

test('non-overlapping effective periods on the same recipe can both publish', function () {
    publishRecipeVersion(
        draftRecipeVersionForTest(
            $this->organization,
            $this->manager,
            $this->recipe,
            $this->item,
            $this->baseUnit,
            $this->yieldUnit,
        ),
    )->forceFill([
        'effective_start_date' => '2026-01-01',
        'effective_end_date' => '2026-06-30',
    ])->save();

    $secondVersion = draftRecipeVersionForTest(
        $this->organization,
        $this->manager,
        $this->recipe,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
    );

    $published = app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $secondVersion,
        [
            'effective_start_date' => '2026-07-01',
            'effective_end_date' => null,
        ],
    );

    expect($published->status)->toBe(RecipeVersionStatus::Published);
});

test('publishing fails when a component item has become inactive', function () {
    $version = draftRecipeVersionForTest(
        $this->organization,
        $this->manager,
        $this->recipe,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
    );

    $this->item->update(['active' => false]);

    expect(fn () => app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $version,
        [
            'effective_start_date' => '2026-09-01',
            'effective_end_date' => null,
        ],
    ))->toThrow(ValidationException::class);

    expect($version->fresh()->status)->toBe(RecipeVersionStatus::Draft);
});

test('publishing requires a valid effective start date', function () {
    $version = draftRecipeVersionForTest(
        $this->organization,
        $this->manager,
        $this->recipe,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
    );

    expect(fn () => app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $this->manager,
        $version,
        [
            'effective_start_date' => null,
            'effective_end_date' => null,
        ],
    ))->toThrow(ValidationException::class);
});

test('kitchen staff cannot publish recipe versions', function () {
    $staff = User::factory()->create();

    OrganizationMembership::factory()
        ->for($this->organization)
        ->for($staff)
        ->create([
            'role' => OrganizationRole::KitchenStaff,
        ]);

    $version = draftRecipeVersionForTest(
        $this->organization,
        $this->manager,
        $this->recipe,
        $this->item,
        $this->baseUnit,
        $this->yieldUnit,
    );

    $this->withoutExceptionHandling();

    expect(fn () => app(PublishRecipeVersion::class)->handle(
        $this->organization,
        $staff,
        $version,
        [
            'effective_start_date' => '2026-09-01',
            'effective_end_date' => null,
        ],
    ))->toThrow(HttpException::class);

    expect($version->fresh()->status)->toBe(RecipeVersionStatus::Draft);
});
