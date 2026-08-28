<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\SaveInventoryItem;
use App\Actions\Inventory\SyncInventoryItemOptionValues;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\InventoryProductOptionValue;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use Illuminate\Validation\ValidationException;

function createVariantItem(Organization $organization, InventoryProduct $product, string $sku): InventoryItem
{
    $unit = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
    ]);

    return InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $unit->id,
        'inventory_product_id' => $product->id,
        'sku' => $sku,
    ]);
}

test('a product-family item can be associated with values from its own family', function () {
    $organization = Organization::factory()->create();
    $product = InventoryProduct::factory()->for($organization)->create();
    $option = InventoryProductOption::factory()->for($organization)->create([
        'inventory_product_id' => $product->id,
    ]);
    $small = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
        'value' => 'Small',
    ]);
    $red = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
        'value' => 'Red',
    ]);

    $item = createVariantItem($organization, $product, 'SKU-VAR-1');

    $updated = app(SyncInventoryItemOptionValues::class)->handle(
        $organization,
        $item,
        [$small->id, $red->id],
    );

    expect($updated->optionValueAssociations()->count())->toBe(2)
        ->and(
            $updated->optionValueAssociations()
                ->pluck('inventory_product_option_value_id')
                ->sort()
                ->values()
                ->all(),
        )->toBe([$small->id, $red->id]);
});

test('re-syncing option values replaces the prior association set', function () {
    $organization = Organization::factory()->create();
    $product = InventoryProduct::factory()->for($organization)->create();
    $option = InventoryProductOption::factory()->for($organization)->create([
        'inventory_product_id' => $product->id,
    ]);
    $small = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
    ]);
    $large = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
    ]);

    $item = createVariantItem($organization, $product, 'SKU-VAR-2');

    app(SyncInventoryItemOptionValues::class)->handle($organization, $item, [$small->id]);
    $updated = app(SyncInventoryItemOptionValues::class)->handle($organization, $item, [$large->id]);

    expect(
        $updated->optionValueAssociations()
            ->pluck('inventory_product_option_value_id')
            ->all(),
    )->toBe([$large->id]);
});

test('an item cannot be assigned an option value from another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $product = InventoryProduct::factory()->for($organization)->create();
    $item = createVariantItem($organization, $product, 'SKU-VAR-3');

    $otherProduct = InventoryProduct::factory()->for($otherOrganization)->create();
    $otherOption = InventoryProductOption::factory()->for($otherOrganization)->create([
        'inventory_product_id' => $otherProduct->id,
    ]);
    $otherValue = InventoryProductOptionValue::factory()->for($otherOrganization)->create([
        'inventory_product_option_id' => $otherOption->id,
    ]);

    expect(
        fn () => app(SyncInventoryItemOptionValues::class)->handle(
            $organization,
            $item,
            [$otherValue->id],
        ),
    )->toThrow(ValidationException::class);
});

test('an item cannot be assigned an option value from an unrelated product family', function () {
    $organization = Organization::factory()->create();

    $product = InventoryProduct::factory()->for($organization)->create();
    $item = createVariantItem($organization, $product, 'SKU-VAR-4');

    $unrelatedProduct = InventoryProduct::factory()->for($organization)->create();
    $unrelatedOption = InventoryProductOption::factory()->for($organization)->create([
        'inventory_product_id' => $unrelatedProduct->id,
    ]);
    $unrelatedValue = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $unrelatedOption->id,
    ]);

    expect(
        fn () => app(SyncInventoryItemOptionValues::class)->handle(
            $organization,
            $item,
            [$unrelatedValue->id],
        ),
    )->toThrow(ValidationException::class);
});

test('an item without a product family cannot be assigned option values', function () {
    $organization = Organization::factory()->create();
    $unit = UnitOfMeasure::factory()->create(['organization_id' => $organization->id]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $unit->id,
        'inventory_product_id' => null,
    ]);

    $product = InventoryProduct::factory()->for($organization)->create();
    $option = InventoryProductOption::factory()->for($organization)->create([
        'inventory_product_id' => $product->id,
    ]);
    $value = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
    ]);

    expect(
        fn () => app(SyncInventoryItemOptionValues::class)->handle(
            $organization,
            $item,
            [$value->id],
        ),
    )->toThrow(ValidationException::class);
});

