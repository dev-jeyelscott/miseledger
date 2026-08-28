<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateBarcode;
use App\Actions\Inventory\UpdateBarcode;
use App\Enums\BarcodeSymbology;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreBarcodeRequest;
use App\Http\Requests\Inventory\UpdateBarcodeRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InventoryItemBarcodeController extends Controller
{
    /**
     * Register a new barcode identity for an item or alternate unit.
     */
    public function store(
        StoreBarcodeRequest $request,
        string $inventoryItem,
        CreateBarcode $createBarcode,
    ): RedirectResponse {
        $organization = $request->organization();
        $item = $request->inventoryItem();

        if ($organization === null || $item === null) {
            abort(403);
        }

        $unitId = $request->validated('inventory_item_unit_id');

        $createBarcode->handle(
            $organization,
            $item,
            (string) $request->validated('value'),
            BarcodeSymbology::from((string) $request->validated('symbology')),
            $unitId === null ? null : (int) $unitId,
            (bool) $request->validated('is_primary'),
            (bool) $request->validated('active'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Barcode added.'),
        ]);

        return to_route('inventory.items.edit', $inventoryItem);
    }

    /**
     * Update an existing barcode's identity, association, and state.
     */
    public function update(
        UpdateBarcodeRequest $request,
        string $inventoryItem,
        string $barcode,
        UpdateBarcode $updateBarcode,
    ): RedirectResponse {
        $organization = $request->organization();
        $item = $request->inventoryItem();
        $barcodeModel = $request->barcode();

        if (
            $organization === null
            || $item === null
            || $barcodeModel === null
        ) {
            abort(403);
        }

        $unitId = $request->validated('inventory_item_unit_id');

        $updateBarcode->handle(
            $organization,
            $item,
            $barcodeModel,
            (string) $request->validated('value'),
            BarcodeSymbology::from((string) $request->validated('symbology')),
            $unitId === null ? null : (int) $unitId,
            (bool) $request->validated('is_primary'),
            (bool) $request->validated('active'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Barcode updated.'),
        ]);

        if ($request->boolean('_modal')) {
            return back();
        }

        return to_route('inventory.items.edit', $inventoryItem);
    }
}
