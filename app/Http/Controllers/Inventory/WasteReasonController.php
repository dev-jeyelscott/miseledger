<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreWasteReasonRequest;
use App\Http\Requests\Inventory\UpdateWasteReasonRequest;
use App\Models\WasteReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WasteReasonController extends Controller
{
    /**
     * Create an active organization-scoped waste reason.
     */
    public function store(
        StoreWasteReasonRequest $request,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        WasteReason::query()->create([
            'organization_id' => $organization->id,
            'name' => $request->validated('name'),
            'active' => true,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Waste reason created.'),
        ]);

        return $this->redirectAfterMutation($request);
    }

    /**
     * Activate or deactivate a reason without rewriting historical meaning.
     */
    public function update(
        UpdateWasteReasonRequest $request,
        string $wasteReason,
    ): RedirectResponse {
        $reason = $request->wasteReason();

        if ($reason === null) {
            abort(403);
        }

        $reason->forceFill([
            'active' => $request->boolean('active'),
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Waste reason status updated.'),
        ]);

        return $this->redirectAfterMutation($request);
    }

    /**
     * Keep modal mutations in their exact same-origin Waste context while
     * retaining the existing canonical redirect for non-modal requests.
     */
    private function redirectAfterMutation(Request $request): RedirectResponse
    {
        if (! $request->boolean('_modal')) {
            return to_route('waste.index');
        }

        $fallback = route('waste.index');

        return back(fallback: $fallback)->enforceSameOrigin($fallback);
    }
}
