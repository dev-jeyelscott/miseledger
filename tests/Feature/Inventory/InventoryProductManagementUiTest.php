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
    $product = InventoryProduct::factory()->for($organization)->create([
        'name' => 'Cordless drills',
    ]);
    $brand = InventoryBrand::factory()->for($organization)->create([
        'name' => 'Acme',
    ]);
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
            ->where('productFamily.variants.0.id', $item->id)
            ->where('productFamily.variants.0.description', '18V compact drill')
            ->where('productFamily.variants.0.sku', 'DRILL-18V')
            ->where('productFamily.variants.0.barcode', '1234567890123')
            ->where(
                'productFamily.variants.0.baseUnitOfMeasure.id',
                $item->base_unit_of_measure_id,
            )
            ->where('productFamily.variants.0.brand.name', 'Acme'));
});

test('option and value mutations are limited to inventory managers and their active tenant', function () {
    $manager = User::factory()->create();
    $viewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $product = InventoryProduct::factory()->for($organization)->create();
    $foreignProduct = InventoryProduct::factory()
        ->for($otherOrganization)
        ->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($manager)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($viewer)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($viewer)
        ->post(
            route('inventory.product-families.options.store', $product),
            [
                'name' => 'Size',
                'active' => true,
            ],
        )
        ->assertForbidden();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($manager)
        ->post(
            route('inventory.product-families.options.store', $foreignProduct),
            [
                'name' => 'Size',
                'active' => true,
            ],
        )
        ->assertForbidden();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($manager)
        ->post(
            route('inventory.product-families.options.store', $product),
            [
                'name' => 'Size',
                'active' => true,
            ],
        )
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $option = $product->options()->sole();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($manager)
        ->post(
            route(
                'inventory.product-families.options.values.store',
                [$product, $option],
            ),
            [
                'value' => 'Small',
                'active' => true,
            ],
        )
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($option->values()->value('value'))->toBe('Small');
});

test('the product family index uses the canonical server backed master data composition', function () {
    $source = File::get(
        resource_path('js/pages/inventory/product-families/index.tsx'),
    );
    $normalizedSource = preg_replace('/\s+/', ' ', $source);

    expect($source)
        ->toContain(
            "import { FilterToolbar } from '@/components/filter-toolbar';",
        )
        ->toContain(
            "import { PageHeader } from '@/components/page-header';",
        )
        ->toContain(
            "import { StatusBadge } from '@/components/status-badge';",
        )
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain(
            "import { NativeSelect } from '@/components/ui/native-select';",
        )
        ->toContain(
            "import { useGuardedDialog } from '@/hooks/use-guarded-dialog';",
        )
        ->toContain('DialogTrigger')
        ->toContain('DialogContent')
        ->toContain('CreateProductFamilyDialog')
        ->toContain('<PageHeader')
        ->toContain('<FilterToolbar')
        ->toContain('<StatusBadge')
        ->toContain('InventoryProductController.index().url')
        ->toContain('method="get"')
        ->toContain('name="search"')
        ->toContain('name="status"')
        ->toContain('Search product families...')
        ->toContain('All statuses')
        ->toContain('Applying…')
        ->toContain('hasQueryState')
        ->toContain('Reset')
        ->toContain('border-border')
        ->toContain('md:hidden')
        ->toContain('hidden overflow-x-auto md:block')
        ->toContain('productFamilies.map')
        ->toContain('canManage')
        ->toContain('canManage ? (')
        ->toContain('Create product family')
        ->toContain('InventoryProductController.store.form()')
        ->toContain('label="Name"')
        ->toContain('label="Status"')
        ->toContain('autoFocus')
        ->toContain('disabled={processing}')
        ->toContain("{processing ? 'Creating…' : 'Create'}")
        ->toContain('resetOnSuccess')
        ->toContain('onSuccess={dialog.closeAfterSuccess}')
        ->toContain('onChange={dialog.markDirty}')
        ->toContain('dialog.onOpenChange(false)')
        ->not->toContain('useState')
        ->not->toContain('useMemo')
        ->not->toContain(
            "import { Badge } from '@/components/ui/badge';",
        );

    expect($normalizedSource)
        ->toContain(
            "label={ productFamily.active ? 'Active' : 'Inactive' } variant={ productFamily.active ? 'success' : 'neutral' }",
        )
        ->toContain("{processing ? 'Creating…' : 'Create'}");
});

test('the product family detail is view first and uses guarded option and value dialogs', function () {
    $source = File::get(
        resource_path('js/pages/inventory/product-families/show.tsx'),
    );

    expect($source)
        ->toContain(
            "import { useGuardedDialog } from '@/hooks/use-guarded-dialog';",
        )
        ->toContain(
            'DialogTrigger',
        )
        ->toContain('CreateOptionDialog')
        ->toContain('EditOptionDialog')
        ->toContain('CreateOptionValueDialog')
        ->toContain('EditOptionValueDialog')
        ->toContain('createProductOption')
        ->toContain('editProductOption')
        ->toContain('createProductOptionValue')
        ->toContain('editProductOptionValue')
        ->toContain('resetOnSuccess')
        ->toContain('onSuccess={dialog.closeAfterSuccess}')
        ->toContain('<StatusBadge')
        ->not->toContain(
            "import { Badge } from '@/components/ui/badge';",
        )
        ->not->toContain('<Badge');
});

test('the product family detail exposes its semantic status and dynamic breadcrumb', function () {
    $source = File::get(
        resource_path('js/pages/inventory/product-families/show.tsx'),
    );

    expect($source)
        ->toContain('setLayoutProps({')
        ->toContain('title: productFamily.name')
        ->toContain(
            'href: InventoryProductController.show(productFamily.id)',
        )
        ->toContain('Family status')
        ->toContain('<ActiveStatus active={productFamily.active} />');
});

test('variant discovery is local and preserves responsive read only navigation', function () {
    $source = File::get(
        resource_path('js/pages/inventory/product-families/show.tsx'),
    );

    expect($source)
        ->toContain('useState')
        ->toContain('useMemo')
        ->toContain('variantSearch')
        ->toContain('filteredVariants')
        ->toContain('Search variants')
        ->toContain('Search variants by name, SKU, barcode, brand, or unit...')
        ->toContain('md:hidden')
        ->toContain('hidden overflow-x-auto md:block')
        ->toContain('Variant description')
        ->toContain('Brand')
        ->toContain('SKU')
        ->toContain('Barcode')
        ->toContain('Base unit')
        ->toContain('Status')
        ->toContain('scope="col"')
        ->toContain('InventoryItemController.show(')
        ->toContain('InventoryItemController.edit(')
        ->toContain('{canManage && (');
});
