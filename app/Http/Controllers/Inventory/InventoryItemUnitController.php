<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateInventoryItemUnit;
use App\Actions\Inventory\UpdateInventoryItemUnit;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryItemUnitRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemUnitRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryItemUnitController extends Controller
{
    /**
     * Add an alternate UOM to an inventory item.
     */
    public function store(
        StoreInventoryItemUnitRequest $request,
        string $inventoryItem,
        CreateInventoryItemUnit $createInventoryItemUnit,
    ): RedirectResponse {
        $organization = $request->organization();
        $item = $request->inventoryItem();

        if ($organization === null || $item === null) {
            abort(403);
        }

        $createInventoryItemUnit->handle(
            $organization,
            $item,
            (int) $request->validated('unit_of_measure_id'),
            (string) $request->validated(
                'quantity_in_base_unit',
            ),
            (bool) $request->validated('active'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Item unit conversion created.'),
        ]);

        return to_route(
            'inventory.items.edit',
            $inventoryItem,
        );
    }

    /**
     * Show a conversion-factor edit form.
     */
    public function edit(
        Request $request,
        string $inventoryItem,
        string $inventoryItemUnit,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        $item = $organization
            ->inventoryItems()
            ->with('baseUnitOfMeasure:id,name,symbol')
            ->findOrFail($inventoryItem);

        $conversion = $item
            ->unitConversions()
            ->with('unitOfMeasure:id,name,symbol,active')
            ->findOrFail($inventoryItemUnit);

        return Inertia::render(
            'inventory/items/unit-edit',
            [
                'item' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'baseUnitOfMeasure' => [
                        'id' => $item->baseUnitOfMeasure->id,
                        'name' => $item->baseUnitOfMeasure->name,
                        'symbol' => $item->baseUnitOfMeasure->symbol,
                        'active' => $item->baseUnitOfMeasure->active,
                    ],
                ],
                'conversion' => [
                    'id' => $conversion->id,
                    'quantityInBaseUnit' => (
                        $conversion->quantity_in_base_unit
                    ),
                    'active' => $conversion->active,
                    'unitOfMeasure' => [
                        'id' => $conversion->unitOfMeasure->id,
                        'name' => $conversion->unitOfMeasure->name,
                        'symbol' => $conversion
                            ->unitOfMeasure
                            ->symbol,
                        'active' => $conversion
                            ->unitOfMeasure
                            ->active,
                    ],
                ],
            ],
        );
    }

    /**
     * Update an existing item-specific conversion.
     */
    public function update(
        UpdateInventoryItemUnitRequest $request,
        string $inventoryItem,
        string $inventoryItemUnit,
        UpdateInventoryItemUnit $updateInventoryItemUnit,
    ): RedirectResponse {
        $organization = $request->organization();
        $item = $request->inventoryItem();
        $conversion = $request->inventoryItemUnit();

        if (
            $organization === null
            || $item === null
            || $conversion === null
        ) {
            abort(403);
        }

        $updateInventoryItemUnit->handle(
            $organization,
            $item,
            $conversion,
            (string) $request->validated(
                'quantity_in_base_unit',
            ),
            (bool) $request->validated('active'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Item unit conversion updated.'),
        ]);

        if ($request->boolean('_modal')) {
            return back();
        }

        return to_route(
            'inventory.items.units.edit',
            [
                'inventoryItem' => $inventoryItem,
                'inventoryItemUnit' => $inventoryItemUnit,
            ],
        );
    }

    /**
     * Return the active organization resolved by tenancy middleware.
     */
    private function activeOrganization(Request $request): Organization
    {
        $organization = $request->attributes->get(
            'activeOrganization',
        );

        if (! $organization instanceof Organization) {
            abort(403);
        }

        return $organization;
    }
}
