<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\CancelGoodsReceipt;
use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\GoodsReceiptTransitionRequest;
use App\Http\Requests\Purchasing\SaveGoodsReceiptRequest;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNonStockLine;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @phpstan-type GoodsReceiptIndexFilters array{
 *     search: string|null,
 *     status: string|null,
 *     supplierId: int|null,
 *     locationId: int|null,
 *     from: string|null,
 *     to: string|null,
 *     sort: string
 * }
 * @phpstan-type GoodsReceiptIndexSummary array{
 *     totalCount: int,
 *     draftCount: int,
 *     finalizedCount: int,
 *     receivedThisWeekCount: int
 * }
 */
class GoodsReceiptController extends Controller
{
    /**
     * List tenant-scoped receiving history with operational filters and summaries.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $filters = $this->resolveIndexFilters(
            $request,
            $organization,
        );

        $receiptsQuery = $this->indexQuery(
            $organization,
            $filters,
        );

        $this->applyIndexSort(
            $receiptsQuery,
            $filters['sort'],
        );

        $receipts = $receiptsQuery
            ->with([
                'purchaseOrder:id,number',
                'supplier:id,name',
                'location:id,name',
                'receiver:id,name',
            ])
            ->withCount('lines')
            ->paginate(25)
            ->withQueryString()
            ->through(
                static fn (GoodsReceipt $receipt): array => [
                    'id' => $receipt->id,
                    'number' => $receipt->number,
                    'status' => $receipt->status->value,
                    'purchaseOrderId' => $receipt->purchaseOrder->id,
                    'purchaseOrderNumber' => $receipt
                        ->purchaseOrder
                        ->number,
                    'supplierName' => $receipt->supplier->name,
                    'locationName' => $receipt->location->name,
                    'acceptedLineCount' => (int) $receipt->getAttribute(
                        'lines_count',
                    ),
                    'receivedAt' => $receipt->received_at
                        ?->toIso8601String(),
                    'receivedBy' => $receipt->receiver?->name,
                ],
            )
            ->toArray();

        return Inertia::render('goods-receipts/index', [
            'receipts' => $receipts,
            'summary' => $this->indexSummary($organization),
            'supplierOptions' => $this->indexSupplierOptions(
                $organization,
            ),
            'locationOptions' => $this->indexLocationOptions(
                $organization,
            ),
            'filters' => $filters,
            'timezone' => $organization->timezone,
            'canFinalize' => Gate::allows(
                OrganizationPermission::ReceivingFinalize->value,
                $organization,
            ),
        ]);
    }

    /**
     * Create a draft receipt against an approved PO.
     */
    public function create(
        Request $request,
        string $purchaseOrder,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReceivingFinalize->value,
            $organization,
        );

        $po = PurchaseOrder::query()
            ->with([
                'supplier:id,name',
                'location:id,name',
                'lines.purchaseUnitOfMeasure:id,name,symbol',
            ])
            ->where('organization_id', $organization->id)
            ->findOrFail($purchaseOrder);

        abort_unless($po->status->canReceive(), 404);

