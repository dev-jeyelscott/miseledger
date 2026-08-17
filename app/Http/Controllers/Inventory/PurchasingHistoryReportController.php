<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\OrganizationPermission;
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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchasingHistoryReportController extends Controller
{
    /**
     * Report purchase orders and receiving history, scoped to the active
     * tenant, retaining ordered-versus-received quantities, historical
     * prices, and partial-receipt state at the PO-line level.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        [$filters, $query] = $this->filteredQuery(
            $request,
            $organization,
        );

        $purchaseOrders = $query
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();

        $rows = $purchaseOrders
            ->flatMap(
                fn (PurchaseOrder $purchaseOrder): array => $purchaseOrder
                    ->lines
                    ->map(
                        fn (PurchaseOrderLine $line): array => $this->lineData(
                            $purchaseOrder,
                            $line,
                        ),
                    )
                    ->all(),
            )
            ->values()
            ->all();

        return Inertia::render('inventory/purchasing-history', [
            'rows' => $rows,
            'supplierOptions' => $this->supplierOptions($organization),
            'locationOptions' => $this->locationOptions($organization),
            'filters' => $filters,
            'currency' => $organization->currency,
        ]);
    }

    /**
     * Stream the same permission- and tenant-scoped rows as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        [, $query] = $this->filteredQuery(
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
            'Unit Price',
            'Line Total',
        ];

        $rows = (function () use ($query): iterable {
            foreach (
                $query
                    ->orderByDesc('order_date')
                    ->orderByDesc('id')
                    ->cursor() as $purchaseOrder
            ) {
                foreach ($purchaseOrder->lines as $line) {
                    $data = $this->lineData($purchaseOrder, $line);

                    yield [
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
                        $data['unitPrice'],
                        $data['lineTotal'],
                    ];
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
     * Build the shared tenant-scoped, filtered query behind every rendering
     * of the Purchasing History report.
     *
     * @return array{0: array{supplierId: int|null, locationId: int|null, from: string|null, to: string|null}, 1: EloquentBuilder<PurchaseOrder>}
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

        return [
            [
                'supplierId' => $supplierId,
                'locationId' => $locationId,
                'from' => $from,
                'to' => $to,
            ],
            $query,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineData(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderLine $line,
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
            'unitPrice' => $line->unit_price,
            'lineTotal' => $line->line_total,
        ];
    }

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
