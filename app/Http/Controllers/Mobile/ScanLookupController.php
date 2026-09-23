<?php

namespace App\Http\Controllers\Mobile;

use App\Actions\Inventory\LookupBarcode;
use App\Actions\Inventory\SearchInventoryItems;
use App\Enums\OrganizationPermission;
use App\Http\Requests\Mobile\ScanLookupRequest;
use App\Http\Resources\Mobile\ScannedItemResource;
use App\Models\User;
use App\Support\Mobile\AvailableScanActions;
use App\Support\Mobile\BuildScannedItemData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ScanLookupController extends MobileController
{
    public function __construct(
        private readonly LookupBarcode $lookupBarcode,
        private readonly SearchInventoryItems $searchInventoryItems,
        private readonly BuildScannedItemData $buildScannedItemData,
    ) {}

    /**
     * Resolve one decoded/typed scan value to a single match, several
     * candidates, or a not-found payload (Spec 2 §"Backend / API").
     */
    public function lookup(ScanLookupRequest $request): JsonResponse
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::InventoryView->value, $organization);

        $location = $this->activeLocation($request);
        abort_if($location === null, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $value = (string) $request->validated('value');
        $availableActions = AvailableScanActions::forUser($user, $organization);

        $result = $this->lookupBarcode->handle($organization, $value);

        if ($result->found && $result->barcode !== null) {
            $barcode = $result->barcode;
            $item = $barcode->inventoryItem;

            $data = $this->buildScannedItemData->handle(
                $organization,
                $location,
                collect([$item]),
                [$item->id => $barcode->inventoryItemUnit],
                $availableActions,
            )->first();

            return response()->json([
                'match' => new ScannedItemResource($data),
            ]);
        }

        $items = $this->searchInventoryItems->handle($organization, $value);

        if ($items->isEmpty()) {
            Log::debug('mobile.scan.lookup.not_found', [
                'organization_id' => $organization->id,
                'value_hash' => hash('sha256', $value),
            ]);

            return response()->json([
                'notFound' => true,
                'query' => $value,
            ], 404);
        }

        $data = $this->buildScannedItemData->handle(
            $organization,
            $location,
            $items,
            [],
            $availableActions,
        );

        if ($data->count() === 1) {
            return response()->json([
                'match' => new ScannedItemResource($data->first()),
            ]);
        }

        return response()->json([
            'matches' => ScannedItemResource::collection($data),
        ]);
    }
}
