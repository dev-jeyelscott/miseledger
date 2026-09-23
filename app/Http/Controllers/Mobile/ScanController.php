<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Resources\Mobile\ScannedItemResource;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use App\Support\Mobile\AvailableScanActions;
use App\Support\Mobile\BuildScannedItemData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends MobileController
{
    public function __construct(
        private readonly BuildScannedItemData $buildScannedItemData,
    ) {}

    /**
     * Render the persistent scanner shell; lookups happen via JSON XHR so
     * the camera stream survives repeated scans.
     *
     * An optional `item_id` pre-selects the item action hub for a caller
     * that already knows which item it wants (Spec 7's Restock task, which
     * opens this screen pre-selected to the flagged item).
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        $location = $this->requireActiveLocation($request);

        return Inertia::render('mobile/scan/index', [
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
            'initialItem' => $this->initialItem($request, $organization, $location),
        ]);
    }

    /**
     * Resolve the `item_id` query param to a `ScannedItem`, scoped to the
     * active organization and location, or null when absent/not found.
     *
     * @return array<string, mixed>|null
     */
    private function initialItem(Request $request, ?Organization $organization, ?Location $location): ?array
    {
        $itemId = $request->integer('item_id') ?: null;

        if ($itemId === null || $organization === null || $location === null) {
            return null;
        }

        $actor = $request->user();

        if (! $actor instanceof User) {
            return null;
        }

        $item = InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find($itemId);

        if ($item === null) {
            return null;
        }

        $availableActions = AvailableScanActions::forUser($actor, $organization);

        $data = $this->buildScannedItemData->handle(
            $organization,
            $location,
            collect([$item]),
            [],
            $availableActions,
        )->first();

        if ($data === null) {
            return null;
        }

        return (new ScannedItemResource($data))->resolve($request);
    }
}
