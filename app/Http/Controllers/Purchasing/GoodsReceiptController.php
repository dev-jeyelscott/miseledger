<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\CancelGoodsReceipt;
use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\GoodsReceiptTransitionRequest;
use App\Http\Requests\Purchasing\SaveGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceiptController extends Controller
{
    /**
     * List receiving history for the active tenant.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $receipts = GoodsReceipt::query()
            ->with([
                'purchaseOrder:id,number',
                'supplier:id,name',
                'location:id,name',
                'receiver:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->orderByDesc('id')
            ->get()
            ->map(
                static fn (GoodsReceipt $receipt): array => [
                    'id' => $receipt->id,
                    'number' => $receipt->number,
                    'status' => $receipt->status->value,
                    'purchaseOrderNumber' => $receipt
                        ->purchaseOrder
                        ->number,
                    'supplierName' => $receipt->supplier->name,
                    'locationName' => $receipt->location->name,
                    'receivedAt' => $receipt->received_at
                        ?->toIso8601String(),
                    'receivedBy' => $receipt->receiver?->name,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('goods-receipts/index', [
            'receipts' => $receipts,
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
                'lines.movement',
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
     * Serialize receipt history and movement traceability.
     *
     * @return array<string, mixed>
     */
    private function receiptData(
        GoodsReceipt $receipt,
    ): array {
        return [
            'id' => $receipt->id,
            'number' => $receipt->number,
            'status' => $receipt->status->value,
            'supplierReference' => $receipt->supplier_reference,
            'notes' => $receipt->notes,
            'receivedAt' => $receipt->received_at
                ?->toIso8601String(),
            'receivedBy' => $receipt->receiver?->name,
            'lines' => $receipt
                ->lines
                ->map(
                    static fn (
                        GoodsReceiptLine $line,
                    ): array => [
                        'id' => $line->id,
                        'purchaseOrderLineId' => $line
                            ->purchase_order_line_id,
                        'itemName' => $line->inventoryItem->name,
                        'storageLocationId' => $line
                            ->storage_location_id,
                        'storageLocationName' => $line
                            ->storageLocation
                            ->name,
                        'receivedQuantity' => $line->received_quantity,
                        'receivedUnitId' => $line
                            ->received_unit_of_measure_id,
                        'receivedUnitSymbol' => $line
                            ->receivedUnitOfMeasure
                            ->symbol,
                        'baseQuantity' => $line->base_quantity,
                        'unitCost' => $line->unit_cost,
                        'totalCost' => $line->total_cost,
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
                            ],
                    ],
                )
                ->values()
                ->all(),
        ];
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
