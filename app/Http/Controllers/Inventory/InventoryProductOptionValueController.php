<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryProductOptionValue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryProductOptionValueRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InventoryProductOptionValueController extends Controller
{
    public function store(SaveInventoryProductOptionValueRequest $request, SaveInventoryProductOptionValue $saveInventoryProductOptionValue): RedirectResponse
    {
        $organization = $request->organization();
        $option = $request->inventoryProductOption();

        if ($organization === null || $option === null) {
            abort(403);
        }

        $saveInventoryProductOptionValue->handle($organization, $option, [
            'value' => (string) $request->validated('value'),
            'active' => (bool) $request->validated('active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option value created.')]);

        return back();
    }

    public function update(SaveInventoryProductOptionValueRequest $request, SaveInventoryProductOptionValue $saveInventoryProductOptionValue): RedirectResponse
    {
        $organization = $request->organization();
        $option = $request->inventoryProductOption();
        $value = $request->inventoryProductOptionValue();

        if ($organization === null || $option === null || $value === null) {
            abort(403);
        }

        $saveInventoryProductOptionValue->handle($organization, $option, [
            'value' => (string) $request->validated('value'),
            'active' => (bool) $request->validated('active'),
        ], $value);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option value updated.')]);

        return back();
    }
}
