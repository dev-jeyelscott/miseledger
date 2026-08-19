<?php

namespace App\Http\Controllers\Suppliers;

use App\Actions\Suppliers\SaveSupplier;
use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SaveSupplierRequest;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    /**
     * List the active organization's suppliers with bounded operational filtering.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::PurchasingView->value,
            $organization,
        );

        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'name_asc',
                    'name_desc',
                    'code_asc',
                    'code_desc',
                    'items_desc',
                ]),
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in([
                    10,
                    25,
                    50,
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

        $sort = isset($validated['sort'])
            && is_string($validated['sort'])
                ? $validated['sort']
                : 'name_asc';

        $perPage = isset($validated['per_page'])
            ? (int) $validated['per_page']
            : 10;

        $query = Supplier::query()
            ->select('suppliers.*')
            ->where(
                'organization_id',
                $organization->id,
            )
            ->addSelect([
                'last_purchase_order_number' => PurchaseOrder::query()
                    ->select('number')
                    ->whereColumn(
                        'purchase_orders.supplier_id',
                        'suppliers.id',
                    )
                    ->whereColumn(
                        'purchase_orders.organization_id',
                        'suppliers.organization_id',
                    )
                    ->orderByDesc('order_date')
                    ->orderByDesc('id')
                    ->limit(1),

                'last_purchase_order_date' => PurchaseOrder::query()
                    ->select('order_date')
                    ->whereColumn(
                        'purchase_orders.supplier_id',
                        'suppliers.id',
                    )
                    ->whereColumn(
                        'purchase_orders.organization_id',
                        'suppliers.organization_id',
                    )
                    ->orderByDesc('order_date')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->withCount([
                'supplierItems as item_count',
            ]);

        if ($search !== null) {
            $searchPattern = "%{$search}%";

            $query->where(
                function (
                    EloquentBuilder $searchQuery,
                ) use ($searchPattern): void {
                    $searchQuery
                        ->whereLike(
                            'name',
                            $searchPattern,
                        )
                        ->orWhereLike(
                            'code',
                            $searchPattern,
                        )
                        ->orWhereLike(
                            'contact_name',
                            $searchPattern,
                        )
                        ->orWhereLike(
                            'email',
                            $searchPattern,
                        )
                        ->orWhereLike(
                            'phone',
                            $searchPattern,
                        );
                },
            );
        }

        if ($status !== null) {
            $query->where(
                'active',
                $status === 'active',
            );
        }

        switch ($sort) {
            case 'name_desc':
                $query
                    ->orderByDesc('name')
                    ->orderByDesc('id');

                break;

            case 'code_asc':
                $query
                    ->orderBy('code')
                    ->orderBy('id');

                break;

            case 'code_desc':
                $query
                    ->orderByDesc('code')
                    ->orderByDesc('id');

                break;

            case 'items_desc':
                $query
                    ->orderByDesc('item_count')
                    ->orderBy('name')
                    ->orderBy('id');

                break;

            case 'name_asc':
            default:
                $query
                    ->orderBy('name')
                    ->orderBy('id');

                break;
        }

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString();

        $suppliers = collect($paginator->items())
            ->map(
                static function (Supplier $supplier): array {
                    $lastPurchaseOrderNumber = $supplier->getAttribute(
                        'last_purchase_order_number',
                    );

                    $lastPurchaseOrderDate = $supplier->getAttribute(
                        'last_purchase_order_date',
                    );

                    return [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'code' => $supplier->code,
                        'contactName' => $supplier->contact_name,
                        'email' => $supplier->email,
                        'phone' => $supplier->phone,
                        'paymentTerms' => $supplier->payment_terms,
                        'leadTimeDays' => $supplier->lead_time_days,
                        'itemCount' => (int) ($supplier->item_count ?? 0),
                        'active' => $supplier->active,

                        'lastPurchaseOrderNumber' => $lastPurchaseOrderNumber !== null
                                ? (string) $lastPurchaseOrderNumber
                                : null,

                        'lastPurchaseOrderDate' => $lastPurchaseOrderDate !== null
                                ? (string) $lastPurchaseOrderDate
                                : null,
                    ];
                },
            )
            ->values()
            ->all();

        $pageLinks = [];
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();

        if ($lastPage > 1) {
            $pageStart = max(
                1,
                $currentPage - 2,
            );

            $pageEnd = min(
                $lastPage,
                $currentPage + 2,
            );

            foreach (
                $paginator->getUrlRange(
                    $pageStart,
                    $pageEnd,
                ) as $page => $url
            ) {
                $pageLinks[] = [
                    'page' => $page,
                    'url' => $url,
                    'active' => $page === $currentPage,
                ];
            }
        }

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,

            'summary' => [
                'totalSuppliers' => Supplier::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->count(),

                'activeSuppliers' => Supplier::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where('active', true)
                    ->count(),

                'linkedItems' => SupplierItem::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->count(),

                'openPurchaseOrders' => PurchaseOrder::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereIn(
                        'status',
                        [
                            PurchaseOrderStatus::Draft->value,
                            PurchaseOrderStatus::Approved->value,
                            PurchaseOrderStatus::PartiallyReceived->value,
                        ],
                    )
                    ->count(),

                'purchaseValueYtd' => $canViewCosts
                    ? $this->purchaseValueYtd($organization)
                    : null,
            ],

            'pagination' => [
                'currentPage' => $currentPage,
                'lastPage' => $lastPage,
                'perPage' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'previousPageUrl' => $paginator->previousPageUrl(),
                'nextPageUrl' => $paginator->nextPageUrl(),
                'pages' => $pageLinks,
            ],

            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'perPage' => $perPage,
            ],

            'currency' => $organization->currency,
            'canViewCosts' => $canViewCosts,

            'canManage' => Gate::allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            ),
        ]);
    }

    /**
     * Calculate current-year committed PO value using decimal-safe arithmetic.
     */
    private function purchaseValueYtd(
        Organization $organization,
    ): string {
        $total = BigDecimal::zero();
        $currentYear = now($organization->timezone)->year;

        $purchaseOrders = PurchaseOrder::query()
            ->select([
                'id',
                'total',
            ])
            ->where(
                'organization_id',
                $organization->id,
            )
            ->whereYear(
                'order_date',
                $currentYear,
            )
            ->whereIn(
                'status',
                [
                    PurchaseOrderStatus::Approved->value,
                    PurchaseOrderStatus::PartiallyReceived->value,
                    PurchaseOrderStatus::Received->value,
                ],
            )
            ->cursor();

        foreach ($purchaseOrders as $purchaseOrder) {
            $total = $total->plus(
                $purchaseOrder->total,
            );
        }

        return (string) $total->toScale(2);
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
