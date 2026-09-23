<?php

namespace App\Http\Controllers\Mobile;

use App\Actions\Inventory\SaveStockCount;
use App\Actions\Inventory\SubmitStockCount;
use App\Enums\OrganizationPermission;
use App\Enums\StockCountStatus;
use App\Http\Requests\Inventory\StockCountTransitionRequest;
use App\Http\Requests\Mobile\SaveStockCountLineRequest;
use App\Http\Requests\Mobile\SelectStockCountStorageLocationRequest;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StorageLocation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin mobile entry point over the desktop `StockCount` draft/submit
 * workflow (Spec 4). Every mutation reuses `SaveStockCount` and
 * `SubmitStockCount` unmodified; finalize stays a desktop-only,
 * `CountsFinalize`-gated action (see docs/mobile-pwa.md).
 */
class StockCountController extends MobileController
{
    /**
     * List open (draft/submitted) counts the operator can resume or review.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::CountsCreate->value, $organization);

        $location = $this->requireActiveLocation($request);

        if ($location === null) {
            return Inertia::render('mobile/stock-counts/index', [
                'activeLocation' => null,
                'counts' => [],
            ]);
        }

        $counts = StockCount::query()
            ->with('storageLocation:id,name')
            ->where('organization_id', $organization->id)
            ->where('location_id', $location->id)
            ->whereIn('status', [
                StockCountStatus::Draft->value,
                StockCountStatus::Submitted->value,
            ])
            ->orderByDesc('id')
            ->get()
            ->map(static fn (StockCount $count): array => [
                'id' => $count->id,
                'number' => $count->number,
                'status' => $count->status->value,
                'storageLocationName' => $count->storageLocation->name,
                'lineCount' => $count->lines()->count(),
            ])
            ->values()
            ->all();

        return Inertia::render('mobile/stock-counts/index', [
            'activeLocation' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'counts' => $counts,
        ]);
    }

    /**
     * Render the storage-location picker for the active location.
     */
    public function create(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::CountsCreate->value, $organization);

        $location = $this->requireActiveLocation($request);

        if ($location === null) {
            return Inertia::render('mobile/stock-counts/create', [
                'activeLocation' => null,
                'storageLocations' => [],
            ]);
        }

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

