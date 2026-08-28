<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryProductOption;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryProductOptionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InventoryProductOptionController extends Controller
{
    public function store(SaveInventoryProductOptionRequest $request, SaveInventoryProductOption $saveInventoryProductOption): RedirectResponse
    {
        $organization = $request->organization();
        $product = $request->inventoryProduct();

        if ($organization === null || $product === null) {
            abort(403);
        }

        $saveInventoryProductOption->handle($organization, $product, [
            'name' => (string) $request->validated('name'),
            'active' => (bool) $request->validated('active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option created.')]);

        return back();
    }

    public function update(SaveInventoryProductOptionRequest $request, SaveInventoryProductOption $saveInventoryProductOption): RedirectResponse
    {
        $organization = $request->organization();
        $product = $request->inventoryProduct();
        $option = $request->inventoryProductOption();

        if ($organization === null || $product === null || $option === null) {
            abort(403);
        }

        $saveInventoryProductOption->handle($organization, $product, [
            'name' => (string) $request->validated('name'),
            'active' => (bool) $request->validated('active'),
        ], $option);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option updated.')]);

        return back();
    }
}
