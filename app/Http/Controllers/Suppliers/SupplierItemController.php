<?php

namespace App\Http\Controllers\Suppliers;

use App\Actions\Suppliers\RecordSupplierItemPrice;
use App\Actions\Suppliers\SaveSupplierItem;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SaveSupplierItemRequest;
use App\Http\Requests\Suppliers\StoreSupplierItemPriceRequest;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\SupplierItemPrice;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierItemController extends Controller
{
    /**
     * Create a supplier-specific inventory mapping and optional initial price.
     */
    public function store(
        SaveSupplierItemRequest $request,
        string $supplier,
        SaveSupplierItem $saveSupplierItem,
        RecordSupplierItemPrice $recordSupplierItemPrice,
    ): RedirectResponse {
        $organization = $request->organization();
        $supplierRecord = $request->supplier();

        if (
            $organization === null
            || $supplierRecord === null
        ) {
            abort(403);
        }

        DB::transaction(function () use (
            $request,
            $organization,
            $supplierRecord,
            $saveSupplierItem,
            $recordSupplierItemPrice,
        ): void {
            $supplierItem = $saveSupplierItem->handle(
                $organization,
                $supplierRecord,
                $this->supplierItemAttributes($request),
            );

            $price = $request->validated('price');

            if ($price !== null) {
                $recordSupplierItemPrice->handle(
                    $organization,
                    $supplierItem,
                    (string) $price,
                );
            }
        }, 3);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Supplier item created.'),
        ]);

        return to_route('suppliers.edit', $supplier);
    }

    /**
     * Show a supplier mapping and its immutable price history.
     */
    public function edit(
        Request $request,
        string $supplier,
        string $supplierItem,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $supplierRecord = $organization
            ->suppliers()
            ->findOrFail($supplier);

        $supplierItemRecord = SupplierItem::query()
            ->where('organization_id', $organization->id)
            ->where('supplier_id', $supplierRecord->id)
            ->with([
                'inventoryItem:id,name,sku,active',
                'purchaseUnitOfMeasure:id,name,symbol,active',
                'prices' => fn ($query) => $query
                    ->mostRecentFirst(),
            ])
            ->findOrFail($supplierItem);

        $itemOptions = $organization
            ->inventoryItems()
            ->where(function ($query) use (
                $supplierItemRecord,
            ): void {
                $query
                    ->where('active', true)
                    ->orWhere(
                        'id',
                        $supplierItemRecord->inventory_item_id,
                    );
            })
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'active'])
            ->map(
                static fn (InventoryItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'active' => $item->active,
                ],
            )
            ->values()
            ->all();

        $unitOptions = $organization
            ->unitsOfMeasure()
            ->where(function ($query) use (
                $supplierItemRecord,
            ): void {
                $query
                    ->where('active', true)
                    ->orWhere(
                        'id',
                        $supplierItemRecord
                            ->purchase_unit_of_measure_id,
                    );
            })
            ->orderBy('name')
            ->get(['id', 'name', 'symbol', 'active'])
            ->map(
                static fn (UnitOfMeasure $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'active' => $unit->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('suppliers/items/edit', [
            'supplier' => [
                'id' => $supplierRecord->id,
                'name' => $supplierRecord->name,
                'code' => $supplierRecord->code,
                'active' => $supplierRecord->active,
            ],

            'supplierItem' => [
                'id' => $supplierItemRecord->id,
                'supplierSku' => $supplierItemRecord->supplier_sku,
                'description' => $supplierItemRecord->description,
                'baseQuantity' => $supplierItemRecord->base_quantity,
                'currentPrice' => $supplierItemRecord->current_price,
                'currency' => $supplierItemRecord->currency,
                'active' => $supplierItemRecord->active,

                'inventoryItem' => [
                    'id' => $supplierItemRecord->inventoryItem->id,
                    'name' => $supplierItemRecord->inventoryItem->name,
                    'sku' => $supplierItemRecord->inventoryItem->sku,
                    'active' => $supplierItemRecord->inventoryItem->active,
                ],

                'purchaseUnit' => [
                    'id' => $supplierItemRecord
                        ->purchaseUnitOfMeasure
                        ->id,
                    'name' => $supplierItemRecord
                        ->purchaseUnitOfMeasure
                        ->name,
                    'symbol' => $supplierItemRecord
                        ->purchaseUnitOfMeasure
                        ->symbol,
                    'active' => $supplierItemRecord
                        ->purchaseUnitOfMeasure
                        ->active,
                ],

                'prices' => $supplierItemRecord
                    ->prices
                    ->map(
                        static fn (
                            SupplierItemPrice $price,
                        ): array => [
                            'id' => $price->id,
                            'price' => $price->price,
                            'currency' => $price->currency,
                            'effectiveAt' => $price
                                ->effective_at
                                ->toISOString(),
                        ],
                    )
                    ->values()
                    ->all(),
            ],

            'itemOptions' => $itemOptions,
            'unitOptions' => $unitOptions,
            'timezone' => $organization->timezone,

            'canManage' => Gate::allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            ),
        ]);
    }

    /**
     * Update a supplier mapping without mutating price history.
     */
    public function update(
        SaveSupplierItemRequest $request,
        string $supplier,
        string $supplierItem,
        SaveSupplierItem $saveSupplierItem,
    ): RedirectResponse {
        $organization = $request->organization();
        $supplierRecord = $request->supplier();
        $supplierItemRecord = $request->supplierItem();

        if (
            $organization === null
            || $supplierRecord === null
            || $supplierItemRecord === null
        ) {
            abort(403);
        }

        $saveSupplierItem->handle(
            $organization,
            $supplierRecord,
            $this->supplierItemAttributes($request),
            $supplierItemRecord,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Supplier item updated.'),
        ]);

        return to_route(
            'suppliers.items.edit',
            [$supplier, $supplierItem],
        );
    }

    /**
     * Append a new supplier price instead of overwriting history.
     */
    public function storePrice(
        StoreSupplierItemPriceRequest $request,
        string $supplier,
        string $supplierItem,
        RecordSupplierItemPrice $recordSupplierItemPrice,
    ): RedirectResponse {
        $organization = $request->organization();
        $supplierItemRecord = $request->supplierItem();

        if (
            $organization === null
            || $supplierItemRecord === null
        ) {
            abort(403);
        }

        $recordSupplierItemPrice->handle(
            $organization,
            $supplierItemRecord,
            (string) $request->validated('price'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Supplier price recorded.'),
        ]);

        return to_route(
            'suppliers.items.edit',
            [$supplier, $supplierItem],
        );
    }

    /**
     * Extract normalized supplier-item attributes.
     *
     * @return array{
     *     inventory_item_id: int,
     *     supplier_sku: string,
     *     description: string|null,
     *     purchase_unit_of_measure_id: int,
     *     base_quantity: string,
     *     active: bool
     * }
     */
    private function supplierItemAttributes(
        SaveSupplierItemRequest $request,
    ): array {
        return [
            'inventory_item_id' => (int) $request->validated(
                'inventory_item_id',
            ),

            'supplier_sku' => (string) $request->validated(
                'supplier_sku',
            ),

            'description' => $request->validated('description') !== null
                ? (string) $request->validated('description')
                : null,

            'purchase_unit_of_measure_id' => (int) $request->validated(
                'purchase_unit_of_measure_id',
            ),

            'base_quantity' => (string) $request->validated(
                'base_quantity',
            ),

            'active' => (bool) $request->validated('active'),
        ];
    }

    /**
     * Return the active organization resolved by tenancy middleware.
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