        return Inertia::render('mobile/stock-counts/create', [
            'activeLocation' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'storageLocations' => $storageLocations,
        ]);
    }

    /**
     * Resume the actor's existing open draft for the chosen storage location,
     * or enter the scanner to start a new one. `SaveStockCount` requires at
     * least one line (verified in source), so an empty draft cannot be
     * created here; the count itself is created lazily on the first scanned
     * line, matching Spec 3's ad-hoc-receiving pattern.
     */
    public function store(
        SelectStockCountStorageLocationRequest $request,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $location = $request->activeLocation();

        if ($organization === null || ! $actor instanceof User || $location === null) {
            abort(403);
        }

        $storageLocationId = (int) $request->validated('storage_location_id');

        $existingDraft = StockCount::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $location->id)
            ->where('storage_location_id', $storageLocationId)
            ->where('created_by', $actor->id)
            ->where('status', StockCountStatus::Draft->value)
            ->latest('id')
            ->first();

        if ($existingDraft !== null) {
            return to_route('mobile.stock-counts.scan', ['stock_count_id' => $existingDraft->id]);
        }

        return to_route('mobile.stock-counts.scan', ['storage_location_id' => $storageLocationId]);
    }

    /**
     * The persistent scan-driven physical-count entry screen.
     */
    public function scan(Request $request): Response|RedirectResponse
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::CountsCreate->value, $organization);

        $location = $this->requireActiveLocation($request);
        abort_if($location === null, 404);

        $stockCountId = $request->integer('stock_count_id') ?: null;
        $storageLocationId = $request->integer('storage_location_id') ?: null;

        if ($stockCountId !== null) {
            $count = StockCount::query()
                ->with('storageLocation:id,name')
                ->where('organization_id', $organization->id)
                ->where('status', StockCountStatus::Draft->value)
                ->find($stockCountId);

            abort_if($count === null, 404);

            return $this->renderScan($organization, $location, $count);
        }

        if ($storageLocationId !== null) {
            $storageLocation = StorageLocation::query()
                ->where('organization_id', $organization->id)
                ->where('location_id', $location->id)
                ->where('active', true)
                ->find($storageLocationId);

            abort_if($storageLocation === null, 404);

            return $this->renderScan($organization, $location, null, $storageLocation);
        }

        return to_route('mobile.stock-counts.create');
    }

    /**
     * Add one scanned line to the accumulating draft (decision #2: stays in
     * the live scanner afterward), replacing rather than duplicating a line
     * for the same item (decision #35.A). The match key is `inventory_item_id`
     * alone, not item+unit: `stock_count_lines` has a
     * unique(stock_count_id, inventory_item_id) constraint, so a re-scan in a
     * different unit still replaces the item's one line (see the note above
     * `existingLineInput()`).
     */
    public function addLine(
        SaveStockCountLineRequest $request,
        SaveStockCount $saveStockCount,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();

        if ($organization === null || ! $actor instanceof User) {
            abort(403);
        }

        $location = $this->requireActiveLocation($request);
        abort_if($location === null, 404);

        $validated = $request->validated();

        $stockCount = null;
        $storageLocationId = null;

        if (! empty($validated['stock_count_id'])) {
            $stockCount = StockCount::query()
                ->where('organization_id', $organization->id)
                ->where('status', StockCountStatus::Draft->value)
                ->find((int) $validated['stock_count_id']);

            abort_if($stockCount === null, 404);

            $storageLocationId = $stockCount->storage_location_id;
        } else {
            $storageLocationId = (int) $validated['storage_location_id'];
        }

        $inventoryItemId = (int) $validated['inventory_item_id'];
        $unitId = (int) $validated['unit_id'];
        $quantity = (string) $validated['quantity'];

        $lines = $this->existingLineInput($stockCount);

        // `stock_count_lines` carries a unique(stock_count_id, inventory_item_id)
        // constraint (one line per item per count, not per item+unit), and
        // `SaveStockCount` enforces the same at the application boundary
        // (`distinct` on `lines.*.inventory_item_id`). A re-scan in a
        // different unit therefore still replaces the item's single line —
        // both its quantity and its counted unit — rather than adding a
        // second line, which the schema cannot hold.
        $matchedIndex = null;
        $previousQuantity = null;

        foreach ($lines as $index => $line) {
            if ($line['inventory_item_id'] === $inventoryItemId) {
                $matchedIndex = $index;
                $previousQuantity = $line['counted_quantity'];
                break;
            }
        }

        if ($matchedIndex !== null) {
            $lines[$matchedIndex]['counted_quantity'] = $quantity;
            $lines[$matchedIndex]['count_unit_id'] = $unitId;
        } else {
            $lines[] = [
                'inventory_item_id' => $inventoryItemId,
                'counted_quantity' => $quantity,
                'count_unit_id' => $unitId,
                'notes' => null,
            ];
        }

        $count = $saveStockCount->handle(
            $organization,
            $actor,
            [
                'number' => $stockCount !== null
                    ? $stockCount->number
                    : $this->uniqueCountNumber($organization),
                'location_id' => $location->id,
                'storage_location_id' => $storageLocationId,
                'lines' => $lines,
            ],
            $stockCount,
        );

        if ($matchedIndex !== null && $previousQuantity !== null) {
            $item = InventoryItem::query()->find($inventoryItemId);

            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __(
                    ':item was already counted at :previous — updated to :current.',
                    [
                        'item' => $item !== null ? $item->name : __('This item'),
                        'previous' => $previousQuantity,
                        'current' => $quantity,
                    ],
                ),
            ]);
        }

        return to_route('mobile.stock-counts.scan', ['stock_count_id' => $count->id]);
    }

    /**
     * Full line list with live expected-vs-physical variance, sorted by
     * absolute variance descending so the largest discrepancies are seen
     * first (decision from Spec 4 §"Behavior and Flow"). The persisted
     * `expected_base_quantity` column stays `0.000000` until finalize (see
     * `App\Actions\Inventory\FinalizeStockCount`), so this reads the live
     * `StockBalance` projection directly — a read-only query, never a write
     * to the projection, consistent with the stock-ledger integrity rule.
     */
    public function review(Request $request, string $stockCount): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::CountsCreate->value, $organization);

        $count = StockCount::query()
            ->with([
                'storageLocation:id,name',
                'lines.inventoryItem:id,name,sku,base_unit_of_measure_id',
                'lines.inventoryItem.baseUnitOfMeasure:id,symbol',
                'lines.countUnit:id,name,symbol',
            ])
            ->where('organization_id', $organization->id)
            ->findOrFail($stockCount);

        $inventoryItemIds = $count->lines
            ->pluck('inventory_item_id')
            ->unique()
            ->values();

        $balances = StockBalance::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $count->location_id)
            ->where('storage_location_id', $count->storage_location_id)
            ->whereIn('inventory_item_id', $inventoryItemIds)
            ->get()
            ->keyBy('inventory_item_id');

        $rows = $count->lines
            ->map(function (StockCountLine $line) use ($balances): array {
                $balance = $balances->get($line->inventory_item_id);

                $expectedBase = BigDecimal::of(
                    $balance !== null ? $balance->quantity_on_hand : '0.000000',
                )->toScale(6, RoundingMode::HalfUp);

                $countedBase = BigDecimal::of($line->counted_base_quantity)
                    ->toScale(6, RoundingMode::HalfUp);

                $varianceBase = $countedBase->minus($expectedBase)
                    ->toScale(6, RoundingMode::HalfUp);

                return [
                    'id' => $line->id,
                    'itemName' => $line->inventoryItem->name,
                    'itemSku' => $line->inventoryItem->sku,
                    'expectedBaseQuantity' => (string) $expectedBase,
                    'physicalQuantity' => (string) $line->counted_quantity,
                    'countUnitSymbol' => $line->countUnit->symbol,
                    'baseUnitSymbol' => $line->inventoryItem->baseUnitOfMeasure->symbol,
                    'varianceBaseQuantity' => (string) $varianceBase,
                    'absVarianceBaseQuantity' => (string) $varianceBase->abs(),
                ];
            })
            ->sortByDesc(
                static fn (array $row): float => (float) $row['absVarianceBaseQuantity'],
            )
            ->values()
            ->all();

        return Inertia::render('mobile/stock-counts/review', [
            'stockCount' => [
                'id' => $count->id,
                'number' => $count->number,
                'status' => $count->status->value,
                'storageLocationName' => $count->storageLocation->name,
                'lines' => $rows,
            ],
        ]);
    }

    /**
     * Submit physical evidence via the unmodified desktop action (mobile
     * stops here; finalize remains a `CountsFinalize`-gated desktop action).
     */
    public function submit(
        StockCountTransitionRequest $request,
        string $stockCount,
        SubmitStockCount $submitStockCount,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $count = $request->stockCount();

        if ($organization === null || ! $actor instanceof User || $count === null) {
            abort(403);
        }

        $submitStockCount->handle($organization, $actor, $count);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock count submitted.'),
        ]);

        return to_route('mobile.scan.index');
    }

    /**
     * Render the scan screen with the resolved session context, including
     * the accumulated draft lines (resume-safety, matching Spec 3's pattern).
     */
    private function renderScan(
        Organization $organization,
        Location $location,
        ?StockCount $count,
        ?StorageLocation $storageLocation = null,
    ): Response {
        $storageLocation ??= $count?->storageLocation;

        $draftLines = $count === null
            ? []
            : $count->lines()
                ->with(['inventoryItem:id,name,sku', 'countUnit:id,name,symbol'])
                ->orderBy('id')
                ->get()
                ->map(static fn (StockCountLine $line): array => [
                    'inventoryItemId' => $line->inventory_item_id,
                    'itemName' => $line->inventoryItem->name,
                    'unitId' => $line->count_unit_id,
                    'unitSymbol' => $line->countUnit->symbol,
                    'physicalQuantity' => (string) $line->counted_quantity,
                ])
                ->values()
                ->all();

        return Inertia::render('mobile/stock-counts/scan', [
            'activeLocation' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'context' => [
                'stockCountId' => $count?->id,
                'stockCountNumber' => $count?->number,
                'storageLocationId' => $storageLocation?->id,
                'storageLocationName' => $storageLocation?->name,
                'draftLines' => $draftLines,
            ],
        ]);
    }

    /**
     * Rebuild the accumulated `SaveStockCount` line input from a draft's
     * currently persisted lines.
     *
     * @return array<int, array{inventory_item_id: int, counted_quantity: string, count_unit_id: int, notes: string|null}>
     */
    private function existingLineInput(?StockCount $stockCount): array
    {
        if ($stockCount === null) {
            return [];
        }

        return $stockCount->lines()
            ->orderBy('id')
            ->get()
            ->map(static fn (StockCountLine $line): array => [
                'inventory_item_id' => $line->inventory_item_id,
                'counted_quantity' => (string) $line->counted_quantity,
                'count_unit_id' => $line->count_unit_id,
                'notes' => $line->notes,
            ])
            ->values()
            ->all();
    }

    /**
     * Generate an organization-unique mobile count number.
     */
    private function uniqueCountNumber(Organization $organization): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = sprintf(
                'SC-MOB-%s-%s',
                now()->format('ymdHis'),
                Str::upper(Str::random(4)),
            );

            $exists = StockCount::query()
                ->where('organization_id', $organization->id)
                ->where('number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'number' => __('Unable to generate a unique count number. Try again.'),
        ]);
    }
}