test('an item with option values cannot change or clear its product family', function () {
    $organization = Organization::factory()->create();
    $product = InventoryProduct::factory()->for($organization)->create();
    $option = InventoryProductOption::factory()->for($organization)->create([
        'inventory_product_id' => $product->id,
    ]);
    $value = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
    ]);
    $item = createVariantItem($organization, $product, 'SKU-VAR-5');
    $otherProduct = InventoryProduct::factory()->for($organization)->create();

    app(SyncInventoryItemOptionValues::class)->handle($organization, $item, [$value->id]);

    $attributes = fn (?int $inventoryProductId): array => [
        'name' => $item->name,
        'sku' => $item->sku,
        'base_unit_of_measure_id' => $item->base_unit_of_measure_id,
        'inventory_category_id' => $item->inventory_category_id,
        'inventory_brand_id' => $item->inventory_brand_id,
        'inventory_product_id' => $inventoryProductId,
        'model_number' => $item->model_number,
        'manufacturer_part_number' => $item->manufacturer_part_number,
        'description' => $item->description,
        'type' => $item->type,
        'yield_percentage' => $item->yield_percentage,
        'active' => $item->active,
    ];

    expect(
        fn () => app(SaveInventoryItem::class)->handle(
            $organization,
            $attributes($otherProduct->id),
            $item,
        ),
    )->toThrow(ValidationException::class)
        ->and($item->fresh()->inventory_product_id)->toBe($product->id)
        ->and($item->fresh()->optionValueAssociations()->pluck('inventory_product_option_value_id')->all())
        ->toBe([$value->id]);

    expect(
        fn () => app(SaveInventoryItem::class)->handle(
            $organization,
            $attributes(null),
            $item,
        ),
    )->toThrow(ValidationException::class)
        ->and($item->fresh()->inventory_product_id)->toBe($product->id)
        ->and($item->fresh()->optionValueAssociations()->pluck('inventory_product_option_value_id')->all())
        ->toBe([$value->id]);
});

test('each associated variant item retains its own sku and independent stock balance', function () {
    $organization = Organization::factory()->create();
    $product = InventoryProduct::factory()->for($organization)->create();
    $option = InventoryProductOption::factory()->for($organization)->create([
        'inventory_product_id' => $product->id,
    ]);
    $small = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
        'value' => 'Small',
    ]);
    $large = InventoryProductOptionValue::factory()->for($organization)->create([
        'inventory_product_option_id' => $option->id,
        'value' => 'Large',
    ]);

    $smallItem = createVariantItem($organization, $product, 'SKU-SMALL');
    $largeItem = createVariantItem($organization, $product, 'SKU-LARGE');

    app(SyncInventoryItemOptionValues::class)->handle($organization, $smallItem, [$small->id]);
    app(SyncInventoryItemOptionValues::class)->handle($organization, $largeItem, [$large->id]);

    expect($smallItem->sku)->not->toBe($largeItem->sku);

    $location = Location::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $storageLocation = new StorageLocation([
        'name' => 'Main Storage',
        'code' => 'MAIN',
        'active' => true,
    ]);
    $storageLocation->organization()->associate($organization);
    $storageLocation->location()->associate($location);
    $storageLocation->save();

    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $smallItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '5',
        baseUnitOfMeasure: $smallItem->baseUnitOfMeasure,
        referenceType: 'opening_balance',
        referenceId: $smallItem->id,
        occurredAt: now()->subHour(),
        idempotencyKey: "variant-test:opening:{$smallItem->id}",
        inboundUnitCost: '2',
    );

    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $largeItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '9',
        baseUnitOfMeasure: $largeItem->baseUnitOfMeasure,
        referenceType: 'opening_balance',
        referenceId: $largeItem->id,
        occurredAt: now()->subHour(),
        idempotencyKey: "variant-test:opening:{$largeItem->id}",
        inboundUnitCost: '2',
    );

    expect(
        StockBalance::query()->where('inventory_item_id', $smallItem->id)->sole()->quantity_on_hand,
    )->toBe('5.000000')
        ->and(
            StockBalance::query()->where('inventory_item_id', $largeItem->id)->sole()->quantity_on_hand,
        )->toBe('9.000000');
});
