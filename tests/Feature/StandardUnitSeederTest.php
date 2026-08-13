<?php

use App\Models\StandardUnit;
use App\Support\Inventory\StandardUnits;
use Database\Seeders\StandardUnitSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

test('seeds the approved global unit catalog deterministically', function () {
    $this->seed(StandardUnitSeeder::class);

    expect(StandardUnit::query()->orderBy('code')->count())
        ->toBe(count(StandardUnits::definitions()));

    foreach (StandardUnits::definitions() as $definition) {
        $this->assertDatabaseHas('standard_units', [
            'code' => $definition['symbol'],
            'name' => $definition['name'],
            'dimension' => $definition['dimension'],
            'canonical_factor' => $definition['canonical_factor'],
            'active' => true,
        ]);
    }
});

test('seeding standard units is idempotent', function () {
    $this->seed(StandardUnitSeeder::class);
    $firstSeed = StandardUnit::query()
        ->orderBy('code')
        ->get(['code', 'name', 'dimension', 'canonical_factor', 'active'])
        ->map->getAttributes()
        ->all();

    $this->seed(StandardUnitSeeder::class);
    $secondSeed = StandardUnit::query()
        ->orderBy('code')
        ->get(['code', 'name', 'dimension', 'canonical_factor', 'active'])
        ->map->getAttributes()
        ->all();

    expect($secondSeed)
        ->toBe($firstSeed)
        ->and($secondSeed)
        ->toHaveCount(count(StandardUnits::definitions()));
});

test('standard unit codes are globally unique', function () {
    $this->seed(StandardUnitSeeder::class);

    expect(fn () => StandardUnit::query()->create([
        'code' => 'kg',
        'name' => 'Duplicate Kilogram',
        'dimension' => 'weight',
        'canonical_factor' => '1000',
        'active' => true,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('only approved dimensions can be stored', function () {
    expect(fn () => StandardUnit::query()->create([
        'code' => 'm',
        'name' => 'Meter',
        'dimension' => 'length',
        'canonical_factor' => '1',
        'active' => true,
    ]))->toThrow(QueryException::class);
});
