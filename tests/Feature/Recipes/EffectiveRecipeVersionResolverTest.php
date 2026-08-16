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
use App\Support\Recipes\EffectiveRecipeVersionResolutionException;
use App\Support\Recipes\EffectiveRecipeVersionResolver;
use Illuminate\Support\Carbon;

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

function draftVersion(): RecipeVersion
{
    return app(SaveRecipeVersion::class)->handle(
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
                    'quantity' => '2.5',
                    'unit_of_measure_id' => test()->baseUnit->id,
                    'yield_percentage' => '90',
                    'notes' => null,
                ],
            ],
        ],
    );
}

function publishedVersion(string $start, ?string $end): RecipeVersion
{
    $version = draftVersion();

    $version->forceFill([
        'status' => RecipeVersionStatus::Published,
        'published_by' => test()->manager->id,
        'published_at' => now(),
        'effective_start_date' => $start,
        'effective_end_date' => $end,
    ])->save();

    return $version->fresh();
}

test('the version whose effective period covers the timestamp resolves', function () {
    $version = publishedVersion('2026-01-01', '2026-06-30');

    $resolved = EffectiveRecipeVersionResolver::resolve(
        $this->recipe,
        Carbon::parse('2026-03-15'),
    );

    expect($resolved->id)->toBe($version->id);
});

test('resolution is inclusive of the effective start and end date boundaries', function () {
    $version = publishedVersion('2026-01-01', '2026-06-30');

    expect(EffectiveRecipeVersionResolver::resolve($this->recipe, Carbon::parse('2026-01-01'))->id)
        ->toBe($version->id)
        ->and(EffectiveRecipeVersionResolver::resolve($this->recipe, Carbon::parse('2026-06-30'))->id)
        ->toBe($version->id);
});

test('a timestamp before the effective start date fails to resolve', function () {
    publishedVersion('2026-01-01', '2026-06-30');

    expect(fn () => EffectiveRecipeVersionResolver::resolve(
        $this->recipe,
        Carbon::parse('2025-12-31'),
    ))->toThrow(EffectiveRecipeVersionResolutionException::class);
});

test('a timestamp after the effective end date fails to resolve', function () {
    publishedVersion('2026-01-01', '2026-06-30');

    expect(fn () => EffectiveRecipeVersionResolver::resolve(
        $this->recipe,
        Carbon::parse('2026-07-01'),
    ))->toThrow(EffectiveRecipeVersionResolutionException::class);
});

test('a recipe with no published versions fails to resolve', function () {
    draftVersion();

    expect(fn () => EffectiveRecipeVersionResolver::resolve(
        $this->recipe,
        Carbon::parse('2026-03-15'),
    ))->toThrow(EffectiveRecipeVersionResolutionException::class);
});

test('an open-ended effective period resolves for any timestamp on or after its start', function () {
    $version = publishedVersion('2026-01-01', null);

    expect(EffectiveRecipeVersionResolver::resolve($this->recipe, Carbon::parse('2030-01-01'))->id)
        ->toBe($version->id);
});

test('historical versions remain resolvable at their own effective timestamps', function () {
    $historical = publishedVersion('2026-01-01', '2026-06-30');
    $current = publishedVersion('2026-07-01', null);

    expect(EffectiveRecipeVersionResolver::resolve($this->recipe, Carbon::parse('2026-02-01'))->id)
        ->toBe($historical->id)
        ->and(EffectiveRecipeVersionResolver::resolve($this->recipe, Carbon::parse('2026-08-01'))->id)
        ->toBe($current->id);
});

test('overlapping published effective periods fail to resolve as an ambiguous match', function () {
    publishedVersion('2026-01-01', '2026-06-30');
    publishedVersion('2026-03-01', '2026-09-30');

    expect(fn () => EffectiveRecipeVersionResolver::resolve(
        $this->recipe,
        Carbon::parse('2026-04-01'),
    ))->toThrow(EffectiveRecipeVersionResolutionException::class);
});
