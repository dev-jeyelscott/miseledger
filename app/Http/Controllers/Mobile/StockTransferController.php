<?php

namespace App\Http\Controllers\Mobile;

use App\Actions\Inventory\ReceiveStockTransfer;
use App\Actions\Inventory\SaveStockTransfer;
use App\Actions\Inventory\ShipStockTransfer;
use App\Enums\OrganizationPermission;
use App\Enums\StockTransferStatus;
use App\Http\Requests\Mobile\CreateStockTransferRequest;
use App\Http\Requests\Mobile\SaveStockTransferLineRequest;
use App\Http\Resources\Mobile\ScannedItemResource;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StorageLocation;
use App\Support\Mobile\BuildScannedItemData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin mobile entry point over the desktop `StockTransfer` draft/ship/receive
 * workflow (Spec 6). `SaveStockTransfer`, `ShipStockTransfer`, and
 * `ReceiveStockTransfer` are called unmodified; mobile's "Submit" is the
 * draft save decision #6 already produces incrementally per scanned line,
 * and Ship/Receive stay their own explicit, separately permission-gated
 * actions, never merged with Submit.
 */
class StockTransferController extends MobileController
{
    public function __construct(
        private readonly BuildScannedItemData $buildScannedItemData,
    ) {}

    /**
     * Render the source (active location) / destination (any active
     * location in the organization) picker.
     */
    public function create(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::TransfersCreate->value, $organization);

        $location = $this->requireActiveLocation($request);

        if ($location === null) {
            return Inertia::render('mobile/stock-transfers/create', [
                'activeLocation' => null,
                'sourceStorageLocations' => [],
                'destinationLocations' => [],
                'prefillItemId' => null,
            ]);
        }

        $sourceStorageLocations = StorageLocation::query()
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

