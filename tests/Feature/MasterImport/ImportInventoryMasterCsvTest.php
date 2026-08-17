<?php

use App\Actions\MasterImport\ImportInventoryCategories;
use App\Actions\MasterImport\ImportInventoryItems;
use App\Actions\MasterImport\ImportInventoryItemUnitConversions;
use App\Actions\MasterImport\ImportInventoryMasterBatch;
use App\Actions\MasterImport\ImportSuppliers;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;

test('a full batch imports categories, items, units, conversions, suppliers, and supplier items', function () {
    $organization = Organization::factory()->create();

    UnitOfMeasure::factory()->for($organization)->create([
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    UnitOfMeasure::factory()->for($organization)->create([
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $batch = app(ImportInventoryMasterBatch::class);

    $results = $batch->handle($organization, [
        'categories' => "name,active\nDry Goods,true\n",
        'items' => "sku,name,base_unit_symbol,category_name,type,yield_percentage,active\nFLOUR-01,All Purpose Flour,g,Dry Goods,ingredient,100,true\n",
        'conversions' => "item_sku,unit_symbol,quantity_in_base_unit,active\nFLOUR-01,kg,1000,true\n",
        'suppliers' => "code,name,active\nACME,Acme Foods,true\n",
        'supplier_items' => "supplier_code,item_sku,supplier_sku,purchase_unit_symbol,base_quantity,current_price,active\nACME,FLOUR-01,ACME-FLR,kg,25,45.50,true\n",
    ]);

    expect($results['categories']->created)->toBe(1)
        ->and($results['items']->created)->toBe(1)
        ->and($results['conversions']->created)->toBe(1)
        ->and($results['suppliers']->created)->toBe(1)
        ->and($results['supplier_items']->created)->toBe(1);

    $category = InventoryCategory::query()->sole();
    $item = InventoryItem::query()->sole();
    $supplier = Supplier::query()->sole();
    $supplierItem = SupplierItem::query()->sole();
    $conversion = InventoryItemUnit::query()->sole();

    expect($category->name)->toBe('Dry Goods')
        ->and($item->sku)->toBe('FLOUR-01')
        ->and($item->inventory_category_id)->toBe($category->id)
        ->and($conversion->inventory_item_id)->toBe($item->id)
        ->and($conversion->quantity_in_base_unit)->toBe('1000.000000')
        ->and($supplier->code)->toBe('ACME')
        ->and($supplierItem->supplier_id)->toBe($supplier->id)
        ->and($supplierItem->inventory_item_id)->toBe($item->id)
        ->and($supplierItem->current_price)->toBe('45.5000');
});

test('re-importing categories by name updates the existing record instead of duplicating it', function () {
    $organization = Organization::factory()->create();

    $existing = InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Dry Goods',
            'active' => true,
        ]);

    $result = app(ImportInventoryCategories::class)->handle(
        $organization,
        "name,active\nDry Goods,false\n",
    );

    expect($result->created)->toBe(0)
        ->and($result->updated)->toBe(1)
        ->and(InventoryCategory::query()->count())->toBe(1)
        ->and($existing->refresh()->active)->toBeFalse();
});

test('invalid rows are reported without blocking valid rows in the same file', function () {
    $organization = Organization::factory()->create();

    $result = app(ImportInventoryCategories::class)->handle(
        $organization,
        "name,active\n,true\nBeverages,true\n",
    );

    expect($result->created)->toBe(1)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->row)->toBe(2)
        ->and(InventoryCategory::query()->where('name', 'Beverages')->exists())->toBeTrue();
});

test('a conversion row without an explicit quantity is rejected instead of guessed', function () {
    $organization = Organization::factory()->create();

    $unit = UnitOfMeasure::factory()->for($organization)->create([
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'sku' => 'FLOUR-01',
            'base_unit_of_measure_id' => $unit->id,
        ]);

    $altUnit = UnitOfMeasure::factory()->for($organization)->create([
        'symbol' => 'kg',
        'dimension' => 'weight',
    ]);

    $result = app(ImportInventoryItemUnitConversions::class)->handle(
        $organization,
        "item_sku,unit_symbol,quantity_in_base_unit,active\nFLOUR-01,kg,,true\n",
    );

    expect($result->created)->toBe(0)
        ->and($result->errors)->toHaveCount(1)
        ->and(InventoryItemUnit::query()->count())->toBe(0);
});

test('unit references never create a unit of measure and report a row error when missing', function () {
    $organization = Organization::factory()->create();

    $result = app(ImportInventoryItems::class)->handle(
        $organization,
        "sku,name,base_unit_symbol,type,active\nFLOUR-01,Flour,kg,ingredient,true\n",
    );

    expect($result->created)->toBe(0)
        ->and($result->errors)->toHaveCount(1)
        ->and(UnitOfMeasure::query()->count())->toBe(0)
        ->and(InventoryItem::query()->count())->toBe(0);
});

test('imports never resolve records belonging to another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    UnitOfMeasure::factory()->for($otherOrganization)->create([
        'symbol' => 'kg',
    ]);

    Supplier::factory()->for($otherOrganization)->create([
        'code' => 'ACME',
    ]);

    $unitResult = app(ImportInventoryItems::class)->handle(
        $organization,
        "sku,name,base_unit_symbol,type,active\nFLOUR-01,Flour,kg,ingredient,true\n",
    );

    $supplierResult = app(ImportSuppliers::class)->handle(
        $organization,
        "code,name,active\nACME,Acme Foods,true\n",
    );

    expect($unitResult->errors)->toHaveCount(1)
        ->and($supplierResult->created)->toBe(1)
        ->and(Supplier::query()->where('organization_id', $organization->id)->count())->toBe(1);
});
