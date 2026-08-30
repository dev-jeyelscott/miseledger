<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\ApprovePurchaseOrder;
use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseOrderTransitionRequest;
use App\Http\Requests\Purchasing\SavePurchaseOrderRequest;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierItem;
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
 * @phpstan-type PurchaseOrderIndexFilters array{
 *     search: string|null,
 *     status: string|null,
 *     supplierId: int|null,
 *     locationId: int|null,
 *     from: string|null,
 *     to: string|null
 * }
 * @phpstan-type PurchaseOrderIndexSummary array{
 *     openCount: int,
 *     awaitingDeliveryCount: int,
 *     partiallyReceivedCount: int,
 *     thisMonthSpend: string|null
 * }
 */
class PurchaseOrderController extends Controller
{
    /**
     * List tenant-scoped purchase orders with operational filters and summaries.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $filters = $this->resolveIndexFilters($request, $organization);

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        $purchaseOrders = $this->indexQuery($organization, $filters)
            ->with([
                'supplier:id,name',
                'location:id,name',
            ])
            ->withCount('lines')
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(
                static fn (PurchaseOrder $purchaseOrder): array => [
                    'id' => $purchaseOrder->id,
                    'number' => $purchaseOrder->number,
                    'status' => $purchaseOrder->status->value,
                    'supplierName' => $purchaseOrder->supplier->name,
                    'locationName' => $purchaseOrder->location->name,
                    'orderDate' => $purchaseOrder->order_date
                        ->toDateString(),
                    'expectedDeliveryDate' => $purchaseOrder
                        ->expected_delivery_date
                        ?->toDateString(),
                    'lineCount' => (int) $purchaseOrder->getAttribute(
                        'lines_count',
                    ),
                    'total' => $purchaseOrder->total,
                ],
            )
            ->toArray();

        return Inertia::render('purchase-orders/index', [
            'purchaseOrders' => $purchaseOrders,
            'summary' => $this->indexSummary(
                $organization,
                $canViewCosts,
            ),
            'supplierOptions' => $this->indexSupplierOptions(
                $organization,
            ),
            'locationOptions' => $this->indexLocationOptions(
                $organization,
            ),
            'filters' => $filters,
            'currency' => $organization->currency,
            'canManage' => Gate::allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            ),
            'canViewCosts' => $canViewCosts,
        ]);
    }

    /**
     * Render creation using active purchasing master data.
     */
    public function create(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingManage->value,
            $organization,
        );