        return Inertia::render('goods-receipts/form', [
            'goodsReceipt' => null,
            'purchaseOrder' => $this->purchaseOrderData($po),
            ...$this->formOptions($organization, $po),
            'canFinalize' => true,
            'auditTrail' => [],
        ]);
    }

    /**
     * Persist a receipt draft against the nested PO.
     */
    public function store(
        SaveGoodsReceiptRequest $request,
        string $purchaseOrder,
        SaveGoodsReceipt $saveGoodsReceipt,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $po = $request->purchaseOrder();

        if (
            $organization === null
            || ! $actor instanceof User
            || $po === null
        ) {
            abort(403);
        }

        $receipt = $saveGoodsReceipt->handle(
            $organization,
            $actor,
            $po,
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Goods receipt draft created.'),
        ]);

        return to_route(
            'goods-receipts.edit',
            $receipt,
        );
    }

    /**
     * Show a draft or immutable finalized receipt.
     */
    public function edit(
        Request $request,
        string $goodsReceipt,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $receipt = GoodsReceipt::query()
            ->with([
                'purchaseOrder.supplier:id,name',
                'purchaseOrder.location:id,name',
                'purchaseOrder.lines.purchaseUnitOfMeasure:id,name,symbol',
                'supplier:id,name',
                'location:id,name',
                'receiver:id,name',
                'lines.purchaseOrderLine',
                'lines.inventoryItem:id,name,sku',
                'lines.storageLocation:id,name',
                'lines.receivedUnitOfMeasure:id,name,symbol',
                'lines.movement.creator:id,name',
                'nonStockLines.inventoryItem:id,name,sku',
                'nonStockLines.rejectedUnitOfMeasure:id,name,symbol',
                'nonStockLines.damagedUnitOfMeasure:id,name,symbol',
            ])
            ->where('organization_id', $organization->id)
            ->findOrFail($goodsReceipt);

        $canFinalize = Gate::allows(
            OrganizationPermission::ReceivingFinalize->value,
            $organization,
        );

        return Inertia::render('goods-receipts/form', [
            'goodsReceipt' => $this->receiptData($receipt),
            'purchaseOrder' => $this->purchaseOrderData(
                $receipt->purchaseOrder,
            ),
            ...$this->formOptions(
                $organization,
                $receipt->purchaseOrder,
            ),
            'canFinalize' => $canFinalize,
            'auditTrail' => $this->auditTrailData(
                $organization,
                $receipt,
            ),
        ]);
    }

    /**
     * Replace a draft receipt.
     */
    public function update(
        SaveGoodsReceiptRequest $request,
        string $goodsReceipt,
        SaveGoodsReceipt $saveGoodsReceipt,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $receipt = $request->goodsReceipt();
        $purchaseOrder = $request->purchaseOrder();

        if (
            $organization === null
            || ! $actor instanceof User
            || $receipt === null
            || $purchaseOrder === null
        ) {
            abort(403);
        }

        $saveGoodsReceipt->handle(
            $organization,
            $actor,
            $purchaseOrder,
            $request->validated(),
            $receipt,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Goods receipt draft updated.'),
        ]);

        return to_route(
            'goods-receipts.edit',
            $goodsReceipt,
        );
    }

    /**
     * Finalize receiving and create authoritative inventory movements.
     */
    public function finalize(
        GoodsReceiptTransitionRequest $request,
        string $goodsReceipt,
        FinalizeGoodsReceipt $finalizeGoodsReceipt,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $receipt = $request->goodsReceipt();

        if (
            $organization === null
            || ! $actor instanceof User
            || $receipt === null
        ) {
            abort(403);
        }

        $finalizeGoodsReceipt->handle(
            $organization,
            $actor,
            $receipt,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Goods receipt finalized.'),
        ]);

        return to_route(
            'goods-receipts.edit',
            $goodsReceipt,
        );
    }

    /**
     * Cancel one inventory-neutral draft receipt.
     */
    public function cancel(
        GoodsReceiptTransitionRequest $request,
        string $goodsReceipt,
        CancelGoodsReceipt $cancelGoodsReceipt,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $receipt = $request->goodsReceipt();

        if (
            $organization === null
            || ! $actor instanceof User
            || $receipt === null
        ) {
            abort(403);
        }

        $cancelGoodsReceipt->handle(
            $organization,
            $actor,
            $receipt,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Goods receipt cancelled.'),
        ]);

        return to_route(
            'goods-receipts.edit',
            $goodsReceipt,
        );
    }

    /**
     * Validate and normalize server-authoritative Receiving list filters.
     *
     * @return GoodsReceiptIndexFilters
     */
    private function resolveIndexFilters(
        Request $request,
        Organization $organization,
    ): array {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(
                    array_map(
                        static fn (
                            GoodsReceiptStatus $status,
                        ): string => $status->value,
                        GoodsReceiptStatus::cases(),
                    ),
                ),
            ],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                ),
            ],
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
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'latest',
                    'oldest',
                    'receipt_asc',
                    'receipt_desc',
                    'status',
                ]),
            ],
        ]);

        $search = isset($validated['search'])
            && is_string($validated['search'])
                ? trim($validated['search'])
                : null;

        if ($search === '') {
            $search = null;
        }

        $status = isset($validated['status'])
            && is_string($validated['status'])
                ? $validated['status']
                : null;

        $supplierId = isset($validated['supplier_id'])
            ? (int) $validated['supplier_id']
            : null;

        $locationId = isset($validated['location_id'])
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

        $sort = isset($validated['sort'])
            && is_string($validated['sort'])
                ? $validated['sort']
                : 'latest';

        if (
            $from !== null
            && $to !== null
            && $from > $to
        ) {
            throw ValidationException::withMessages([
                'from' => __(
                    'The received-from date must not be after the received-to date.',
                ),
            ]);
        }

        return [
            'search' => $search,
            'status' => $status,
            'supplierId' => $supplierId,
            'locationId' => $locationId,
            'from' => $from,
            'to' => $to,
            'sort' => $sort,
        ];
    }

    /**
     * Build the filtered tenant-scoped query used by the Receiving register.
     *
     * @param  GoodsReceiptIndexFilters  $filters
     * @return EloquentBuilder<GoodsReceipt>
     */
    private function indexQuery(
        Organization $organization,
        array $filters,
    ): EloquentBuilder {
        $query = GoodsReceipt::query()
            ->where('organization_id', $organization->id);

        if ($filters['search'] !== null) {
            $searchPattern = "%{$filters['search']}%";

            $query->where(
                function (
                    EloquentBuilder $searchQuery,
                ) use ($searchPattern): void {
                    $searchQuery
                        ->whereLike(
                            'number',
                            $searchPattern,
                        )
                        ->orWhereHas(
                            'purchaseOrder',
                            fn (
                                EloquentBuilder $purchaseOrderQuery,
                            ): EloquentBuilder => $purchaseOrderQuery
                                ->whereLike(
                                    'number',
                                    $searchPattern,
                                ),
                        )
                        ->orWhereHas(
                            'supplier',
                            fn (
                                EloquentBuilder $supplierQuery,
                            ): EloquentBuilder => $supplierQuery->whereLike(
                                'name',
                                $searchPattern,
                            ),
                        );
                },
            );
        }

        if ($filters['status'] !== null) {
            $query->where(
                'status',
                $filters['status'],
            );
        }

        if ($filters['supplierId'] !== null) {
            $query->where(
                'supplier_id',
                $filters['supplierId'],
            );
        }

        if ($filters['locationId'] !== null) {
            $query->where(
                'location_id',
                $filters['locationId'],
            );
        }

        if ($filters['from'] !== null) {
            $from = CarbonImmutable::parse(
                $filters['from'],
                $organization->timezone,
            )
                ->startOfDay()
                ->utc();

            $query->where(
                'received_at',
                '>=',
                $from,
            );
        }

        if ($filters['to'] !== null) {
            $to = CarbonImmutable::parse(
                $filters['to'],
                $organization->timezone,
            )
                ->endOfDay()
                ->utc();

            $query->where(
                'received_at',
                '<=',
                $to,
            );
        }

        return $query;
    }

    /**
     * Apply deterministic database-portable ordering to Receiving rows.
     *
     * @param  EloquentBuilder<GoodsReceipt>  $query
     */
    private function applyIndexSort(
        EloquentBuilder $query,
        string $sort,
    ): void {
        if ($sort === 'oldest') {
            $query->orderBy('id');

            return;
        }

        if ($sort === 'receipt_asc') {
            $query
                ->orderBy('number')
                ->orderBy('id');

            return;
        }

        if ($sort === 'receipt_desc') {
            $query
                ->orderByDesc('number')
                ->orderByDesc('id');

            return;
        }

        if ($sort === 'status') {
            $query
                ->orderBy('status')
                ->orderByDesc('id');

            return;
        }

        $query->orderByDesc('id');
    }

    /**
     * Build stable organization-wide Receiving metrics.
     *
     * @return GoodsReceiptIndexSummary
     */
    private function indexSummary(
        Organization $organization,
    ): array {
        $businessNow = CarbonImmutable::now(
            $organization->timezone,
        );

        $weekStart = $businessNow
            ->startOfWeek()
            ->startOfDay()
            ->utc();

        $weekEnd = $businessNow
            ->endOfWeek()
            ->endOfDay()
            ->utc();

        $baseQuery = GoodsReceipt::query()
            ->where(
                'organization_id',
                $organization->id,
            );

        return [
            'totalCount' => (clone $baseQuery)->count(),

            'draftCount' => (clone $baseQuery)
                ->where(
                    'status',
                    GoodsReceiptStatus::Draft->value,
                )
                ->count(),

            'finalizedCount' => (clone $baseQuery)
                ->where(
                    'status',
                    GoodsReceiptStatus::Finalized->value,
                )
                ->count(),

            'receivedThisWeekCount' => (clone $baseQuery)
                ->where(
                    'status',
                    GoodsReceiptStatus::Finalized->value,
                )
                ->whereBetween(
                    'received_at',
                    [$weekStart, $weekEnd],
                )
                ->count(),
        ];
    }

    /**
     * Return every tenant supplier so historical receipts remain filterable.
     *
     * @return list<array{id: int, name: string}>
     */
    private function indexSupplierOptions(
        Organization $organization,
    ): array {
        return array_values(
            Supplier::query()
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
                    static fn (Supplier $supplier): array => [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                    ],
                )
                ->all(),
        );
    }

    /**
     * Return every tenant location so historical receipts remain filterable.
     *
     * @return list<array{id: int, name: string}>
     */
    private function indexLocationOptions(
        Organization $organization,
    ): array {
        return array_values(
            Location::query()
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
                    static fn (Location $location): array => [
                        'id' => $location->id,
                        'name' => $location->name,
                    ],
                )
                ->all(),
        );
    }

    /**
     * Serialize PO fulfillment with non-negative remaining and explicit over-receipt quantities.
     *
     * @return array<string, mixed>
     */
    private function purchaseOrderData(
        PurchaseOrder $purchaseOrder,
    ): array {
        $purchaseOrder->loadMissing([
            'supplier:id,name',
            'location:id,name',
            'lines.purchaseUnitOfMeasure:id,name,symbol',
        ]);

        return [
            'id' => $purchaseOrder->id,
            'number' => $purchaseOrder->number,
            'status' => $purchaseOrder->status->value,
            'supplierName' => $purchaseOrder->supplier->name,
            'locationName' => $purchaseOrder->location->name,
            'lines' => $purchaseOrder
                ->lines
                ->map(
                    static function (
                        PurchaseOrderLine $line,
                    ): array {
                        $baseQuantity = BigDecimal::of(
                            $line->base_quantity,
                        );

                        $receivedBaseQuantity = BigDecimal::of(
                            $line->received_base_quantity,
                        );

                        $remainingBaseQuantity = $baseQuantity->compareTo(
                            $receivedBaseQuantity,
                        ) > 0
                            ? $baseQuantity->minus($receivedBaseQuantity)
                            : BigDecimal::of('0.000000');

                        $overReceivedBaseQuantity = $receivedBaseQuantity
                            ->compareTo($baseQuantity) > 0
                            ? $receivedBaseQuantity->minus($baseQuantity)
                            : BigDecimal::of('0.000000');

                        return [
                            'id' => $line->id,
                            'itemName' => $line->item_name_snapshot,
                            'supplierSku' => $line->supplier_sku_snapshot,
                            'orderedQuantity' => $line->ordered_quantity,
                            'baseQuantity' => $line->base_quantity,
                            'receivedBaseQuantity' => $line
                                ->received_base_quantity,
                            'remainingBaseQuantity' => (string) $remainingBaseQuantity,
                            'overReceivedBaseQuantity' => (string) $overReceivedBaseQuantity,
                            'purchaseUnit' => [
                                'id' => $line->purchaseUnitOfMeasure->id,
                                'symbol' => $line
                                    ->purchaseUnitOfMeasure
                                    ->symbol,
                            ],
                        ];
                    },
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * Serialize accepted lines together with linked or standalone non-stock evidence.
     *
     * @return array<string, mixed>
     */
    private function receiptData(
        GoodsReceipt $receipt,
    ): array {
        $nonStockByAcceptedLine = $receipt
            ->nonStockLines
            ->filter(
                static fn (
                    GoodsReceiptNonStockLine $line,
                ): bool => $line->goods_receipt_line_id !== null,
            )
            ->keyBy(
                static fn (
                    GoodsReceiptNonStockLine $line,
                ): int => (int) $line->goods_receipt_line_id,
            );

        $lines = $receipt
            ->lines
            ->map(function (GoodsReceiptLine $line) use (
                $nonStockByAcceptedLine,
            ): array {
                $evidence = $nonStockByAcceptedLine->get($line->id);

                return $this->acceptedReceiptLineData(
                    $line,
                    $evidence instanceof GoodsReceiptNonStockLine
                        ? $evidence
                        : null,
                );
            });

        $standaloneNonStockLines = $receipt
            ->nonStockLines
            ->filter(
                static fn (
                    GoodsReceiptNonStockLine $line,
                ): bool => $line->goods_receipt_line_id === null,
            )
            ->map(
                fn (
                    GoodsReceiptNonStockLine $line,
                ): array => $this->standaloneNonStockLineData($line),
            );

        return [
            'id' => $receipt->id,
            'number' => $receipt->number,
            'status' => $receipt->status->value,
            'supplierReference' => $receipt->supplier_reference,
            'notes' => $receipt->notes,
            'receivedAt' => $receipt->received_at
                ?->toIso8601String(),
            'receivedBy' => $receipt->receiver?->name,
            'lines' => $lines
                ->concat($standaloneNonStockLines)
                ->values()
                ->all(),
        ];
    }

    /**
     * Serialize one accepted stock line and its optional non-stock evidence.
     *
     * @return array<string, mixed>
     */
    private function acceptedReceiptLineData(
        GoodsReceiptLine $line,
        ?GoodsReceiptNonStockLine $evidence,
    ): array {
        return [
            'key' => "accepted:{$line->id}",
            'id' => $line->id,
            'purchaseOrderLineId' => $line->purchase_order_line_id,
            'itemName' => $line->inventoryItem->name,
            'storageLocationId' => $line->storage_location_id,
            'storageLocationName' => $line->storageLocation->name,
            'receivedQuantity' => $line->received_quantity,
            'receivedUnitId' => $line->received_unit_of_measure_id,
            'receivedUnitSymbol' => $line->receivedUnitOfMeasure->symbol,
            'baseQuantity' => $line->base_quantity,
            'unitCost' => $line->unit_cost,
            'totalCost' => $line->total_cost,
            'rejectedQuantity' => $evidence->rejected_quantity
                ?? '0.000000',
            'rejectedUnitId' => $evidence?->rejected_unit_of_measure_id,
            'rejectedUnitSymbol' => $evidence
                ?->rejectedUnitOfMeasure
                ?->symbol,
            'rejectedBaseQuantity' => $evidence?->rejected_base_quantity,
            'damagedQuantity' => $evidence->damaged_quantity
                ?? '0.000000',
            'damagedUnitId' => $evidence?->damaged_unit_of_measure_id,
            'damagedUnitSymbol' => $evidence
                ?->damagedUnitOfMeasure
                ?->symbol,
            'damagedBaseQuantity' => $evidence?->damaged_base_quantity,
            'notes' => $line->notes,
            'movement' => $line->movement === null
                ? null
                : [
                    'id' => $line->movement->id,
                    'quantity' => $line->movement->quantity,
                    'unitCost' => $line->movement->unit_cost,
                    'occurredAt' => $line
                        ->movement
                        ->occurred_at
                        ->toIso8601String(),
                    'actorName' => $line->movement->creator?->name,
                ],
        ];
    }

    /**
     * Serialize an all-rejected/all-damaged row without inventing stock data.
     *
     * @return array<string, mixed>
     */
    private function standaloneNonStockLineData(
        GoodsReceiptNonStockLine $line,
    ): array {
        return [
            'key' => "non-stock:{$line->id}",
            'id' => null,
            'purchaseOrderLineId' => $line->purchase_order_line_id,
            'itemName' => $line->inventoryItem->name,
            'storageLocationId' => null,
            'storageLocationName' => null,
            'receivedQuantity' => '0.000000',
            'receivedUnitId' => null,
            'receivedUnitSymbol' => null,
            'baseQuantity' => '0.000000',
            'unitCost' => '0.0000',
            'totalCost' => '0.0000',
            'rejectedQuantity' => $line->rejected_quantity
                ?? '0.000000',
            'rejectedUnitId' => $line->rejected_unit_of_measure_id,
            'rejectedUnitSymbol' => $line
                ->rejectedUnitOfMeasure
                ?->symbol,
            'rejectedBaseQuantity' => $line->rejected_base_quantity,
            'damagedQuantity' => $line->damaged_quantity
                ?? '0.000000',
            'damagedUnitId' => $line->damaged_unit_of_measure_id,
            'damagedUnitSymbol' => $line
                ->damagedUnitOfMeasure
                ?->symbol,
            'damagedBaseQuantity' => $line->damaged_base_quantity,
            'notes' => $line->notes,
            'movement' => null,
        ];
    }

    /**
     * Serialize the immutable audit trail for one receipt, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function auditTrailData(
        Organization $organization,
        GoodsReceipt $receipt,
    ): array {
        return AuditLog::query()
            ->with('actor:id,name')
            ->where('organization_id', $organization->id)
            ->where('entity_type', 'goods_receipt')
            ->where('entity_id', $receipt->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(
                static fn (AuditLog $entry): array => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'actorName' => $entry->actor?->name,
                    'createdAt' => $entry
                        ->created_at
                        ?->toIso8601String(),
                ],
            )
            ->values()
            ->all();
    }

    /**
     * Build active storage and UOM choices.
     *
     * @return array<string, mixed>
     */
    private function formOptions(
        Organization $organization,
        PurchaseOrder $purchaseOrder,
    ): array {
        $storageLocations = StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $purchaseOrder->location_id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(
                static fn (
                    StorageLocation $storageLocation,
                ): array => [
                    'id' => $storageLocation->id,
                    'name' => $storageLocation->name,
                ],
            )
            ->values()
            ->all();

        $units = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'symbol'])
            ->map(
                static fn (UnitOfMeasure $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                ],
            )
            ->values()
            ->all();

        return [
            'storageLocationOptions' => $storageLocations,
            'unitOptions' => $units,
            'currency' => $organization->currency,
        ];
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
