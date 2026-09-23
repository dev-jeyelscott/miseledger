<?php

namespace App\Http\Controllers\Mobile;

use App\Enums\OrganizationPermission;
use App\Http\Resources\Mobile\ScannedItemResource;
use App\Models\InventoryItem;
use App\Models\User;
use App\Support\Mobile\AvailableScanActions;
use App\Support\Mobile\BuildScannedItemData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin page shell for the "Stock" tab (Spec 8): a second, read-only entry
 * point into Spec 2's existing search/scan lookup, for an operator who only
 * wants to check current stock without starting a write workflow. No new
 * lookup logic is introduced; `show` resolves one item the same way
 * `ScanController::initialItem()` and `WasteController::record()` already do.
 */
class StockController extends MobileController
{
    public function __construct(
        private readonly BuildScannedItemData $buildScannedItemData,
    ) {}

    /**
     * Render the search + scan shell. Search and scan results are fetched
     * client-side against Spec 2's existing `GET /mobile/scan/search` and
     * `POST /mobile/scan/lookup` endpoints.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        $location = $this->activeLocation($request);

        return Inertia::render('mobile/stock/index', [
            'activeLocation' => $location !== null ? [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ] : null,
            'organization' => $organization !== null ? [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ] : null,
            'navBadgeCounts' => $this->navBadgeCounts($request),
        ]);
    }

    /**
     * Render the read-only current-stock detail for one item, reached by
     * tapping a search result or a completed scan on this tab, or from the
     * item action hub's "Stock Details" action.
     */
    public function show(Request $request, InventoryItem $inventoryItem): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::InventoryView->value, $organization);

        abort_unless($inventoryItem->organization_id === $organization->id, 404);
        abort_unless($inventoryItem->active, 404);

        $location = $this->requireActiveLocation($request);
        abort_if($location === null, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $availableActions = AvailableScanActions::forUser($user, $organization);

        $data = $this->buildScannedItemData->handle(
            $organization,
            $location,
            collect([$inventoryItem]),
            [],
            $availableActions,
        )->first();

        return Inertia::render('mobile/stock/item', [
            'activeLocation' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'navBadgeCounts' => $this->navBadgeCounts($request),
            'item' => new ScannedItemResource($data),
        ]);
    }
}
