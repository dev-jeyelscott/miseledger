<?php

namespace App\Http\Controllers\Mobile;

use App\Actions\Inventory\RecordWaste;
use App\Enums\OrganizationPermission;
use App\Http\Requests\Mobile\RecordWasteRequest;
use App\Http\Resources\Mobile\ScannedItemResource;
use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\WasteReason;
use App\Support\Mobile\AvailableScanActions;
use App\Support\Mobile\BuildScannedItemData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin mobile entry point over the desktop `RecordWaste` action (Spec 5).
 * The `store` mutation calls `RecordWaste::handle()` unmodified; the
 * controller adds no parallel validation or ledger logic of its own.
 */
class WasteController extends MobileController
{
    public function __construct(
        private readonly BuildScannedItemData $buildScannedItemData,
    ) {}

    /**
     * Render the single connected scan → qty → reason → save screen for one
     * already-resolved item (decision #1), reached from the item action hub
     * or an item detail page via `?item_id=`.
     */
    public function record(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::WasteRecord->value, $organization);

        $location = $this->requireActiveLocation($request);
        abort_if($location === null, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $item = InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->findOrFail((int) $request->query('item_id'));

        $availableActions = AvailableScanActions::forUser($user, $organization);

        $scannedItem = $this->buildScannedItemData->handle(
            $organization,
            $location,
            collect([$item]),
            [],
            $availableActions,
        )->first();

        $storageLocations = StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $location->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (StorageLocation $storageLocation): array => [
                'id' => $storageLocation->id,
                'name' => $storageLocation->name,
            ])
            ->values()
            ->all();

        $wasteReasons = WasteReason::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (WasteReason $reason): array => [
                'id' => $reason->id,
                'name' => $reason->name,
            ])
            ->values()
            ->all();

        return Inertia::render('mobile/waste/record', [
            'activeLocation' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'item' => (new ScannedItemResource($scannedItem))->resolve($request),
            'storageLocationOptions' => $storageLocations,
            'wasteReasonOptions' => $wasteReasons,
            'operationId' => (string) Str::uuid(),
        ]);
    }

    /**
     * Record one audited waste evidence entry through the shared stock
     * ledger boundary (decision #5, reusing `RecordWaste`'s existing
     * server idempotency and retry-verification exactly).
     */
    public function store(
        RecordWasteRequest $request,
        RecordWaste $recordWaste,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();

        if ($organization === null || ! $actor instanceof User) {
            abort(403);
        }

        $item = InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->findOrFail((int) $request->validated('inventory_item_id'));

        $recordWaste->handle($organization, $actor, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':item recorded as waste.', ['item' => $item->name]),
        ]);

        return to_route('mobile.scan.index');
    }
}
