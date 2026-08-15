<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CancelStockTransfer;
use App\Actions\Inventory\ConvertQuantity;
use App\Actions\Inventory\ReceiveStockTransfer;
use App\Actions\Inventory\SaveStockTransfer;
use App\Actions\Inventory\ShipStockTransfer;
use App\Enums\OrganizationPermission;
use App\Enums\StockTransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReceiveStockTransferRequest;
use App\Http\Requests\Inventory\SaveStockTransferRequest;
use App\Http\Requests\Inventory\StockTransferTransitionRequest;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    /**
     * List tenant-owned stock-transfer history.
     */
    public function index(Request $request): Response
    {
        $organization =
            $this->activeOrganization($request);

        $this->authorizeTransferRead(
            $organization,
        );

        $transfers = StockTransfer::query()
            ->with([
                'fromLocation:id,name',
                'fromStorageLocation:id,name',
                'toLocation:id,name',
                'toStorageLocation:id,name',
            ])
            ->where(
                'organization_id',
                $organization->id,
            )
            ->orderByDesc('id')
            ->get()
            ->map(
                static fn (
                    StockTransfer $transfer,
                ): array => [
                    'id' => $transfer->id,
                    'number' => $transfer->number,
                    'status' =>
                        $transfer->status->value,
                    'fromLocationName' =>
                        $transfer->fromLocation->name,
                    'fromStorageLocationName' =>
                        $transfer
                            ->fromStorageLocation
                            ->name,
                    'toLocationName' =>
                        $transfer->toLocation->name,
                    'toStorageLocationName' =>
                        $transfer
                            ->toStorageLocation
                            ->name,
                    'requestedAt' =>
                        $transfer->requested_at
                            ?->toIso8601String(),
                    'shippedAt' =>
                        $transfer->shipped_at
                            ?->toIso8601String(),
                    'receivedAt' =>
                        $transfer->received_at
                            ?->toIso8601String(),
                ],
            )
            ->values()
            ->all();

        return Inertia::render(
            'stock-transfers/index',
            [
                'transfers' => $transfers,
                'canCreate' => Gate::allows(
                    OrganizationPermission::TransfersCreate
                        ->value,
                    $organization,
                ),
                'canViewReport' => Gate::allows(
                    OrganizationPermission::ReportsView
                        ->value,
                    $organization,
                ),
            ],
        );
    }

    /**
     * Show an empty transfer draft.
     */
    public function create(
        Request $request,
        ConvertQuantity $convertQuantity,
    ): Response {
        $organization =
            $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::TransfersCreate->value,
            $organization,
        );

        return Inertia::render(
            'stock-transfers/form',
            [
                'stockTransfer' => null,
                ...$this->formOptions(
                    $organization,
                    $convertQuantity,
                ),
                ...$this->permissionData(
                    $organization,
                ),
            ],
        );
    }

    /**
     * Persist a new inventory-neutral transfer draft.
     */
    public function store(
        SaveStockTransferRequest $request,
        SaveStockTransfer $saveStockTransfer,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();

        if (
            $organization === null
            || ! $actor instanceof User
        ) {
            abort(403);
        }

        $transfer = $saveStockTransfer->handle(
            $organization,
            $actor,
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(
                'Stock transfer draft created.',
            ),
        ]);

        return to_route(
            'stock-transfers.edit',
            $transfer,
        );
    }

    /**
     * Show an editable draft or immutable transfer history.
     */
    public function edit(
        Request $request,
        string $stockTransfer,
        ConvertQuantity $convertQuantity,
    ): Response {
        $organization =
            $this->activeOrganization($request);

        $this->authorizeTransferRead(
            $organization,
        );

        $transfer = StockTransfer::query()
            ->with([
                'fromLocation:id,name',
                'fromStorageLocation:id,name',
                'toLocation:id,name',
                'toStorageLocation:id,name',
                'creator:id,name',
                'shipper:id,name',
                'receiver:id,name',
                'lines.inventoryItem.baseUnitOfMeasure',
                'lines.unit:id,name,symbol',
                'lines.outboundMovement',
                'lines.inboundMovement',
            ])
            ->where(
                'organization_id',
                $organization->id,
            )
            ->findOrFail($stockTransfer);

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        return Inertia::render(
            'stock-transfers/form',
            [
                'stockTransfer' =>
                    $this->stockTransferData(
                        $transfer,
                        $canViewCosts,
                    ),
                ...$this->formOptions(
                    $organization,
                    $convertQuantity,
                ),
                ...$this->permissionData(
                    $organization,
                ),
                'canViewCosts' => $canViewCosts,
            ],
        );
    }

    /**
     * Replace an existing transfer draft.
     */
    public function update(
        SaveStockTransferRequest $request,
        string $stockTransfer,
        SaveStockTransfer $saveStockTransfer,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $transfer = $request->stockTransfer();

        if (
            $organization === null
            || ! $actor instanceof User
            || $transfer === null
        ) {
            abort(403);
        }

        $saveStockTransfer->handle(
            $organization,
            $actor,
            $request->validated(),
            $transfer,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(
                'Stock transfer draft updated.',
            ),
        ]);

        return to_route(
            'stock-transfers.edit',
            $stockTransfer,
        );
    }

    /**
     * Ship a transfer through authoritative outbound movements.
     */
    public function ship(
        StockTransferTransitionRequest $request,
        string $stockTransfer,
        ShipStockTransfer $shipStockTransfer,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $transfer = $request->stockTransfer();

        if (
            $organization === null
            || ! $actor instanceof User
            || $transfer === null
        ) {
            abort(403);
        }

        $shipStockTransfer->handle(
            $organization,
            $actor,
            $transfer,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(
                'Stock transfer shipped.',
            ),
        ]);

        return to_route(
            'stock-transfers.edit',
            $stockTransfer,
        );
    }

    /**
     * Receive actual quantities through authoritative inbound movements.
     */
    public function receive(
        ReceiveStockTransferRequest $request,
        string $stockTransfer,
        ReceiveStockTransfer $receiveStockTransfer,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $transfer = $request->stockTransfer();

        if (
            $organization === null
            || ! $actor instanceof User
            || $transfer === null
        ) {
            abort(403);
        }

        $receiveStockTransfer->handle(
            $organization,
            $actor,
            $transfer,
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(
                'Stock transfer received.',
            ),
        ]);

        return to_route(
            'stock-transfers.edit',
            $stockTransfer,
        );
    }

    /**
     * Cancel an inventory-neutral transfer draft.
     */
    public function cancel(
        StockTransferTransitionRequest $request,
        string $stockTransfer,
        CancelStockTransfer $cancelStockTransfer,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $transfer = $request->stockTransfer();

        if (
            $organization === null
            || ! $actor instanceof User
            || $transfer === null
        ) {
            abort(403);
        }

        $cancelStockTransfer->handle(
            $organization,
            $actor,
            $transfer,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(
                'Stock transfer cancelled.',
            ),
        ]);

        return to_route(
            'stock-transfers.edit',
            $stockTransfer,
        );
    }

    /**
     * Report immutable shipment-versus-receipt variance.
     */
    public function variance(Request $request): Response
    {
        $organization =
            $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        $validated = $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                ),
            ],
            'from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'to' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ]);

        $locationId =
            isset($validated['location_id'])
                ? (int) $validated['location_id']
                : null;

        $from = isset($validated['from'])
            && is_string($validated['from'])
                ? $validated['from']
                : null;

        $to = isset($validated['to'])
            && is_string($validated['to'])
                ? $validated['to']
                : null;

        if (
            $from !== null
            && $to !== null
            && $from > $to
        ) {
            throw ValidationException::withMessages([
                'from' => __(
                    'The from date must not be after the to date.',
                ),
            ]);
        }

        $query = StockTransfer::query()
            ->with([
                'fromLocation:id,name',
                'fromStorageLocation:id,name',
                'toLocation:id,name',
                'toStorageLocation:id,name',
                'lines.inventoryItem.baseUnitOfMeasure',
                'lines.outboundMovement',
                'lines.inboundMovement',
            ])
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where(
                'status',
                StockTransferStatus::Received->value,
            );

        if ($locationId !== null) {
            $query->where(
                function (
                    EloquentBuilder $locationQuery,
                ) use ($locationId): void {
                    $locationQuery
                        ->where(
                            'from_location_id',
                            $locationId,
                        )
                        ->orWhere(
                            'to_location_id',
                            $locationId,
                        );
                },
            );
        }

        if ($from !== null) {
            $query->where(
                'received_at',
                '>=',
                CarbonImmutable::parse(
                    $from,
                    $organization->timezone,
                )
                    ->startOfDay()
                    ->utc(),
            );
        }

        if ($to !== null) {
            $query->where(
                'received_at',
                '<=',
                CarbonImmutable::parse(
                    $to,
                    $organization->timezone,
                )
                    ->endOfDay()
                    ->utc(),
            );
        }

        $transfers = $query
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        $rows = [];

        foreach ($transfers as $transfer) {
            foreach ($transfer->lines as $line) {
                $varianceValue = null;

                if (
                    $canViewCosts
                    && $line->unit_cost !== null
                    && $line->variance_base_quantity
                        !== null
                ) {
                    $varianceValue = (string) BigDecimal::of(
                        $line->variance_base_quantity,
                    )
                        ->multipliedBy(
                            BigDecimal::of(
                                $line->unit_cost,
                            ),
                        )
                        ->toScale(
                            4,
                            RoundingMode::HalfUp,
                        );
                }

                $rows[] = [
                    'transferId' => $transfer->id,
                    'transferNumber' =>
                        $transfer->number,
                    'receivedAt' =>
                        $transfer->received_at
                            ?->toIso8601String(),
                    'fromLocationName' =>
                        $transfer->fromLocation->name,
                    'fromStorageLocationName' =>
                        $transfer
                            ->fromStorageLocation
                            ->name,
                    'toLocationName' =>
                        $transfer->toLocation->name,
                    'toStorageLocationName' =>
                        $transfer
                            ->toStorageLocation
                            ->name,
                    'itemName' =>
                        $line->inventoryItem->name,
                    'itemSku' =>
                        $line->inventoryItem->sku,
                    'shippedBaseQuantity' =>
                        $line->shipped_base_quantity,
                    'receivedBaseQuantity' =>
                        $line->received_base_quantity,
                    'varianceBaseQuantity' =>
                        $line->variance_base_quantity,
                    'baseUnitSymbol' =>
                        $line
                            ->inventoryItem
                            ->baseUnitOfMeasure
                            ->symbol,
                    'unitCost' => $canViewCosts
                        ? $line->unit_cost
                        : null,
                    'varianceValue' =>
                        $varianceValue,
                    'outboundMovementId' =>
                        $line
                            ->outboundMovement
                            ?->id,
                    'inboundMovementId' =>
                        $line
                            ->inboundMovement
                            ?->id,
                ];
            }
        }

        $locationOptions = Location::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ])
            ->map(
                static fn (
                    Location $location,
                ): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                ],
            )
            ->values()
            ->all();

        return Inertia::render(
            'stock-transfers/variance',
            [
                'rows' => $rows,
                'locationOptions' =>
                    $locationOptions,
                'filters' => [
                    'locationId' => $locationId,
                    'from' => $from,
                    'to' => $to,
                ],
                'currency' =>
                    $organization->currency,
                'canViewCosts' => $canViewCosts,
            ],
        );
    }

    /**
     * Serialize transfer history without leaking protected costs.
     *
     * @return array<string, mixed>
     */
    private function stockTransferData(
        StockTransfer $transfer,
        bool $canViewCosts,
    ): array {
        return [
            'id' => $transfer->id,
            'number' => $transfer->number,
            'status' => $transfer->status->value,
            'fromLocationId' =>
                $transfer->from_location_id,
            'fromLocationName' =>
                $transfer->fromLocation->name,
            'fromStorageLocationId' =>
                $transfer->from_storage_location_id,
            'fromStorageLocationName' =>
                $transfer->fromStorageLocation->name,
            'toLocationId' =>
                $transfer->to_location_id,
            'toLocationName' =>
                $transfer->toLocation->name,
            'toStorageLocationId' =>
                $transfer->to_storage_location_id,
            'toStorageLocationName' =>
                $transfer->toStorageLocation->name,
            'requestedAt' =>
                $transfer->requested_at
                    ?->toIso8601String(),
            'shippedAt' =>
                $transfer->shipped_at
                    ?->toIso8601String(),
            'receivedAt' =>
                $transfer->received_at
                    ?->toIso8601String(),
            'createdBy' =>
                $transfer->creator?->name,
            'shippedBy' =>
                $transfer->shipper?->name,
            'receivedBy' =>
                $transfer->receiver?->name,
            'notes' => $transfer->notes,
            'lines' => $transfer
                ->lines
                ->map(
                    static fn (
                        StockTransferLine $line,
                    ): array => [
                        'id' => $line->id,
                        'inventoryItemId' =>
                            $line->inventory_item_id,
                        'itemName' =>
                            $line
                                ->inventoryItem
                                ->name,
                        'itemSku' =>
                            $line
                                ->inventoryItem
                                ->sku,
                        'requestedQuantity' =>
                            $line->requested_quantity,
                        'unitId' => $line->unit_id,
                        'unitSymbol' =>
                            $line->unit->symbol,
                        'requestedBaseQuantity' =>
                            $line
                                ->requested_base_quantity,
                        'shippedBaseQuantity' =>
                            $line
                                ->shipped_base_quantity,
                        'receivedBaseQuantity' =>
                            $line
                                ->received_base_quantity,
                        'unitCost' => $canViewCosts
                            ? $line->unit_cost
                            : null,
                        'varianceBaseQuantity' =>
                            $line
                                ->variance_base_quantity,
                        'baseUnitSymbol' =>
                            $line
                                ->inventoryItem
                                ->baseUnitOfMeasure
                                ->symbol,
                        'outboundMovementId' =>
                            $line
                                ->outboundMovement
                                ?->id,
                        'inboundMovementId' =>
                            $line
                                ->inboundMovement
                                ?->id,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * Build active tenant-scoped transfer form options.
     *
     * @return array<string, mixed>
     */
    private function formOptions(
        Organization $organization,
        ConvertQuantity $convertQuantity,
    ): array {
        $units = UnitOfMeasure::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $inventoryItems = InventoryItem::query()
            ->with('baseUnitOfMeasure')
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->whereHas(
                'baseUnitOfMeasure',
                fn ($query) => $query
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where('active', true),
            )
            ->orderBy('name')
            ->get();

        return [
            'locationOptions' => Location::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where('active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ])
                ->map(
                    static fn (
                        Location $location,
                    ): array => [
                        'id' => $location->id,
                        'name' => $location->name,
                    ],
                )
                ->values()
                ->all(),
            'storageLocationOptions' =>
                StorageLocation::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where('active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'location_id',
                        'name',
                    ])
                    ->map(
                        static fn (
                            StorageLocation $storage,
                        ): array => [
                            'id' => $storage->id,
                            'locationId' =>
                                $storage->location_id,
                            'name' => $storage->name,
                        ],
                    )
                    ->values()
                    ->all(),
            'inventoryItemOptions' =>
                $inventoryItems
                    ->map(
                        fn (
                            InventoryItem $item,
                        ): array => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'baseUnitId' =>
                                $item
                                    ->base_unit_of_measure_id,
                            'baseUnitSymbol' =>
                                $item
                                    ->baseUnitOfMeasure
                                    ->symbol,
                            'validUnitIds' =>
                                $this->validUnitIds(
                                    $organization,
                                    $item,
                                    $units,
                                    $convertQuantity,
                                ),
                        ],
                    )
                    ->values()
                    ->all(),
            'unitOptions' => $units
                ->map(
                    static fn (
                        UnitOfMeasure $unit,
                    ): array => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'symbol' => $unit->symbol,
                    ],
                )
                ->values()
                ->all(),
            'currency' => $organization->currency,
        ];
    }

    /**
     * Resolve selectable units through the authoritative converter.
     *
     * @param  Collection<int, UnitOfMeasure>  $units
     * @return list<int>
     */
    private function validUnitIds(
        Organization $organization,
        InventoryItem $item,
        Collection $units,
        ConvertQuantity $convertQuantity,
    ): array {
        return array_values(
            $units
                ->filter(
                    function (
                        UnitOfMeasure $unit,
                    ) use (
                        $organization,
                        $item,
                        $convertQuantity,
                    ): bool {
                        try {
                            $convertQuantity->handle(
                                $organization,
                                $item,
                                '0.000001',
                                $unit,
                                $item->baseUnitOfMeasure,
                            );

                            return true;
                        } catch (
                            ValidationException
                        ) {
                            return false;
                        }
                    },
                )
                ->map(
                    static fn (
                        UnitOfMeasure $unit,
                    ): int => $unit->id,
                )
                ->all(),
        );
    }

    /**
     * Return transfer lifecycle permissions for the active organization.
     *
     * @return array<string, bool>
     */
    private function permissionData(
        Organization $organization,
    ): array {
        return [
            'canCreate' => Gate::allows(
                OrganizationPermission::TransfersCreate
                    ->value,
                $organization,
            ),
            'canShip' => Gate::allows(
                OrganizationPermission::TransfersShip
                    ->value,
                $organization,
            ),
            'canReceive' => Gate::allows(
                OrganizationPermission::TransfersReceive
                    ->value,
                $organization,
            ),
            'canViewCosts' => Gate::allows(
                OrganizationPermission::CostsView
                    ->value,
                $organization,
            ),
        ];
    }

    /**
     * Permit transfer history to operators or report readers.
     */
    private function authorizeTransferRead(
        Organization $organization,
    ): void {
        if (
            ! Gate::allows(
                OrganizationPermission::TransfersCreate
                    ->value,
                $organization,
            )
            && ! Gate::allows(
                OrganizationPermission::TransfersShip
                    ->value,
                $organization,
            )
            && ! Gate::allows(
                OrganizationPermission::TransfersReceive
                    ->value,
                $organization,
            )
            && ! Gate::allows(
                OrganizationPermission::ReportsView
                    ->value,
                $organization,
            )
        ) {
            abort(403);
        }
    }

    /**
     * Resolve active organization middleware context.
     */
    private function activeOrganization(
        Request $request,
    ): Organization {
        $organization = $request->attributes->get(
            'activeOrganization',
        );

        if (! $organization instanceof Organization) {
            abort(403);
        }

        return $organization;
    }
}
