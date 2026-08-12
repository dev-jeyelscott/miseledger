<?php

namespace App\Http\Controllers\Suppliers;

use App\Actions\Suppliers\SaveSupplier;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SaveSupplierRequest;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    /**
     * List suppliers from the active organization only.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $suppliers = $organization
            ->suppliers()
            ->withCount([
                'supplierItems as item_count',
            ])
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (Supplier $supplier): array => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'code' => $supplier->code,
                    'contactName' => $supplier->contact_name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'itemCount' => $supplier->item_count ?? 0,
                    'active' => $supplier->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,
            'canManage' => Gate::allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            ),
        ]);
    }

    /**
     * Show supplier creation.
     */
    public function create(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingManage->value,
            $organization,
        );

        return Inertia::render('suppliers/create');
    }

    /**
     * Persist a supplier.
     */
    public function store(
        SaveSupplierRequest $request,
        SaveSupplier $saveSupplier,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        $supplier = $saveSupplier->handle(
            $organization,
            $this->supplierAttributes($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Supplier created.'),
        ]);

        return to_route('suppliers.edit', $supplier);
    }

    /**
     * Show supplier master data and its purchase-pack mappings.
     */
    public function edit(
        Request $request,
        string $supplier,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $supplierRecord = $organization
            ->suppliers()
            ->with([
                'supplierItems' => fn ($query) => $query
                    ->with([
                        'inventoryItem:id,name,sku',
                        'purchaseUnitOfMeasure:id,name,symbol',
                    ])
                    ->orderByDesc('active')
                    ->orderBy('supplier_sku'),
            ])
            ->findOrFail($supplier);

        $itemOptions = $organization
            ->inventoryItems()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(
                static fn (InventoryItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                ],
            )
            ->values()
            ->all();

        $unitOptions = $organization
            ->unitsOfMeasure()
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

        return Inertia::render('suppliers/edit', [
            'supplier' => [
                'id' => $supplierRecord->id,
                'name' => $supplierRecord->name,
                'code' => $supplierRecord->code,
                'contactName' => $supplierRecord->contact_name,
                'email' => $supplierRecord->email,
                'phone' => $supplierRecord->phone,
                'paymentTerms' => $supplierRecord->payment_terms,
                'leadTimeDays' => $supplierRecord->lead_time_days,
                'active' => $supplierRecord->active,

                'items' => $supplierRecord
                    ->supplierItems
                    ->map(
                        static fn (
                            SupplierItem $supplierItem,
                        ): array => [
                            'id' => $supplierItem->id,
                            'supplierSku' => $supplierItem->supplier_sku,
                            'description' => $supplierItem->description,
                            'baseQuantity' => $supplierItem->base_quantity,
                            'currentPrice' => $supplierItem->current_price,
                            'currency' => $supplierItem->currency,
                            'active' => $supplierItem->active,

                            'inventoryItem' => [
                                'id' => $supplierItem->inventoryItem->id,
                                'name' => $supplierItem->inventoryItem->name,
                                'sku' => $supplierItem->inventoryItem->sku,
                            ],

                            'purchaseUnit' => [
                                'id' => $supplierItem
                                    ->purchaseUnitOfMeasure
                                    ->id,
                                'name' => $supplierItem
                                    ->purchaseUnitOfMeasure
                                    ->name,
                                'symbol' => $supplierItem
                                    ->purchaseUnitOfMeasure
                                    ->symbol,
                            ],
                        ],
                    )
                    ->values()
                    ->all(),
            ],

            'itemOptions' => $itemOptions,
            'unitOptions' => $unitOptions,

            'canManage' => Gate::allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            ),
        ]);
    }

    /**
     * Update supplier master data without deleting history.
     */
    public function update(
        SaveSupplierRequest $request,
        string $supplier,
        SaveSupplier $saveSupplier,
    ): RedirectResponse {
        $organization = $request->organization();
        $supplierRecord = $request->supplier();

        if (
            $organization === null
            || $supplierRecord === null
        ) {
            abort(403);
        }

        $saveSupplier->handle(
            $organization,
            $this->supplierAttributes($request),
            $supplierRecord,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Supplier updated.'),
        ]);

        return to_route('suppliers.edit', $supplier);
    }

    /**
     * Extract normalized supplier attributes from validated input.
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     contact_name: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     payment_terms: string|null,
     *     lead_time_days: int|null,
     *     active: bool
     * }
     */
    private function supplierAttributes(
        SaveSupplierRequest $request,
    ): array {
        $leadTimeDays = $request->validated('lead_time_days');

        return [
            'name' => (string) $request->validated('name'),
            'code' => (string) $request->validated('code'),

            'contact_name' => $request->validated('contact_name') !== null
                ? (string) $request->validated('contact_name')
                : null,

            'email' => $request->validated('email') !== null
                ? (string) $request->validated('email')
                : null,

            'phone' => $request->validated('phone') !== null
                ? (string) $request->validated('phone')
                : null,

            'payment_terms' => $request->validated('payment_terms') !== null
                ? (string) $request->validated('payment_terms')
                : null,

            'lead_time_days' => $leadTimeDays !== null
                ? (int) $leadTimeDays
                : null,

            'active' => (bool) $request->validated('active'),
        ];
    }

    /**
     * Return the organization resolved by tenancy middleware.
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
