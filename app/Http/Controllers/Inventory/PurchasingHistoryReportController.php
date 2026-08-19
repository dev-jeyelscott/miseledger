<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Support\Csv\CsvExport;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchasingHistoryReportController extends Controller
{
    private const RECEIPT_STATES = [
        'received',
        'partial',
        'not_received',
        'over_received',
    ];

    /**
     * Report purchase orders and receiving history with filter-aware summaries.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        [$filters, $query, $canViewCosts] = $this->filteredQuery(
            $request,
            $organization,
        );

        $purchaseOrders = (clone $query)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();

        $rows = $this->filteredRows(
            $purchaseOrders,
            $filters,
            $canViewCosts,
        );

        return Inertia::render('inventory/purchasing-history', [
            'rows' => $rows,
            'summary' => $this->summaryData($rows, $canViewCosts),
            'supplierOptions' => $this->supplierOptions($organization),
            'locationOptions' => $this->locationOptions($organization),
            'filters' => $filters,
            'currency' => $organization->currency,
            'canViewCosts' => $canViewCosts,
            'canViewPurchaseOrders' => Gate::allows(
                OrganizationPermission::PurchasingView->value,
                $organization,
            ),
        ]);
    }

    /**
     * Stream the exact tenant-, permission-, and filter-scoped report as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        [$filters, $query, $canViewCosts] = $this->filteredQuery(
            $request,
            $organization,
        );

        $header = [
            'PO Number',
            'Status',
            'Order Date',
            'Supplier',
            'Location',
            'Item',
            'Supplier SKU',
            'Ordered Quantity',
            'Purchase Unit',
            'Base Quantity',
            'Base Unit',
            'Received Base Quantity',
            'Remaining Base Quantity',
            'Over Received Base Quantity',
            'Receipt State',
        ];

        if ($canViewCosts) {
            $header[] = 'Unit Price';
            $header[] = 'Line Total';
        }

        $rows = (function () use (
            $query,
            $filters,
            $canViewCosts,
        ): iterable {
            foreach (
                $query
                    ->orderByDesc('order_date')
                    ->orderByDesc('id')
                    ->cursor() as $purchaseOrder
            ) {
                foreach ($purchaseOrder->lines as $line) {
                    $data = $this->lineData(
                        $purchaseOrder,
                        $line,
                        $canViewCosts,
                    );

                    if (! $this->rowMatchesFilters($data, $filters)) {
                        continue;
                    }

                    $row = [
                        $data['purchaseOrderNumber'],
                        $data['purchaseOrderStatus'],
                        $data['orderDate'],
                        $data['supplierName'],
                        $data['locationName'],
                        $data['itemName'],
                        $data['supplierSku'],
                        $data['orderedQuantity'],
                        $data['purchaseUnitSymbol'],
                        $data['baseQuantity'],
                        $data['baseUnitSymbol'],
                        $data['receivedBaseQuantity'],
                        $data['remainingBaseQuantity'],
                        $data['overReceivedBaseQuantity'],
                        $data['receiptState'],
                    ];

                    if ($canViewCosts) {
                        $row[] = $data['unitPrice'];
                        $row[] = $data['lineTotal'];
                    }

                    yield $row;
                }
            }
        })();

        return CsvExport::download(
            'purchasing-history.csv',
            $header,
            $rows,
        );
    }

    /**
     * Build the shared tenant-scoped query used by both screen and CSV output.
     *
     * @return array{
     *     0: array{
     *         supplierId: int|null,
     *         locationId: int|null,
     *         from: string|null,
     *         to: string|null,
     *         search: string|null,
     *         receiptState: string|null
     *     },
     *     1: EloquentBuilder<PurchaseOrder>,
     *     2: bool
     * }
     */
    private function filteredQuery(
        Request $request,
        Organization $organization,
    ): array {
        $validated = $request->validate([
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
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],
            'receipt_state' => [
                'nullable',
                'string',
                Rule::in(self::RECEIPT_STATES),
            ],
        ]);

        $supplierId = isset($validated['supplier_id'])
            ? (int) $validated['supplier_id']
            : null;

        $locationId = isset($validated['location_id'])
            ? (int) $validated['location_id']
            : null;

        $from = isset($validated['from']) && is_string($validated['from'])
            ? $validated['from']
            : null;

        $to = isset($validated['to']) && is_string($validated['to'])
            ? $validated['to']
            : null;

        $search = isset($validated['search']) && is_string($validated['search'])
            ? trim($validated['search'])
            : null;

        $receiptState = isset($validated['receipt_state'])
            && is_string($validated['receipt_state'])
                ? $validated['receipt_state']
                : null;

        if ($search === '') {
            $search = null;
        }

        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::withMessages([
                'from' => __(
                    'The from date must not be after the to date.',
                ),
            ]);
        }

        $query = PurchaseOrder::query()
            ->with([
                'supplier:id,name',
                'location:id,name',
                'lines.inventoryItem:id,name,base_unit_of_measure_id',
                'lines.inventoryItem.baseUnitOfMeasure:id,symbol',
                'lines.purchaseUnitOfMeasure:id,symbol',
            ])
            ->where('organization_id', $organization->id);

        if ($supplierId !== null) {
            $query->where('supplier_id', $supplierId);
        }

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        if ($from !== null) {
            $query->whereDate('order_date', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('order_date', '<=', $to);
        }

        if ($search !== null) {
            $searchPattern = "%{$search}%";

            $query->where(
                function (
                    EloquentBuilder $searchQuery
                ) use ($searchPattern): void {
                    $searchQuery
                        ->whereLike('number', $searchPattern)
                        ->orWhereHas(
                            'supplier',
                            fn (
                                EloquentBuilder $supplierQuery
                            ): EloquentBuilder => $supplierQuery->whereLike(
                                'name',
                                $searchPattern,
                            ),
                        )
                        ->orWhereHas(
                            'lines',
                            function (
                                EloquentBuilder $lineQuery
                            ) use ($searchPattern): void {
                                $lineQuery->where(
                                    function (
                                        EloquentBuilder $lineSearchQuery
                                    ) use ($searchPattern): void {
                                        $lineSearchQuery
                                            ->whereLike(
                                                'item_name_snapshot',
                                                $searchPattern,
                                            )
                                            ->orWhereLike(
                                                'supplier_sku_snapshot',
                                                $searchPattern,
                                            );
                                    },
                                );
                            },
                        );
                },
            );
        }

        if ($receiptState !== null) {
            $query->whereHas(
                'lines',
                function (
                    EloquentBuilder $lineQuery
                ) use ($receiptState): void {
                    $this->applyReceiptStateConstraint(
                        $lineQuery,
                        $receiptState,
                    );
                },
            );
        }

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        return [
            [
                'supplierId' => $supplierId,
                'locationId' => $locationId,
                'from' => $from,
                'to' => $to,
                'search' => $search,
                'receiptState' => $receiptState,
            ],
            $query,
            $canViewCosts,
        ];
    }

    /**
     * Apply the persisted ordered-versus-received receipt-state rules.
     *
     * @param  EloquentBuilder<PurchaseOrderLine>  $query
     */
    private function applyReceiptStateConstraint(
        EloquentBuilder $query,
        string $receiptState,
    ): void {
        switch ($receiptState) {
            case 'received':
                $query->whereColumn(
                    'received_base_quantity',
                    '=',
                    'base_quantity',
                );

                return;

            case 'partial':
                $query
                    ->where('received_base_quantity', '>', 0)
                    ->whereColumn(
                        'received_base_quantity',
                        '<',
                        'base_quantity',
                    );

                return;

            case 'not_received':
                $query->where('received_base_quantity', '<=', 0);

                return;

            case 'over_received':
                $query->whereColumn(
                    'received_base_quantity',
                    '>',
                    'base_quantity',
                );

                return;
        }
    }

    /**
     * Convert filtered purchase orders into the exact line-level report result.
     *
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     * @param  array{
     *     supplierId: int|null,
     *     locationId: int|null,
     *     from: string|null,
     *     to: string|null,
     *     search: string|null,
     *     receiptState: string|null
     * }  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(
        Collection $purchaseOrders,
        array $filters,
        bool $canViewCosts,
    ): array {
        $rows = [];

        foreach ($purchaseOrders as $purchaseOrder) {
            foreach ($purchaseOrder->lines as $line) {
                $row = $this->lineData(
                    $purchaseOrder,
                    $line,
                    $canViewCosts,
                );

                if ($this->rowMatchesFilters($row, $filters)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * Keep line-level search and receipt-state filtering identical for UI/export.
     *
     * @param  array<string, mixed>  $row
     * @param  array{
     *     supplierId: int|null,
     *     locationId: int|null,
     *     from: string|null,
     *     to: string|null,
     *     search: string|null,
     *     receiptState: string|null
     * }  $filters
     */
    private function rowMatchesFilters(array $row, array $filters): bool
    {
        if (
            $filters['receiptState'] !== null
            && ($row['receiptState'] ?? null) !== $filters['receiptState']
        ) {
            return false;
        }

        if ($filters['search'] === null) {
            return true;
        }

        foreach (
            [
                'purchaseOrderNumber',
                'supplierName',
                'itemName',
                'supplierSku',
            ] as $field
        ) {
            $value = $row[$field] ?? null;

            if (
                is_string($value)
                && stripos($value, $filters['search']) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Produce filter-aware purchase-order counts and decimal-safe spend totals.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     totalPurchaseOrders: int,
     *     fullyReceivedCount: int,
     *     partialReceiptCount: int,
     *     totalSpend: string|null
     * }
     */
    private function summaryData(array $rows, bool $canViewCosts): array
    {
        /** @var array<int, string> $purchaseOrderStatuses */
        $purchaseOrderStatuses = [];

        $totalSpend = BigDecimal::zero();

        foreach ($rows as $row) {
            $purchaseOrderId = $row['purchaseOrderId'] ?? null;
            $purchaseOrderStatus = $row['purchaseOrderStatus'] ?? null;

            if (
                is_int($purchaseOrderId)
                && is_string($purchaseOrderStatus)
            ) {
                $purchaseOrderStatuses[$purchaseOrderId] =
                    $purchaseOrderStatus;
            }

            $lineTotal = $row['lineTotal'] ?? null;

            if ($canViewCosts && is_string($lineTotal)) {
                $totalSpend = $totalSpend->plus($lineTotal);
            }
        }

        return [
            'totalPurchaseOrders' => count($purchaseOrderStatuses),
            'fullyReceivedCount' => count(
                array_filter(
                    $purchaseOrderStatuses,
                    static fn (string $status): bool => $status
                        === PurchaseOrderStatus::Received->value,
                ),
            ),
            'partialReceiptCount' => count(
                array_filter(
                    $purchaseOrderStatuses,
                    static fn (string $status): bool => $status
                        === PurchaseOrderStatus::PartiallyReceived->value,
                ),
            ),
            'totalSpend' => $canViewCosts
                ? (string) $totalSpend
                : null,
        ];
    }

    /**
     * Build one immutable historical PO-line representation for reporting.
     *
     * @return array<string, mixed>
     */
    private function lineData(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderLine $line,
        bool $canViewCosts,
    ): array {
        $baseQuantity = BigDecimal::of($line->base_quantity);
        $receivedBaseQuantity = BigDecimal::of(
            $line->received_base_quantity,
        );

        $remainingBaseQuantity = $baseQuantity->compareTo(
            $receivedBaseQuantity,
        ) > 0
            ? $baseQuantity->minus($receivedBaseQuantity)
            : BigDecimal::of('0.000000');

        $overReceivedBaseQuantity = $receivedBaseQuantity->compareTo(
            $baseQuantity,
        ) > 0
            ? $receivedBaseQuantity->minus($baseQuantity)
            : BigDecimal::of('0.000000');

        return [
            'id' => $line->id,
            'purchaseOrderId' => $purchaseOrder->id,
            'purchaseOrderNumber' => $purchaseOrder->number,
            'purchaseOrderStatus' => $purchaseOrder->status->value,
            'orderDate' => $purchaseOrder->order_date->toDateString(),
            'supplierId' => $purchaseOrder->supplier_id,
            'supplierName' => $purchaseOrder->supplier->name,
            'locationId' => $purchaseOrder->location_id,
            'locationName' => $purchaseOrder->location->name,
            'itemId' => $line->inventory_item_id,
            'itemName' => $line->item_name_snapshot,
            'supplierSku' => $line->supplier_sku_snapshot,
            'orderedQuantity' => $line->ordered_quantity,
            'purchaseUnitSymbol' => $line->purchaseUnitOfMeasure->symbol,
            'baseQuantity' => $line->base_quantity,
            'baseUnitSymbol' => $line
                ->inventoryItem
                ->baseUnitOfMeasure
                ->symbol,
            'receivedBaseQuantity' => $line->received_base_quantity,
            'remainingBaseQuantity' => (string) $remainingBaseQuantity,
            'overReceivedBaseQuantity' => (string) $overReceivedBaseQuantity,
            'receiptState' => $this->receiptState(
                $baseQuantity,
                $receivedBaseQuantity,
            ),
            'unitPrice' => $canViewCosts
                ? $line->unit_price
                : null,
            'lineTotal' => $canViewCosts
                ? $line->line_total
                : null,
        ];
    }

    /**
     * Derive receipt state from authoritative base-unit quantities.
     */
    private function receiptState(
        BigDecimal $baseQuantity,
        BigDecimal $receivedBaseQuantity,
    ): string {
        if ($receivedBaseQuantity->compareTo(BigDecimal::zero()) <= 0) {
            return 'not_received';
        }

        if ($receivedBaseQuantity->compareTo($baseQuantity) < 0) {
            return 'partial';
        }

        if ($receivedBaseQuantity->compareTo($baseQuantity) > 0) {
            return 'over_received';
        }

        return 'received';
    }

    /**
     * Return tenant-scoped supplier filter options.
     *
     * @return list<array{id: int, name: string}>
     */
    private function supplierOptions(Organization $organization): array
    {
        return array_values(
            Supplier::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name'])
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
     * Return tenant-scoped location filter options.
     *
     * @return list<array{id: int, name: string}>
     */
    private function locationOptions(Organization $organization): array
    {
        return array_values(
            Location::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name'])
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
     * Resolve the request's trusted active organization context.
     */
    private function activeOrganization(Request $request): Organization
    {
        $organization = $request->attributes->get(
            'activeOrganization',
        );

        if (! $organization instanceof Organization) {
            abort(403);
        }

        return $organization;
    }
}
