<?php

use App\Actions\Inventory\CreateInventoryItemUnit;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Validation\ValidationException;

test('create item unit rejects zero quantity in base unit', function () {
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

    expect(
        fn () => app(CreateInventoryItemUnit::class)->handle(
            $organization,
            $item,
            $kilogram->id,
            '0.000000',
            true,
        ),
    )->toThrow(ValidationException::class);

    expect(InventoryItemUnit::count())->toBe(0);
});

test('create item unit rejects negative quantity in base unit', function () {
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

    expect(
        fn () => app(CreateInventoryItemUnit::class)->handle(
            $organization,
            $item,
            $kilogram->id,
            '-1.500000',
            true,
        ),
    )->toThrow(ValidationException::class);

    expect(InventoryItemUnit::count())->toBe(0);
});

test('create item unit rejects invalid decimal format', function () {
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

    expect(
        fn () => app(CreateInventoryItemUnit::class)->handle(
            $organization,
            $item,
            $kilogram->id,
            'not-a-number',
            true,
        ),
    )->toThrow(ValidationException::class);

    expect(InventoryItemUnit::count())->toBe(0);
});

test('create item unit rejects quantity with excess decimal places', function () {
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

    expect(
        fn () => app(CreateInventoryItemUnit::class)->handle(
            $organization,
            $item,
            $kilogram->id,
            '1.0000001',
            true,
        ),
    )->toThrow(ValidationException::class);

    expect(InventoryItemUnit::count())->toBe(0);
});

test('create item unit accepts valid positive quantity', function () {
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

    $result = app(CreateInventoryItemUnit::class)->handle(
        $organization,
        $item,
        $kilogram->id,
        '1000.000000',
        true,
    );

    expect($result)->toBeInstanceOf(InventoryItemUnit::class);
    expect($result->quantity_in_base_unit)->toBe('1000.000000');
    expect($result->active)->toBeTrue();
});
