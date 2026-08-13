<?php

use App\Actions\Inventory\ConvertQuantity;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Validation\ValidationException;

test('same unit conversion preserves fixed precision', function () {
    $organization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $gram->id,
    ]);

    $converted = app(ConvertQuantity::class)->handle(
        $organization,
        $item,
        '5.125000',
        $gram,
        $gram,
    );

    expect($converted)->toBe('5.125000');
});

test('standard weight conversion converts kilograms to grams', function () {
    $organization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $gram->id,
    ]);

    expect(
        app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '1.234567',
            $kilogram,
            $gram,
        ),
    )->toBe('1234.567000');

    expect(
        app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '1234.567000',
            $gram,
            $kilogram,
        ),
    )->toBe('1.234567');
});

test('standard volume conversion converts liters to milliliters', function () {
    $organization = Organization::factory()->create();

    $milliliter = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Milliliter',
        'symbol' => 'ml',
        'dimension' => 'volume',
    ]);

    $liter = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Liter',
        'symbol' => 'l',
        'dimension' => 'volume',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $milliliter->id,
    ]);

    expect(
        app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '2.500000',
            $liter,
            $milliliter,
        ),
    )->toBe('2500.000000');
});

test('direct item conversion converts cases into the item base unit', function () {
    $organization = Organization::factory()->create();

    $bottle = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Bottle',
        'symbol' => 'bottle',
        'dimension' => 'count',
    ]);

    $case = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Case',
        'symbol' => 'case',
        'dimension' => 'count',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $bottle->id,
    ]);

    InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $case->id,
        'quantity_in_base_unit' => '24.000000',
        'active' => true,
    ]);

    expect(
        app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '2.500000',
            $case,
            $bottle,
        ),
    )->toBe('60.000000');
});

test('inverse item conversion converts base quantity back to alternate unit', function () {
    $organization = Organization::factory()->create();

    $bottle = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Bottle',
        'symbol' => 'bottle',
        'dimension' => 'count',
    ]);

    $case = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Case',
        'symbol' => 'case',
        'dimension' => 'count',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $bottle->id,
    ]);

    InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $case->id,
        'quantity_in_base_unit' => '24.000000',
        'active' => true,
    ]);

    expect(
        app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '48.000000',
            $bottle,
            $case,
        ),
    )->toBe('2.000000');
});

test('cross-dimension item conversion fails', function () {
    $organization = Organization::factory()->create();

    $kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
    ]);

    $sack = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Sack',
        'symbol' => 'sack',
        'dimension' => 'count',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $kilogram->id,
    ]);

    InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $sack->id,
        'quantity_in_base_unit' => '25.000000',
        'active' => true,
    ]);

    expect(
        fn () => app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '2.000000',
            $sack,
            $kilogram,
        ),
    )->toThrow(ValidationException::class);
});

test('unsupported count conversion fails explicitly', function () {
    $organization = Organization::factory()->create();

    $piece = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Piece',
        'symbol' => 'piece',
        'dimension' => 'count',
    ]);

    $bottle = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Bottle',
        'symbol' => 'bottle',
        'dimension' => 'count',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $bottle->id,
    ]);

    expect(
        fn () => app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '5.000000',
            $piece,
            $bottle,
        ),
    )->toThrow(ValidationException::class);
});

test('dimension mismatch fails without explicit item conversion', function () {
    $organization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $milliliter = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Milliliter',
        'symbol' => 'ml',
        'dimension' => 'volume',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $gram->id,
    ]);

    expect(
        fn () => app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '5.000000',
            $milliliter,
            $gram,
        ),
    )->toThrow(ValidationException::class);
});

test('cross tenant unit access is rejected', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $bottle = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Bottle',
        'symbol' => 'bottle',
        'dimension' => 'count',
    ]);

    $otherCase = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Case',
        'symbol' => 'case',
        'dimension' => 'count',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $bottle->id,
    ]);

    expect(
        fn () => app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '1.000000',
            $otherCase,
            $bottle,
        ),
    )->toThrow(ValidationException::class);
});

test('cross tenant inventory item access is rejected', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $otherGram = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Other Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $otherOrganization->id,
        'base_unit_of_measure_id' => $otherGram->id,
    ]);

    expect(
        fn () => app(ConvertQuantity::class)->handle(
            $organization,
            $otherItem,
            '1.000000',
            $gram,
            $gram,
        ),
    )->toThrow(ValidationException::class);
});

test('decimal precision is preserved without floating point arithmetic', function () {
    $organization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $pound = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Pound',
        'symbol' => 'lb',
        'dimension' => 'weight',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $gram->id,
    ]);

    expect(
        app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '0.123456',
            $pound,
            $gram,
        ),
    )->toBe('55.998700');
});

test('inactive item conversion is not used', function () {
    $organization = Organization::factory()->create();

    $bottle = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Bottle',
        'symbol' => 'bottle',
        'dimension' => 'count',
    ]);

    $case = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Case',
        'symbol' => 'case',
        'dimension' => 'count',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $bottle->id,
    ]);

    InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $case->id,
        'quantity_in_base_unit' => '24.000000',
        'active' => false,
    ]);

    expect(
        fn () => app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '1.000000',
            $case,
            $bottle,
        ),
    )->toThrow(ValidationException::class);
});

test('item conversions do not traverse through the base unit', function () {
    $organization = Organization::factory()->create();

    $bottle = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Bottle',
        'symbol' => 'bottle',
        'dimension' => 'count',
    ]);

    $case = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Case',
        'symbol' => 'case',
        'dimension' => 'count',
    ]);

    $pallet = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Pallet',
        'symbol' => 'pallet',
        'dimension' => 'count',
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $bottle->id,
    ]);

    InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $case->id,
        'quantity_in_base_unit' => '24.000000',
    ]);

    InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $pallet->id,
        'quantity_in_base_unit' => '240.000000',
    ]);

    expect(
        fn () => app(ConvertQuantity::class)->handle(
            $organization,
            $item,
            '10.000000',
            $case,
            $pallet,
        ),
    )->toThrow(ValidationException::class);
});