        return Inertia::render('purchase-orders/form', [
            'purchaseOrder' => null,
            'defaultOrderDate' => CarbonImmutable::now(
                $organization->timezone,
            )->toDateString(),
            ...$this->formOptions($organization),
            'canManage' => true,
            'canReceive' => false,
        ]);
    }

    /**
     * Persist one draft PO.
     */
    public function store(
        SavePurchaseOrderRequest $request,
        SavePurchaseOrder $savePurchaseOrder,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();

        if (
            $organization === null
            || ! $actor instanceof User
        ) {
            abort(403);
        }

        $purchaseOrder = $savePurchaseOrder->handle(
            $organization,
            $actor,
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Purchase order created.'),
        ]);

        return to_route(
            'purchase-orders.edit',
            $purchaseOrder,
        );
    }

    /**
     * Show a PO for editing or immutable history.
     */
    public function edit(
        Request $request,
        string $purchaseOrder,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $record = PurchaseOrder::query()
            ->with([
                'supplier:id,name',
                'location:id,name',
                'lines.purchaseUnitOfMeasure:id,name,symbol',
            ])
            ->where('organization_id', $organization->id)
            ->findOrFail($purchaseOrder);

        $canManage = Gate::allows(
            OrganizationPermission::PurchasingManage->value,
            $organization,
        );

        $canReceive = Gate::allows(
            OrganizationPermission::ReceivingFinalize->value,
            $organization,
        ) && $record->status->canReceive();

        return Inertia::render('purchase-orders/form', [
            'purchaseOrder' => $this->purchaseOrderData($record),
            ...$this->formOptions($organization),
            'canManage' => $canManage,
            'canReceive' => $canReceive,
        ]);
    }

    /**
     * Replace one draft PO.
     */
    public function update(
        SavePurchaseOrderRequest $request,
        string $purchaseOrder,
        SavePurchaseOrder $savePurchaseOrder,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $record = $request->purchaseOrder();

        if (
            $organization === null
            || ! $actor instanceof User
            || $record === null
        ) {
            abort(403);
        }

        $savePurchaseOrder->handle(
            $organization,
            $actor,
            $request->validated(),
            $record,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Purchase order updated.'),
        ]);

        return to_route(
            'purchase-orders.edit',
            $purchaseOrder,
        );
    }

    /**
     * Approve a PO without changing stock.
     */
    public function approve(
        PurchaseOrderTransitionRequest $request,
        string $purchaseOrder,
        ApprovePurchaseOrder $approvePurchaseOrder,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $record = $request->purchaseOrder();

        if (
            $organization === null
            || ! $actor instanceof User
            || $record === null
        ) {
            abort(403);
        }

        $approvePurchaseOrder->handle(
            $organization,
            $actor,
            $record,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Purchase order approved.'),
        ]);

        return to_route(
            'purchase-orders.edit',
            $purchaseOrder,
        );
    }

    /**
     * Cancel an unreceived PO.
     */
    public function cancel(
        PurchaseOrderTransitionRequest $request,
        string $purchaseOrder,
        CancelPurchaseOrder $cancelPurchaseOrder,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $record = $request->purchaseOrder();

        if (
            $organization === null
            || ! $actor instanceof User
            || $record === null
        ) {
            abort(403);
        }

        $cancelPurchaseOrder->handle(
            $organization,
            $actor,
            $record,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Purchase order cancelled.'),
        ]);

        return to_route(
            'purchase-orders.edit',
            $purchaseOrder,
        );
    }

    /**
     * Validate and normalize server-authoritative Purchase Orders list filters.
     *
     * @return PurchaseOrderIndexFilters
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
                            PurchaseOrderStatus $status,
                        ): string => $status->value,
                        PurchaseOrderStatus::cases(),
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

        return [
            'search' => $search,
            'status' => $status,
            'supplierId' => $supplierId,
            'locationId' => $locationId,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Build the filtered tenant-scoped query used by the Purchase Orders list.
     *
     * @param  PurchaseOrderIndexFilters  $filters
     * @return EloquentBuilder<PurchaseOrder>
     */
    private function indexQuery(
        Organization $organization,
        array $filters,
    ): EloquentBuilder {
        $query = PurchaseOrder::query()
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
            $query->whereDate(
                'order_date',
                '>=',
                $filters['from'],
            );
        }

        if ($filters['to'] !== null) {
            $query->whereDate(
                'order_date',
                '<=',
                $filters['to'],
            );
        }

        return $query;
    }

    /**
     * Build stable organization-wide operational Purchase Orders metrics.
     *
     * @return PurchaseOrderIndexSummary
     */
    private function indexSummary(
        Organization $organization,
        bool $canViewCosts,
    ): array {
        $baseQuery = PurchaseOrder::query()
            ->where(
                'organization_id',
                $organization->id,
            );

        return [
            'openCount' => (clone $baseQuery)
                ->whereIn('status', [
                    PurchaseOrderStatus::Draft->value,
                    PurchaseOrderStatus::Approved->value,
                    PurchaseOrderStatus::PartiallyReceived->value,
                ])
                ->count(),

            'awaitingDeliveryCount' => (clone $baseQuery)
                ->where(
                    'status',
                    PurchaseOrderStatus::Approved->value,
                )
                ->count(),

            'partiallyReceivedCount' => (clone $baseQuery)
                ->where(
                    'status',
                    PurchaseOrderStatus::PartiallyReceived->value,
                )
                ->count(),

            'thisMonthSpend' => $canViewCosts
                ? $this->thisMonthPurchaseOrderSpend($organization)
                : null,
        ];
    }

    /**
     * Sum this business month's non-draft, non-cancelled PO totals exactly.
     */
    private function thisMonthPurchaseOrderSpend(
        Organization $organization,
    ): string {
        $businessNow = CarbonImmutable::now(
            $organization->timezone,
        );

        $from = $businessNow
            ->startOfMonth()
            ->toDateString();

        $to = $businessNow
            ->endOfMonth()
            ->toDateString();

        $total = BigDecimal::zero();

        foreach (
            PurchaseOrder::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->whereBetween(
                    'order_date',
                    [$from, $to],
                )
                ->whereIn('status', [
                    PurchaseOrderStatus::Approved->value,
                    PurchaseOrderStatus::PartiallyReceived->value,
                    PurchaseOrderStatus::Received->value,
                ])
                ->select([
                    'id',
                    'total',
                ])
                ->cursor() as $purchaseOrder
        ) {
            $total = $total->plus(
                $purchaseOrder->total,
            );
        }

        return (string) $total->toScale(2);
    }

    /**
     * Return every tenant supplier so historical POs remain filterable.
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
     * Return every tenant location so historical POs remain filterable.
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
     * Build active supplier-item and location form options.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Organization $organization): array
    {
        $supplierItems = SupplierItem::query()
            ->with([
                'inventoryItem:id,name,sku,active',
                'purchaseUnitOfMeasure:id,name,symbol,active',
            ])
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->whereNotNull('current_price')
            ->get()
            ->filter(
                static fn (SupplierItem $supplierItem): bool => $supplierItem
                    ->inventoryItem
                    ->active
                    && $supplierItem
                        ->purchaseUnitOfMeasure
                        ->active,
            )
            ->groupBy('supplier_id');

        $suppliers = Supplier::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(function (
                Supplier $supplier,
            ) use ($supplierItems): array {
                $items = $supplierItems->get(
                    $supplier->id,
                    collect(),
                );

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'items' => $items
                        ->map(
                            static fn (
                                SupplierItem $supplierItem,
                            ): array => [
                                'id' => $supplierItem->id,
                                'supplierSku' => $supplierItem
                                    ->supplier_sku,
                                'itemName' => $supplierItem
                                    ->inventoryItem
                                    ->name,
                                'purchaseUnit' => $supplierItem
                                    ->purchaseUnitOfMeasure
                                    ->symbol,
                                'baseQuantity' => $supplierItem
                                    ->base_quantity,
                                'currentPrice' => $supplierItem
                                    ->current_price,
                            ],
                        )
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $locations = Location::query()
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
                static fn (Location $location): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                ],
            )
            ->values()
            ->all();

        return [
            'supplierOptions' => $suppliers,
            'locationOptions' => $locations,
            'currency' => $organization->currency,
        ];
    }

    /**
     * Serialize one PO without exposing unrelated model data.
     *
     * @return array<string, mixed>
     */
    private function purchaseOrderData(
        PurchaseOrder $purchaseOrder,
    ): array {
        return [
            'id' => $purchaseOrder->id,
            'number' => $purchaseOrder->number,
            'status' => $purchaseOrder->status->value,
            'supplierId' => $purchaseOrder->supplier_id,
            'supplierName' => $purchaseOrder->supplier->name,
            'locationId' => $purchaseOrder->location_id,
            'locationName' => $purchaseOrder->location->name,
            'orderDate' => $purchaseOrder
                ->order_date
                ->toDateString(),
            'expectedDeliveryDate' => $purchaseOrder
                ->expected_delivery_date
                ?->toDateString(),
            'subtotal' => $purchaseOrder->subtotal,
            'taxTotal' => $purchaseOrder->tax_total,
            'discountTotal' => $purchaseOrder->discount_total,
            'total' => $purchaseOrder->total,
            'notes' => $purchaseOrder->notes,
            'approvedAt' => $purchaseOrder
                ->approved_at
                ?->toIso8601String(),
            'lines' => $purchaseOrder
                ->lines
                ->map(
                    static fn (
                        PurchaseOrderLine $line,
                    ): array => [
                        'id' => $line->id,
                        'supplierItemId' => $line
                            ->supplier_item_id,
                        'itemName' => $line
                            ->item_name_snapshot,
                        'supplierSku' => $line
                            ->supplier_sku_snapshot,
                        'orderedQuantity' => $line
                            ->ordered_quantity,
                        'purchaseUnit' => [
                            'id' => $line
                                ->purchaseUnitOfMeasure
                                ->id,
                            'symbol' => $line
                                ->purchaseUnitOfMeasure
                                ->symbol,
                        ],
                        'baseQuantity' => $line->base_quantity,
                        'unitPrice' => $line->unit_price,
                        'lineTotal' => $line->line_total,
                        'receivedBaseQuantity' => $line
                            ->received_base_quantity,
                    ],
                )
                ->values()
                ->all(),
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
