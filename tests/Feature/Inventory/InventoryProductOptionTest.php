<?php

use App\Actions\Inventory\SaveInventoryProductOption;
use App\Actions\Inventory\SaveInventoryProductOptionValue;
use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\InventoryProductOptionValue;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('a product family can own a controlled option dimension with values', function () {
    $organization = Organization::factory()->create();
    $product = InventoryProduct::factory()
        ->for($organization)
        ->create();

    $option = app(SaveInventoryProductOption::class)->handle(
        $organization,
        $product,
        ['name' => 'Size', 'active' => true],
    );

    expect($option->inventory_product_id)->toBe($product->id)
        ->and($option->organization_id)->toBe($organization->id);

    $small = app(SaveInventoryProductOptionValue::class)->handle(
        $organization,
        $option,
        ['value' => 'Small', 'active' => true],
    );

    $large = app(SaveInventoryProductOptionValue::class)->handle(
        $organization,
        $option,
        ['value' => 'Large', 'active' => true],
    );

    expect($small->inventory_product_option_id)->toBe($option->id)
        ->and($large->inventory_product_option_id)->toBe($option->id)
        ->and($option->values()->count())->toBe(2)
        ->and($product->options()->count())->toBe(1);
});

test('an option dimension can be updated within its own tenant', function () {
    $organization = Organization::factory()->create();
    $product = InventoryProduct::factory()
        ->for($organization)
        ->create();

    $option = InventoryProductOption::factory()
        ->for($organization)
        ->create(['inventory_product_id' => $product->id, 'name' => 'Color']);

    $updated = app(SaveInventoryProductOption::class)->handle(
        $organization,
        $product,
        ['name' => 'Color', 'active' => false],
        $option,
    );

    expect($updated->id)->toBe($option->id)
        ->and($updated->fresh()->active)->toBeFalse();
});

test('a value can be updated within its own option dimension', function () {
    $organization = Organization::factory()->create();
    $option = InventoryProductOption::factory()
        ->for($organization)
        ->create();

    $value = InventoryProductOptionValue::factory()
        ->for($organization)
        ->create([
            'inventory_product_option_id' => $option->id,
            'value' => 'Red',
        ]);

    $updated = app(SaveInventoryProductOptionValue::class)->handle(
        $organization,
        $option,
        ['value' => 'Red', 'active' => false],
        $value,
    );

    expect($updated->id)->toBe($value->id)
        ->and($updated->fresh()->active)->toBeFalse();
});

test('an option dimension name is unique within its product family but reusable elsewhere', function () {
    $organization = Organization::factory()->create();
    $product = InventoryProduct::factory()
        ->for($organization)
        ->create();
    $otherProduct = InventoryProduct::factory()
        ->for($organization)
        ->create();

    app(SaveInventoryProductOption::class)->handle(
        $organization,
        $product,
        ['name' => 'Size', 'active' => true],
    );

    app(SaveInventoryProductOption::class)->handle(
        $organization,
        $otherProduct,
        ['name' => 'Size', 'active' => true],
    );

    expect(
        fn () => app(SaveInventoryProductOption::class)->handle(
            $organization,
            $product,
            ['name' => 'Size', 'active' => true],
        ),
    )->toThrow(QueryException::class);
});

test('assigning an option dimension to a product family from another organization is rejected', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $foreignProduct = InventoryProduct::factory()
        ->for($otherOrganization)
        ->create();

    expect(
        fn () => app(SaveInventoryProductOption::class)->handle(
            $organization,
            $foreignProduct,
            ['name' => 'Size', 'active' => true],
        ),
    )->toThrow(ValidationException::class);

    $this->assertDatabaseMissing('inventory_product_options', [
        'inventory_product_id' => $foreignProduct->id,
    ]);
});

test('assigning a value to an option dimension from another organization is rejected', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $foreignOption = InventoryProductOption::factory()
        ->for($otherOrganization)
        ->create();

    expect(
        fn () => app(SaveInventoryProductOptionValue::class)->handle(
            $organization,
            $foreignOption,
            ['value' => 'Small', 'active' => true],
        ),
    )->toThrow(ValidationException::class);

    $this->assertDatabaseMissing('inventory_product_option_values', [
        'inventory_product_option_id' => $foreignOption->id,
    ]);
});

test('database rejects an option dimension whose organization differs from its product family', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $product = InventoryProduct::factory()
        ->for($organization)
        ->create();

    expect(
        fn () => DB::table('inventory_product_options')->insert([
            'organization_id' => $otherOrganization->id,
            'inventory_product_id' => $product->id,
            'name' => 'Size',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
    )->toThrow(QueryException::class);
});

test('database rejects an option value whose organization differs from its option dimension', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $option = InventoryProductOption::factory()
        ->for($organization)
        ->create();

    expect(
        fn () => DB::table('inventory_product_option_values')->insert([
            'organization_id' => $otherOrganization->id,
            'inventory_product_option_id' => $option->id,
            'value' => 'Small',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
    )->toThrow(QueryException::class);
});

test('deleting a product family cascades to its option dimensions and values', function () {
    $organization = Organization::factory()->create();
    $product = InventoryProduct::factory()
        ->for($organization)
        ->create();

    $option = InventoryProductOption::factory()
        ->for($organization)
        ->create(['inventory_product_id' => $product->id]);

    InventoryProductOptionValue::factory()
        ->for($organization)
        ->create(['inventory_product_option_id' => $option->id]);

    $product->delete();

    expect(InventoryProductOption::query()->whereKey($option->id)->exists())
        ->toBeFalse()
        ->and(
            InventoryProductOptionValue::query()
                ->where('inventory_product_option_id', $option->id)
                ->exists(),
        )->toBeFalse();
});
