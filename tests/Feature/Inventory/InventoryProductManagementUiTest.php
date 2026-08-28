<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryBrand;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\File;

test('an inventory manager receives the product family page with its variants and controlled options', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $product = InventoryProduct::factory()->for($organization)->create(['name' => 'Cordless drills']);
    $brand = InventoryBrand::factory()->for($organization)->create(['name' => 'Acme']);
    $item = InventoryItem::factory()->for($organization)->create([
        'inventory_product_id' => $product->id,
        'inventory_brand_id' => $brand->id,
        'description' => '18V compact drill',
        'sku' => 'DRILL-18V',
    ]);
    InventoryItemBarcode::factory()->for($organization)->for($item)->create([
        'barcode' => '1234567890123',
        'primary' => true,
        'active' => true,
    ]);
    $option = InventoryProductOption::factory()->for($organization)->create([
        'inventory_product_id' => $product->id,
        'name' => 'Voltage',
    ]);
    $option->values()->create([
        'organization_id' => $organization->id,
        'value' => '18V',
        'active' => true,
    ]);

    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'role' => OrganizationRole::Owner,
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.product-families.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/product-families/show')
            ->where('canManage', true)
            ->where('productFamily.name', 'Cordless drills')
            ->where('productFamily.options.0.name', 'Voltage')
            ->where('productFamily.options.0.values.0.value', '18V')
            ->where('productFamily.variants.0.description', '18V compact drill')
            ->where('productFamily.variants.0.sku', 'DRILL-18V')
            ->where('productFamily.variants.0.barcode', '1234567890123')
            ->where('productFamily.variants.0.baseUnitOfMeasure.id', $item->base_unit_of_measure_id)
            ->where('productFamily.variants.0.brand.name', 'Acme'));
});

test('option and value mutations are limited to inventory managers and their active tenant', function () {
    $manager = User::factory()->create();
    $viewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $product = InventoryProduct::factory()->for($organization)->create();
    $foreignProduct = InventoryProduct::factory()->for($otherOrganization)->create();

    OrganizationMembership::factory()->for($organization)->for($manager)->create([
        'role' => OrganizationRole::Owner,
    ]);
    OrganizationMembership::factory()->for($organization)->for($viewer)->create([
        'role' => OrganizationRole::Auditor,
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($viewer)
        ->post(route('inventory.product-families.options.store', $product), [
            'name' => 'Size',
            'active' => true,
        ])
        ->assertForbidden();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($manager)
        ->post(route('inventory.product-families.options.store', $foreignProduct), [
            'name' => 'Size',
            'active' => true,
        ])
        ->assertForbidden();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($manager)
        ->post(route('inventory.product-families.options.store', $product), [
            'name' => 'Size',
            'active' => true,
        ])
        ->assertRedirect();

    $option = $product->options()->sole();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($manager)
        ->post(route('inventory.product-families.options.values.store', [$product, $option]), [
            'value' => 'Small',
            'active' => true,
        ])
        ->assertRedirect();

    expect($option->values()->value('value'))->toBe('Small');
});

test('the product family interface uses Wayfinder item actions and exposes all required variant columns', function () {
    $source = File::get(resource_path('js/pages/inventory/product-families/show.tsx'));

    expect($source)
        ->toContain("InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController'")
        ->toContain('InventoryItemController.edit(')
        ->toContain('variant.id')
        ->toContain('Variant description')
        ->toContain('SKU')
        ->toContain('Barcode')
        ->toContain('Base unit')
        ->toContain('Status')
        ->toContain('scope="col"')
        ->toContain('overflow-x-auto')
        ->toContain('canManage');
});
