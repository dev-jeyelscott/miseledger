<?php

namespace App\Http\Controllers\Mobile;

use App\Actions\Purchasing\CreateAdHocPurchaseOrder;
use App\Actions\Purchasing\FinalizeGoodsReceipt;
use App\Actions\Purchasing\SaveGoodsReceipt;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\Mobile\AddReceivingLineRequest;
use App\Http\Requests\Mobile\StartAdHocReceivingRequest;
use App\Http\Requests\Purchasing\GoodsReceiptTransitionRequest;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin mobile entry point over the desktop `GoodsReceipt` draft/finalize
 * workflow (Spec 3). Every mutation reuses `SaveGoodsReceipt` and
 * `FinalizeGoodsReceipt` unmodified; the only new business logic is
 * `CreateAdHocPurchaseOrder`, invoked here exactly like a real PO would be
 * picked on the PO-based path.
 */
class ReceivingController extends MobileController
{
    /**
     * List receivable POs for the active location, plus the ad-hoc entry point.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::ReceivingFinalize->value, $organization);

        $location = $this->requireActiveLocation($request);

        if ($location === null) {
            return Inertia::render('mobile/receiving/index', [
                'activeLocation' => null,
                'purchaseOrders' => [],
            ]);
        }

        $today = now($organization->timezone)->toDateString();

        $purchaseOrders = PurchaseOrder::query()
            ->with('supplier:id,name')
            ->where('organization_id', $organization->id)
            ->where('location_id', $location->id)
            ->whereIn('status', [
                PurchaseOrderStatus::Approved->value,
                PurchaseOrderStatus::PartiallyReceived->value,
            ])
            ->orderBy('expected_delivery_date')
            ->orderBy('id')
            ->get()
            ->map(static fn (PurchaseOrder $purchaseOrder): array => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'supplierName' => $purchaseOrder->supplier->name,
                'expectedDeliveryDate' => $purchaseOrder->expected_delivery_date?->toDateString(),
                'overdue' => $purchaseOrder->expected_delivery_date !== null
                    && $purchaseOrder->expected_delivery_date->toDateString() < $today,
            ])
            ->values()
            ->all();

        return Inertia::render('mobile/receiving/index', [
            'activeLocation' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    /**
     * Render the ad-hoc supplier picker (decision #36.A: a separate, explicit action).
     */
    public function createAdHoc(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::ReceivingFinalize->value, $organization);