        $destinationLocations = Location::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Location $destination): array => [
                'id' => $destination->id,
                'name' => $destination->name,
                'storageLocations' => StorageLocation::query()
                    ->where('organization_id', $organization->id)
                    ->where('location_id', $destination->id)
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(static fn (StorageLocation $storageLocation): array => [
                        'id' => $storageLocation->id,
                        'name' => $storageLocation->name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('mobile/stock-transfers/create', [
            'activeLocation' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'sourceStorageLocations' => $sourceStorageLocations,
            'destinationLocations' => $destinationLocations,
            'prefillItemId' => $request->integer('item_id') ?: null,
        ]);
    }

    /**
     * Validate the picker's selection and hand off to the scanner. No
     * `StockTransfer` row exists yet: it is created lazily on the first
     * scanned line, matching Specs 3–4's ad-hoc/draft pattern.
     */
    public function begin(CreateStockTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        return to_route('mobile.transfers.scan', array_filter([
            'from_storage_location_id' => $validated['from_storage_location_id'],
            'to_location_id' => $validated['to_location_id'],
            'to_storage_location_id' => $validated['to_storage_location_id'],
            'item_id' => $validated['item_id'] ?? null,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * The persistent scan-to-add-line screen (Spec 2's scanner, "transfer
     * mode"), resuming an in-progress draft or starting a fresh one from
     * the validated destination selection.
     */
    public function scan(Request $request): Response|RedirectResponse
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::TransfersCreate->value, $organization);

        $location = $this->requireActiveLocation($request);
        abort_if($location === null, 404);

        $stockTransferId = $request->integer('stock_transfer_id') ?: null;

        if ($stockTransferId !== null) {
            $transfer = StockTransfer::query()
                ->with(['fromStorageLocation:id,name', 'toLocation:id,name', 'toStorageLocation:id,name'])
                ->where('organization_id', $organization->id)
                ->where('from_location_id', $location->id)
                ->where('status', StockTransferStatus::Draft->value)
                ->find($stockTransferId);

            abort_if($transfer === null, 404);

            return $this->renderScan(
                $request,
                $organization,
                $location,
                transfer: $transfer,
                fromStorageLocation: $transfer->fromStorageLocation,
                toLocation: $transfer->toLocation,
                toStorageLocation: $transfer->toStorageLocation,
            );
        }

        $fromStorageLocationId = $request->integer('from_storage_location_id') ?: null;
        $toLocationId = $request->integer('to_location_id') ?: null;
        $toStorageLocationId = $request->integer('to_storage_location_id') ?: null;

        if ($fromStorageLocationId === null || $toLocationId === null || $toStorageLocationId === null) {
            return to_route('mobile.transfers.create');
        }

        $fromStorageLocation = StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $location->id)
            ->where('active', true)
            ->find($fromStorageLocationId);

        abort_if($fromStorageLocation === null, 404);

        $toLocation = Location::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find($toLocationId);

        abort_if($toLocation === null, 404);

        $toStorageLocation = StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $toLocation->id)
            ->where('active', true)
            ->find($toStorageLocationId);

        abort_if($toStorageLocation === null || $toStorageLocation->id === $fromStorageLocation->id, 404);

        return $this->renderScan(
            $request,
            $organization,
            $location,
            transfer: null,
            fromStorageLocation: $fromStorageLocation,
            toLocation: $toLocation,
            toStorageLocation: $toStorageLocation,
        );
    }

    /**
     * Add one scanned line to the accumulating draft (decision #2: stays in
     * the live scanner afterward), replacing rather than duplicating a line
     * for the same item (mirroring Spec 4's `StockCountController::addLine`,
     * since `stock_transfer_lines`/`SaveStockTransferRequest` also require
     * one line per item per transfer).
     */
    public function addLine(
        SaveStockTransferLineRequest $request,
        SaveStockTransfer $saveStockTransfer,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        $actor = $request->user();
        $location = $request->activeLocation();

        if ($actor === null || $location === null) {
            abort(403);
        }

        $validated = $request->validated();

        $transfer = null;

        if (! empty($validated['stock_transfer_id'])) {
            $transfer = StockTransfer::query()
                ->where('organization_id', $organization->id)
                ->where('from_location_id', $location->id)
                ->where('status', StockTransferStatus::Draft->value)
                ->find((int) $validated['stock_transfer_id']);

            abort_if($transfer === null, 404);

            $fromStorageLocationId = $transfer->from_storage_location_id;
            $toLocationId = $transfer->to_location_id;
            $toStorageLocationId = $transfer->to_storage_location_id;
        } else {
            $fromStorageLocationId = (int) $validated['from_storage_location_id'];
            $toLocationId = (int) $validated['to_location_id'];
            $toStorageLocationId = (int) $validated['to_storage_location_id'];
        }

        $inventoryItemId = (int) $validated['inventory_item_id'];
        $unitId = (int) $validated['unit_id'];
        $quantity = (string) $validated['quantity'];

        $lines = $this->existingLineInput($transfer);
        $matchedIndex = null;

        foreach ($lines as $index => $line) {
            if ($line['inventory_item_id'] === $inventoryItemId) {
                $matchedIndex = $index;
                break;
            }
        }

        if ($matchedIndex !== null) {
            $lines[$matchedIndex]['requested_quantity'] = $quantity;
            $lines[$matchedIndex]['unit_id'] = $unitId;
        } else {
            $lines[] = [
                'inventory_item_id' => $inventoryItemId,
                'requested_quantity' => $quantity,
                'unit_id' => $unitId,
            ];
        }

        $savedTransfer = $saveStockTransfer->handle(
            $organization,
            $actor,
            [
                'number' => $transfer !== null ? $transfer->number : $this->uniqueTransferNumber($organization),
                'from_location_id' => $location->id,
                'from_storage_location_id' => $fromStorageLocationId,
                'to_location_id' => $toLocationId,
                'to_storage_location_id' => $toStorageLocationId,
                'notes' => $transfer?->notes,
                'lines' => $lines,
            ],
            $transfer,
        );

        return to_route('mobile.transfers.scan', ['stock_transfer_id' => $savedTransfer->id]);
    }

    /**
     * Full source/destination/item/quantity review (decision #38.A), the
     * mandatory step before Submit can be enabled.
     */
    public function review(Request $request, string $stockTransfer): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::TransfersCreate->value, $organization);

        $transfer = $this->tenantTransfer($organization, $stockTransfer);

        return Inertia::render('mobile/stock-transfers/review', [
            'stockTransfer' => $this->transferData($transfer),
        ]);
    }

    /**
     * "Submit" (decision #6): the draft is already saved line-by-line as it
     * is scanned, so this step only confirms the draft is non-empty and
     * still editable before handing the operator to the detail screen. It
     * performs no additional write, which makes repeated taps naturally
     * idempotent (decision #30.C).
     */
    public function store(Request $request, string $stockTransfer): RedirectResponse
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::TransfersCreate->value, $organization);

        $transfer = $this->tenantTransfer($organization, $stockTransfer);

        if ($transfer->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => __('At least one stock-transfer line is required.'),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock transfer :number submitted.', ['number' => $transfer->number]),
        ]);

        return to_route('mobile.transfers.show', $transfer);
    }

    /**
     * Transfer detail screen: status, lines, and Ship/Receive when the
     * actor holds the corresponding permission (decision #6).
     */
    public function show(Request $request, string $stockTransfer): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::TransfersCreate->value, $organization);

        $transfer = $this->tenantTransfer($organization, $stockTransfer);

        return Inertia::render('mobile/stock-transfers/show', [
            'stockTransfer' => $this->transferData($transfer),
            'canShip' => Gate::allows(OrganizationPermission::TransfersShip->value, $organization),
            'canReceive' => Gate::allows(OrganizationPermission::TransfersReceive->value, $organization),
        ]);
    }

    /**
     * Ship via the unmodified desktop action, gated on its own permission
     * (decision #6, Security §"Ship/Receive remain separately permissioned").
     */
    public function ship(
        Request $request,
        string $stockTransfer,
        ShipStockTransfer $shipStockTransfer,
    ): RedirectResponse {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        $actor = $request->user();
        abort_if($actor === null, 403);

        Gate::authorize(OrganizationPermission::TransfersShip->value, $organization);

        $transfer = StockTransfer::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($stockTransfer);

        $shipStockTransfer->handle($organization, $actor, $transfer);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock transfer shipped.'),
        ]);

        return to_route('mobile.transfers.show', $transfer);
    }

    /**
     * Receive via the unmodified desktop action, gated on its own
     * permission. Mobile receives every line exactly as shipped in one tap;
     * an operator who needs to record a receiving variance corrects it on
     * desktop (Out of Scope: variance reporting stays desktop-only).
     */
    public function receive(
        Request $request,
        string $stockTransfer,
        ReceiveStockTransfer $receiveStockTransfer,
    ): RedirectResponse {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        $actor = $request->user();
        abort_if($actor === null, 403);

        Gate::authorize(OrganizationPermission::TransfersReceive->value, $organization);

        $transfer = StockTransfer::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($stockTransfer);

        $lines = StockTransferLine::query()
            ->where('stock_transfer_id', $transfer->id)
            ->get(['id', 'shipped_base_quantity']);

        $receiveStockTransfer->handle($organization, $actor, $transfer, [
            'lines' => $lines->map(static fn (StockTransferLine $line): array => [
                'id' => $line->id,
                'received_base_quantity' => (string) ($line->shipped_base_quantity ?? '0'),
            ])->values()->all(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock transfer received.'),
        ]);

        return to_route('mobile.transfers.show', $transfer);
    }

    /**
     * Resolve a tenant-owned transfer with the relations the review/show/
     * store screens need.
     */
    private function tenantTransfer(Organization $organization, string $id): StockTransfer
    {
        return StockTransfer::query()
            ->with([
                'fromLocation:id,name',
                'fromStorageLocation:id,name',
                'toLocation:id,name',
                'toStorageLocation:id,name',
                'lines.inventoryItem:id,name,sku',
                'lines.unit:id,name,symbol',
            ])
            ->where('organization_id', $organization->id)
            ->findOrFail($id);
    }

    /**
     * Shape one transfer plus its lines for the review/show screens.
     *
     * @return array<string, mixed>
     */
    private function transferData(StockTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'number' => $transfer->number,
            'status' => $transfer->status->value,
            'fromLocationName' => $transfer->fromLocation->name,
            'fromStorageLocationName' => $transfer->fromStorageLocation->name,
            'toLocationName' => $transfer->toLocation->name,
            'toStorageLocationName' => $transfer->toStorageLocation->name,
            'lines' => $transfer->lines->map(static fn (StockTransferLine $line): array => [
                'id' => $line->id,
                'itemName' => $line->inventoryItem->name,
                'quantity' => (string) $line->requested_quantity,
                'unitSymbol' => $line->unit->symbol,
            ])->values()->all(),
        ];
    }

    /**
     * Render the scan screen with the resolved session context, including
     * an optional pre-filled item (reached from the item action hub via
     * `?item_id=`, Spec 2 §"Behavior and Flow" step 6).
     */
    private function renderScan(
        Request $request,
        Organization $organization,
        Location $location,
        ?StockTransfer $transfer,
        StorageLocation $fromStorageLocation,
        Location $toLocation,
        StorageLocation $toStorageLocation,
    ): Response {
        $draftLines = $transfer === null
            ? []
            : $transfer->lines()
                ->with(['inventoryItem:id,name,sku', 'unit:id,name,symbol'])
                ->orderBy('id')
                ->get()
                ->map(static fn (StockTransferLine $line): array => [
                    'inventoryItemId' => $line->inventory_item_id,
                    'itemName' => $line->inventoryItem->name,
                    'unitId' => $line->unit_id,
                    'unitSymbol' => $line->unit->symbol,
                    'quantity' => (string) $line->requested_quantity,
                ])
                ->values()
                ->all();

        $prefillItemId = $request->integer('item_id') ?: null;
        $prefillItem = null;

        if ($prefillItemId !== null) {
            $item = InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->where('active', true)
                ->find($prefillItemId);

            if ($item !== null) {
                $prefillItem = $this->buildScannedItemData->handle(
                    $organization,
                    $location,
                    collect([$item]),
                    [],
                    [],
                )->first();
            }
        }

        return Inertia::render('mobile/stock-transfers/scan', [
            'activeLocation' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'context' => [
                'stockTransferId' => $transfer?->id,
                'stockTransferNumber' => $transfer?->number,
                'fromStorageLocationId' => $fromStorageLocation->id,
                'fromStorageLocationName' => $fromStorageLocation->name,
                'toLocationId' => $toLocation->id,
                'toLocationName' => $toLocation->name,
                'toStorageLocationId' => $toStorageLocation->id,
                'toStorageLocationName' => $toStorageLocation->name,
                'draftLines' => $draftLines,
            ],
            'prefillItem' => $prefillItem !== null
                ? (new ScannedItemResource($prefillItem))->resolve($request)
                : null,
        ]);
    }

    /**
     * Generate an organization-unique mobile transfer number.
     */
    private function uniqueTransferNumber(Organization $organization): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = sprintf(
                'ST-MOB-%s-%s',
                now()->format('ymdHis'),
                Str::upper(Str::random(4)),
            );

            $exists = StockTransfer::query()
                ->where('organization_id', $organization->id)
                ->where('number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'number' => __('Unable to generate a unique transfer number. Try again.'),
        ]);
    }

    /**
     * Rebuild the accumulated `SaveStockTransfer` line input from a draft's
     * currently persisted lines.
     *
     * @return array<int, array{inventory_item_id: int, requested_quantity: string, unit_id: int}>
     */
    private function existingLineInput(?StockTransfer $transfer): array
    {
        if ($transfer === null) {
            return [];
        }

        return $transfer->lines()
            ->orderBy('id')
            ->get()
            ->map(static fn (StockTransferLine $line): array => [
                'inventory_item_id' => $line->inventory_item_id,
                'requested_quantity' => (string) $line->requested_quantity,
                'unit_id' => $line->unit_id,
            ])
            ->values()
            ->all();
    }
}
