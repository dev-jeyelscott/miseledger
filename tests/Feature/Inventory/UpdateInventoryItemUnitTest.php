<?php

use App\Actions\Inventory\UpdateInventoryItemUnit;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Validation\ValidationException;

test('update item unit rejects zero quantity in base unit', function () {
    $organization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $gram->id,
    ]);

    $conversion = InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $kilogram->id,
        'quantity_in_base_unit' => '1000.000000',
        'active' => true,
    ]);

    expect(
        fn () => app(UpdateInventoryItemUnit::class)->handle(
            $organization,
            $item,
            $conversion,
            '0.000000',
            true,
        ),
    )->toThrow(ValidationException::class);

    $conversion->refresh();
    expect($conversion->quantity_in_base_unit)->toBe('1000.000000');
});

test('update item unit rejects negative quantity in base unit', function () {
    $organization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $gram->id,
    ]);

    $conversion = InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $kilogram->id,
        'quantity_in_base_unit' => '1000.000000',
        'active' => true,
    ]);

    expect(
        fn () => app(UpdateInventoryItemUnit::class)->handle(
            $organization,
            $item,
            $conversion,
            '-1.500000',
            true,
        ),
    )->toThrow(ValidationException::class);

    $conversion->refresh();
    expect($conversion->quantity_in_base_unit)->toBe('1000.000000');
});

test('update item unit rejects invalid decimal format', function () {
    $organization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $gram->id,
    ]);

    $conversion = InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $kilogram->id,
        'quantity_in_base_unit' => '1000.000000',
        'active' => true,
    ]);

    expect(
        fn () => app(UpdateInventoryItemUnit::class)->handle(
            $organization,
            $item,
            $conversion,
            'not-a-number',
            true,
        ),
    )->toThrow(ValidationException::class);

    $conversion->refresh();
    expect($conversion->quantity_in_base_unit)->toBe('1000.000000');
});

test('update item unit rejects quantity with excess decimal places', function () {
    $organization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $gram->id,
    ]);

    $conversion = InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $kilogram->id,
        'quantity_in_base_unit' => '1000.000000',
        'active' => true,
    ]);

    expect(
        fn () => app(UpdateInventoryItemUnit::class)->handle(
            $organization,
            $item,
            $conversion,
            '1.0000001',
            true,
        ),
    )->toThrow(ValidationException::class);

    $conversion->refresh();
    expect($conversion->quantity_in_base_unit)->toBe('1000.000000');
});

test('update item unit accepts valid positive quantity', function () {
    $organization = Organization::factory()->create();

    $gram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $kilogram = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $gram->id,
    ]);

    $conversion = InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $kilogram->id,
        'quantity_in_base_unit' => '1000.000000',
        'active' => true,
    ]);

    $result = app(UpdateInventoryItemUnit::class)->handle(
        $organization,
        $item,
        $conversion,
        '2000.000000',
        false,
    );

    expect($result)->toBeInstanceOf(InventoryItemUnit::class);
    expect($result->quantity_in_base_unit)->toBe('2000.000000');
    expect($result->active)->toBeFalse();
});