        $suppliers = Supplier::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
            ])
            ->values()
            ->all();

        return Inertia::render('mobile/receiving/create', [
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Stash the chosen ad-hoc supplier and enter scan mode. No PO or
     * receipt exists yet: it is created lazily on the first scanned line.
     */
    public function storeAdHoc(StartAdHocReceivingRequest $request): RedirectResponse
    {
        $supplier = $request->supplier();

        abort_if($supplier === null, 404);

        return to_route('mobile.receiving.scan', ['supplier_id' => $supplier->id]);
    }

    /**
     * The persistent scan-to-add-line screen for both the PO-based and
     * ad-hoc paths (Spec 2's scanner, "receiving mode").
     */
    public function scan(Request $request): Response|RedirectResponse
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::ReceivingFinalize->value, $organization);

        $location = $this->requireActiveLocation($request);
        abort_if($location === null, 404);

        $goodsReceiptId = $request->integer('goods_receipt_id') ?: null;
        $purchaseOrderId = $request->integer('purchase_order_id') ?: null;
        $supplierId = $request->integer('supplier_id') ?: null;

        if ($goodsReceiptId !== null) {
            $receipt = GoodsReceipt::query()
                ->with('purchaseOrder:id,supplier_id,origin,location_id')
                ->where('organization_id', $organization->id)
                ->where('status', GoodsReceiptStatus::Draft->value)
                ->find($goodsReceiptId);

            abort_if($receipt === null, 404);

            return $this->renderScan(
                $organization,
                $location,
                mode: $receipt->purchaseOrder->origin === 'mobile_ad_hoc' ? 'ad_hoc' : 'purchase_order',
                purchaseOrderId: $receipt->purchase_order_id,
                supplierId: $receipt->purchaseOrder->supplier_id,
                goodsReceiptId: $receipt->id,
                lineCount: $receipt->lines()->count(),
            );
        }

        if ($purchaseOrderId !== null) {
            $purchaseOrder = PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->where('location_id', $location->id)
                ->find($purchaseOrderId);

            abort_if($purchaseOrder === null || ! $purchaseOrder->status->canReceive(), 404);

            $existingDraft = GoodsReceipt::query()
                ->where('organization_id', $organization->id)
                ->where('purchase_order_id', $purchaseOrder->id)
                ->where('status', GoodsReceiptStatus::Draft->value)
                ->latest('id')
                ->first();

            if ($existingDraft !== null) {
                return to_route('mobile.receiving.scan', ['goods_receipt_id' => $existingDraft->id]);
            }

            return $this->renderScan(
                $organization,
                $location,
                mode: 'purchase_order',
                purchaseOrderId: $purchaseOrder->id,
                supplierId: $purchaseOrder->supplier_id,
                goodsReceiptId: null,
                lineCount: 0,
            );
        }

        if ($supplierId !== null) {
            $supplier = Supplier::query()
                ->where('organization_id', $organization->id)
                ->where('active', true)
                ->find($supplierId);

            abort_if($supplier === null, 404);

            return $this->renderScan(
                $organization,
                $location,
                mode: 'ad_hoc',
                purchaseOrderId: null,
                supplierId: $supplier->id,
                goodsReceiptId: null,
                lineCount: 0,
            );
        }

        return to_route('mobile.receiving.index');
    }

    /**
     * Add one scanned line to the accumulating draft (decision #2: stays in
     * the live scanner afterward). Each call is its own `SaveGoodsReceipt`
     * call against the full accumulated line set, matching how desktop's
     * edit/update already re-saves the full line array.
     */
    public function addLine(
        AddReceivingLineRequest $request,
        CreateAdHocPurchaseOrder $createAdHocPurchaseOrder,
        SaveGoodsReceipt $saveGoodsReceipt,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();

        if ($organization === null || ! $actor instanceof User) {
            abort(403);
        }

        $location = $this->requireActiveLocation($request);
        abort_if($location === null, 404);

        $validated = $request->validated();

        $goodsReceipt = null;
        $purchaseOrder = null;

        if (! empty($validated['goods_receipt_id'])) {
            $goodsReceipt = GoodsReceipt::query()
                ->where('organization_id', $organization->id)
                ->where('status', GoodsReceiptStatus::Draft->value)
                ->find((int) $validated['goods_receipt_id']);

            abort_if($goodsReceipt === null, 404);

            $purchaseOrder = PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->find($goodsReceipt->purchase_order_id);

            abort_if($purchaseOrder === null, 404);
        } elseif (! empty($validated['purchase_order_id'])) {
            $purchaseOrder = PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->find((int) $validated['purchase_order_id']);

            abort_if($purchaseOrder === null || ! $purchaseOrder->status->canReceive(), 404);
        } elseif (! empty($validated['supplier_id'])) {
            $supplier = Supplier::query()
                ->where('organization_id', $organization->id)
                ->where('active', true)
                ->find((int) $validated['supplier_id']);

            abort_if($supplier === null, 404);

            $purchaseOrder = $createAdHocPurchaseOrder->handle(
                $organization,
                $actor,
                $supplier,
                $location,
                [[
                    'inventory_item_id' => (int) $validated['inventory_item_id'],
                    'unit_id' => (int) $validated['unit_id'],
                    'quantity' => (string) $validated['quantity'],
                ]],
            );
        } else {
            throw ValidationException::withMessages([
                'purchase_order_id' => __('Select a purchase order or start an ad-hoc receipt first.'),
            ]);
        }

        $poLine = $this->resolvePurchaseOrderLine(
            $purchaseOrder,
            (int) $validated['inventory_item_id'],
            (int) $validated['unit_id'],
        );

        if ($poLine === null && $purchaseOrder->origin === 'mobile_ad_hoc') {
            $purchaseOrder = $createAdHocPurchaseOrder->handle(
                $organization,
                $actor,
                $purchaseOrder->supplier,
                $location,
                [[
                    'inventory_item_id' => (int) $validated['inventory_item_id'],
                    'unit_id' => (int) $validated['unit_id'],
                    'quantity' => (string) $validated['quantity'],
                ]],
                $purchaseOrder,
            );

            $poLine = $this->resolvePurchaseOrderLine(
                $purchaseOrder,
                (int) $validated['inventory_item_id'],
                (int) $validated['unit_id'],
            );
        }

        if ($poLine === null) {
            throw ValidationException::withMessages([
                'inventory_item_id' => __('This item is not on the selected purchase order.'),
            ]);
        }

        $storageLocation = $this->defaultStorageLocation($organization, $purchaseOrder->location_id);

        $lines = $this->existingLineInput($goodsReceipt);
        $lines[] = [
            'purchase_order_line_id' => $poLine->id,
            'storage_location_id' => $storageLocation->id,
            'received_quantity' => (string) $validated['quantity'],
            'received_unit_of_measure_id' => (int) $validated['unit_id'],
            'notes' => null,
        ];

        $receipt = $saveGoodsReceipt->handle(
            $organization,
            $actor,
            $purchaseOrder,
            [
                'number' => $goodsReceipt !== null
                    ? $goodsReceipt->number
                    : $this->uniqueReceiptNumber($organization),
                'supplier_reference' => $goodsReceipt?->supplier_reference,
                'notes' => $goodsReceipt?->notes,
                'lines' => $lines,
            ],
            $goodsReceipt,
        );

        return to_route('mobile.receiving.scan', ['goods_receipt_id' => $receipt->id]);
    }

    /**
     * Full line list, totals, and the non-dismissible zero-cost warning.
     */
    public function review(Request $request, string $goodsReceipt): Response
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::ReceivingFinalize->value, $organization);

        $receipt = GoodsReceipt::query()
            ->with([
                'purchaseOrder:id,number,origin',
                'supplier:id,name',
                'lines.inventoryItem:id,name,sku',
                'lines.receivedUnitOfMeasure:id,name,symbol',
            ])
            ->where('organization_id', $organization->id)
            ->findOrFail($goodsReceipt);

        $canViewCosts = Gate::allows(OrganizationPermission::CostsView->value, $organization);

        $lines = $receipt->lines->map(static fn (GoodsReceiptLine $line): array => [
            'id' => $line->id,
            'itemName' => $line->inventoryItem->name,
            'quantity' => $line->received_quantity,
            'unitSymbol' => $line->receivedUnitOfMeasure->symbol,
            'unitCost' => $canViewCosts ? $line->unit_cost : null,
            'totalCost' => $canViewCosts ? $line->total_cost : null,
            'hasZeroCost' => BigDecimal::of($line->unit_cost)->isZero(),
        ])->values()->all();

        return Inertia::render('mobile/receiving/review', [
            'goodsReceipt' => [
                'id' => $receipt->id,
                'number' => $receipt->number,
                'status' => $receipt->status->value,
                'purchaseOrderNumber' => $receipt->purchaseOrder->number,
                'isAdHoc' => $receipt->purchaseOrder->origin === 'mobile_ad_hoc',
                'supplierName' => $receipt->supplier->name,
                'lines' => $lines,
                'hasZeroCostLine' => collect($lines)->contains('hasZeroCost', true),
            ],
            'canFinalize' => Gate::allows(OrganizationPermission::ReceivingFinalize->value, $organization),
            'canViewCosts' => $canViewCosts,
        ]);
    }

    /**
     * Remove one line from an in-progress draft by resubmitting the reduced
     * line set through `SaveGoodsReceipt`, which replaces the whole set.
     */
    public function removeLine(
        Request $request,
        string $goodsReceipt,
        string $line,
        SaveGoodsReceipt $saveGoodsReceipt,
    ): RedirectResponse {
        $organization = $this->activeOrganization($request);
        $actor = $request->user();

        if ($organization === null || ! $actor instanceof User) {
            abort(403);
        }

        Gate::authorize(OrganizationPermission::ReceivingFinalize->value, $organization);

        $receipt = GoodsReceipt::query()
            ->where('organization_id', $organization->id)
            ->where('status', GoodsReceiptStatus::Draft->value)
            ->findOrFail($goodsReceipt);

        $purchaseOrder = PurchaseOrder::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($receipt->purchase_order_id);

        $remainingLines = $receipt->lines()
            ->where('id', '!=', (int) $line)
            ->orderBy('id')
            ->get()
            ->map(static fn (GoodsReceiptLine $goodsReceiptLine): array => [
                'purchase_order_line_id' => $goodsReceiptLine->purchase_order_line_id,
                'storage_location_id' => $goodsReceiptLine->storage_location_id,
                'received_quantity' => $goodsReceiptLine->received_quantity,
                'received_unit_of_measure_id' => $goodsReceiptLine->received_unit_of_measure_id,
                'notes' => $goodsReceiptLine->notes,
            ])
            ->values()
            ->all();

        if ($remainingLines === []) {
            throw ValidationException::withMessages([
                'lines' => __('A receipt needs at least one line. Back out of receiving instead of removing the last item.'),
            ]);
        }

        $saveGoodsReceipt->handle(
            $organization,
            $actor,
            $purchaseOrder,
            [
                'number' => $receipt->number,
                'supplier_reference' => $receipt->supplier_reference,
                'notes' => $receipt->notes,
                'lines' => $remainingLines,
            ],
            $receipt,
        );

        return to_route('mobile.receiving.review', $receipt);
    }

    /**
     * Finalize receiving via the unmodified desktop action (decision #30.C:
     * `FinalizeGoodsReceipt` already returns the existing receipt untouched
     * when called again after finalization, giving server-side idempotency;
     * the client pairs this with a submit lock).
     */
    public function finalize(
        GoodsReceiptTransitionRequest $request,
        string $goodsReceipt,
        FinalizeGoodsReceipt $finalizeGoodsReceipt,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $receipt = $request->goodsReceipt();

        if ($organization === null || ! $actor instanceof User || $receipt === null) {
            abort(403);
        }

        $finalizeGoodsReceipt->handle($organization, $actor, $receipt);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Goods receipt finalized.'),
        ]);

        return to_route('mobile.receiving.index');
    }

    /**
     * Active units for one item, for the scan/qty screen's unit switcher.
     */
    public function itemUnits(Request $request, string $inventoryItem): JsonResponse
    {
        $organization = $this->activeOrganization($request);
        abort_if($organization === null, 404);

        Gate::authorize(OrganizationPermission::ReceivingFinalize->value, $organization);

        $item = InventoryItem::query()
            ->with([
                'baseUnitOfMeasure:id,name,symbol',
                'unitConversions' => fn ($query) => $query
                    ->where('active', true)
                    ->with('unitOfMeasure:id,name,symbol'),
            ])
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->findOrFail($inventoryItem);

        $units = collect([[
            'id' => $item->baseUnitOfMeasure->id,
            'name' => $item->baseUnitOfMeasure->name,
            'symbol' => $item->baseUnitOfMeasure->symbol,
            'isBase' => true,
        ]])->merge(
            $item->unitConversions->map(static fn ($conversion): array => [
                'id' => $conversion->unitOfMeasure->id,
                'name' => $conversion->unitOfMeasure->name,
                'symbol' => $conversion->unitOfMeasure->symbol,
                'isBase' => false,
            ]),
        )->unique('id')->values();

        return response()->json(['units' => $units]);
    }

    /**
     * Render the scan screen with the resolved session context.
     */
    private function renderScan(
        Organization $organization,
        Location $location,
        string $mode,
        ?int $purchaseOrderId,
        ?int $supplierId,
        ?int $goodsReceiptId,
        int $lineCount,
    ): Response {
        return Inertia::render('mobile/receiving/scan', [
            'activeLocation' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ],
            'context' => [
                'mode' => $mode,
                'purchaseOrderId' => $purchaseOrderId,
                'supplierId' => $supplierId,
                'goodsReceiptId' => $goodsReceiptId,
                'lineCount' => $lineCount,
            ],
        ]);
    }

    /**
     * Resolve one purchase-order line for a scanned item, preferring an
     * exact unit match (the ad-hoc case, where each scanned unit gets its
     * own line) and falling back to any line for that item (the PO-based
     * case, where the receiving unit may differ from the planned unit).
     */
    private function resolvePurchaseOrderLine(
        PurchaseOrder $purchaseOrder,
        int $inventoryItemId,
        int $unitId,
    ): ?PurchaseOrderLine {
        $exact = PurchaseOrderLine::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('inventory_item_id', $inventoryItemId)
            ->where('purchase_unit_of_measure_id', $unitId)
            ->orderBy('id')
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        if ($purchaseOrder->origin === 'mobile_ad_hoc') {
            return null;
        }

        return PurchaseOrderLine::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('inventory_item_id', $inventoryItemId)
            ->orderBy('id')
            ->first();
    }

    /**
     * Rebuild the accumulated `SaveGoodsReceipt` line input from a draft's
     * currently persisted lines.
     *
     * @return array<int, array<string, mixed>>
     */
    private function existingLineInput(?GoodsReceipt $goodsReceipt): array
    {
        if ($goodsReceipt === null) {
            return [];
        }

        return $goodsReceipt->lines()
            ->orderBy('id')
            ->get()
            ->map(static fn (GoodsReceiptLine $line): array => [
                'purchase_order_line_id' => $line->purchase_order_line_id,
                'storage_location_id' => $line->storage_location_id,
                'received_quantity' => $line->received_quantity,
                'received_unit_of_measure_id' => $line->received_unit_of_measure_id,
                'notes' => $line->notes,
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve the mobile location's one default storage destination.
     *
     * The mobile Scan → Qty → Next flow (Spec 3 §"Frontend / UI / UX") has
     * no storage-location picker step, so receiving lines land in the
     * location's first active storage location deterministically.
     */
    private function defaultStorageLocation(Organization $organization, int $locationId): StorageLocation
    {
        $storageLocation = StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $locationId)
            ->where('active', true)
            ->orderBy('name')
            ->first();

        if ($storageLocation === null) {
            throw ValidationException::withMessages([
                'storage_location' => __('No active storage location is configured for this location.'),
            ]);
        }

        return $storageLocation;
    }

    /**
     * Generate an organization-unique mobile receipt number.
     */
    private function uniqueReceiptNumber(Organization $organization): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = sprintf(
                'GR-MOB-%s-%s',
                now()->format('ymdHis'),
                Str::upper(Str::random(4)),
            );

            $exists = GoodsReceipt::query()
                ->where('organization_id', $organization->id)
                ->where('number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'number' => __('Unable to generate a unique receipt number. Try again.'),
        ]);
    }
}
