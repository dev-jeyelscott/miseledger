<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\ApprovePurchaseOrder;
use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Actions\Purchasing\SavePurchaseOrder;
use App\Enums\OrganizationPermission;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    /**
     * List tenant-scoped purchase orders.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $purchaseOrders = PurchaseOrder::query()
            ->with([
                'supplier:id,name',
                'location:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get()
            ->map(
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
                    'total' => $purchaseOrder->total,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('purchase-orders/index', [
            'purchaseOrders' => $purchaseOrders,
            'currency' => $organization->currency,
            'canManage' => Gate::allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            ),
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
            ->where('organization_id', $organization->id)
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
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Supplier $supplier) use ($supplierItems): array {
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
                                'supplierSku' => $supplierItem->supplier_sku,
                                'itemName' => $supplierItem
                                    ->inventoryItem
                                    ->name,
                                'purchaseUnit' => $supplierItem
                                    ->purchaseUnitOfMeasure
                                    ->symbol,
                                'baseQuantity' => $supplierItem->base_quantity,
                                'currentPrice' => $supplierItem->current_price,
                            ],
                        )
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $locations = Location::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
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
            'orderDate' => $purchaseOrder->order_date->toDateString(),
            'expectedDeliveryDate' => $purchaseOrder
                ->expected_delivery_date
                ?->toDateString(),
            'subtotal' => $purchaseOrder->subtotal,
            'taxTotal' => $purchaseOrder->tax_total,
            'discountTotal' => $purchaseOrder->discount_total,
            'total' => $purchaseOrder->total,
            'notes' => $purchaseOrder->notes,
            'approvedAt' => $purchaseOrder->approved_at
                ?->toIso8601String(),
            'lines' => $purchaseOrder
                ->lines
                ->map(
                    static fn (
                        PurchaseOrderLine $line,
                    ): array => [
                        'id' => $line->id,
                        'supplierItemId' => $line->supplier_item_id,
                        'itemName' => $line->item_name_snapshot,
                        'supplierSku' => $line->supplier_sku_snapshot,
                        'orderedQuantity' => $line->ordered_quantity,
                        'purchaseUnit' => [
                            'id' => $line->purchaseUnitOfMeasure->id,
                            'symbol' => $line->purchaseUnitOfMeasure->symbol,
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
