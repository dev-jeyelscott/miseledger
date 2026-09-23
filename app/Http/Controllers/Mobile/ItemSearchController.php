<?php

namespace App\Http\Controllers\Mobile;

use App\Actions\Inventory\SearchInventoryItems;
use App\Enums\OrganizationPermission;
use App\Http\Requests\Mobile\ScanSearchRequest;
use App\Http\Resources\Mobile\ScannedItemResource;
use App\Models\User;
use App\Support\Mobile\AvailableScanActions;
use App\Support\Mobile\BuildScannedItemData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ItemSearchController extends MobileController
{
    public function __construct(
        private readonly SearchInventoryItems $searchInventoryItems,
        private readonly BuildScannedItemData $buildScannedItemData,
    ) {}

    /**
     * Search-as-you-type fallback for the manual-entry sheet (Spec 2
     * decisions #27 and #32.A). Always returns a `matches` list, which the
     * client renders as the selection screen regardless of row count.
     */
    public function search(ScanSearchRequest $request): JsonResponse
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::InventoryView->value, $organization);

        $location = $this->activeLocation($request);
        abort_if($location === null, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $items = $this->searchInventoryItems->handle(
            $organization,
            (string) $request->validated('q'),
        );

        $data = $this->buildScannedItemData->handle(
            $organization,
            $location,
            $items,
            [],
            AvailableScanActions::forUser($user, $organization),
        );

        return response()->json([
            'matches' => ScannedItemResource::collection($data),
        ]);
    }
}
