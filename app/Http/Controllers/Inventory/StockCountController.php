<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CancelStockCount;
use App\Actions\Inventory\FinalizeStockCount;
use App\Actions\Inventory\SaveStockCount;
use App\Actions\Inventory\SubmitStockCount;
use App\Enums\OrganizationPermission;
use App\Enums\StockCountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveStockCountRequest;
use App\Http\Requests\Inventory\StockCountTransitionRequest;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Csv\CsvExport;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockCountController extends Controller
{
    /**
     * List stock-count workflow history for the active tenant.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        $this->authorizeCountRead($organization);

        [$filters, $query] = $this->indexQuery(
            $request,
            $organization,
        );

        $summary = $this->indexSummary($organization);

        $paginatedCounts = $this->applyIndexSort(
            clone $query,
            $filters['sort'],
            $filters['direction'],
        )
            ->paginate($filters['perPage'])
            ->withQueryString();

        $rows = collect($paginatedCounts->items())
            ->map(
                fn (StockCount $count): array => $this->indexRowData($count),
            )
            ->values()
            ->all();

        return Inertia::render(
            'stock-counts/index',
            [
                'rows' => $rows,
                'pagination' => [
                    'currentPage' => $paginatedCounts->currentPage(),
                    'from' => $paginatedCounts->firstItem(),
                    'lastPage' => $paginatedCounts->lastPage(),
                    'nextPageUrl' => $paginatedCounts->nextPageUrl(),
                    'perPage' => $paginatedCounts->perPage(),
                    'previousPageUrl' => $paginatedCounts->previousPageUrl(),
                    'to' => $paginatedCounts->lastItem(),
                    'total' => $paginatedCounts->total(),
                ],
                'summary' => $summary,
                'locationOptions' => $this->indexLocationOptions($organization),
                'storageLocationOptions' => $this->indexStorageLocationOptions(
                    $organization,
                    $filters['locationId'],
                ),
                'filters' => $filters,
                'timezone' => $organization->timezone,
                'canCreate' => Gate::allows(
                    OrganizationPermission::CountsCreate->value,
                    $organization,
                ),
                'canViewReport' => Gate::allows(
                    OrganizationPermission::ReportsView->value,
                    $organization,
                ),
            ],
        );
    }

    /**
     * Show an empty physical-count draft.
     */
    public function create(Request $request): Response
    {
        $organization = $this->activeOrganization(
            $request,
        );

        Gate::authorize(
            OrganizationPermission::CountsCreate->value,
            $organization,
        );

        return Inertia::render(
            'stock-counts/form',
            [
                'stockCount' => null,
                ...$this->formOptions($organization),
                'canCreate' => true,
                'canFinalize' => Gate::allows(
                    OrganizationPermission::CountsFinalize
                        ->value,
                    $organization,
                ),
                'canViewCosts' => Gate::allows(
                    OrganizationPermission::CostsView->value,
                    $organization,
                ),
            ],
        );
    }

    /**
     * Persist a new inventory-neutral count draft.
     */
    public function store(
        SaveStockCountRequest $request,
        SaveStockCount $saveStockCount,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();

        if (
            $organization === null
            || ! $actor instanceof User
        ) {
            abort(403);
        }

        $count = $saveStockCount->handle(
            $organization,
            $actor,
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock count draft created.'),
        ]);

        return to_route(
            'stock-counts.edit',
            $count,
        );
    }

    /**
     * Show editable draft evidence or immutable count history.
     */
    public function edit(
        Request $request,
        string $stockCount,
    ): Response {
        $organization = $this->activeOrganization(
            $request,
        );

        $this->authorizeCountRead($organization);

        $count = StockCount::query()
            ->with([
                'location:id,name',
                'storageLocation:id,name',
                'creator:id,name',
                'submitter:id,name',
                'finalizer:id,name',
                'lines.inventoryItem.baseUnitOfMeasure',
                'lines.countUnit:id,name,symbol',
                'lines.movement',
            ])
            ->where(
                'organization_id',
                $organization->id,
            )
            ->findOrFail($stockCount);

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        return Inertia::render(
            'stock-counts/form',
            [
                'stockCount' => $this->stockCountData(
                    $count,
                    $canViewCosts,
                ),
                ...$this->formOptions($organization),
                'canCreate' => Gate::allows(
                    OrganizationPermission::CountsCreate
                        ->value,
                    $organization,
                ),
                'canFinalize' => Gate::allows(
                    OrganizationPermission::CountsFinalize
                        ->value,
                    $organization,
                ),
                'canViewCosts' => $canViewCosts,
            ],
        );
    }

    /**
     * Replace an existing count draft.
     */
    public function update(
        SaveStockCountRequest $request,
        string $stockCount,
        SaveStockCount $saveStockCount,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $count = $request->stockCount();

        if (
            $organization === null
            || ! $actor instanceof User
            || $count === null
        ) {
            abort(403);
        }

        $saveStockCount->handle(
            $organization,
            $actor,
            $request->validated(),
            $count,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock count draft updated.'),
        ]);

        return to_route(
            'stock-counts.edit',
            $stockCount,
        );
    }

    /**
     * Submit physical evidence and make the lines immutable.
     */
    public function submit(
        StockCountTransitionRequest $request,
        string $stockCount,
        SubmitStockCount $submitStockCount,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $count = $request->stockCount();

        if (
            $organization === null
            || ! $actor instanceof User
            || $count === null
        ) {
            abort(403);
        }

        $submitStockCount->handle(
            $organization,
            $actor,
            $count,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock count submitted.'),
        ]);

        return to_route(
            'stock-counts.edit',
            $stockCount,
        );
    }

    /**
     * Reconcile a submitted physical count through the stock ledger.
     */
    public function finalize(
        StockCountTransitionRequest $request,
        string $stockCount,
        FinalizeStockCount $finalizeStockCount,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $count = $request->stockCount();

        if (
            $organization === null
            || ! $actor instanceof User
            || $count === null
        ) {
            abort(403);
        }

        $finalizeStockCount->handle(
            $organization,
            $actor,
            $count,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock count finalized.'),
        ]);

        return to_route(
            'stock-counts.edit',
            $stockCount,
        );
    }

    /**
     * Cancel an inventory-neutral stock-count workflow.
     */
    public function cancel(
        StockCountTransitionRequest $request,
        string $stockCount,
        CancelStockCount $cancelStockCount,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();
        $count = $request->stockCount();

        if (
            $organization === null
            || ! $actor instanceof User
            || $count === null
        ) {
            abort(403);
        }

        $cancelStockCount->handle(
            $organization,
            $actor,
            $count,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock count cancelled.'),
        ]);

        return to_route(
            'stock-counts.edit',
            $stockCount,
        );
    }

    /**
     * Report immutable finalized count variances.
     */
    public function variance(Request $request): Response
    {
        $organization = $this->activeOrganization(
            $request,
        );

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        [$filters, $query] = $this->varianceQuery(
            $request,
            $organization,
        );

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        $counts = $query
            ->orderByDesc('counted_at')
            ->orderByDesc('id')
            ->get();

        $rows = [];

        foreach ($counts as $count) {
            foreach ($count->lines as $line) {
                $rows[] = $this->varianceRow(
                    $count,
                    $line,
                    $canViewCosts,
                );
            }
        }

        $locationOptions = Location::query()
            ->where(
                'organization_id',
                $organization->id,
            )
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

        return Inertia::render(
            'stock-counts/variance',
            [
                'rows' => $rows,
                'locationOptions' => $locationOptions,
                'filters' => $filters,
                'currency' => $organization->currency,
                'canViewCosts' => $canViewCosts,
            ],
        );
    }

    /**
     * Stream the same permission- and tenant-scoped variance evidence as a
     * CSV download.
     */
    public function exportVariance(Request $request): StreamedResponse
    {
        $organization = $this->activeOrganization(
            $request,
        );

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        [, $query] = $this->varianceQuery(
            $request,
            $organization,
        );

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        $header = [
            'Count Number',
            'Counted At',
            'Location',
            'Storage Location',
            'Item',
            'SKU',
            'Expected Base Quantity',
            'Counted Quantity',
            'Count Unit',
            'Counted Base Quantity',
            'Base Unit',
            'Variance Base Quantity',
            'Variance Unit Cost',
            'Variance Total Cost',
        ];

        $rows = (function () use (
            $query,
            $canViewCosts,
        ): iterable {
            foreach (
                $query
                    ->orderByDesc('counted_at')
                    ->orderByDesc('id')
                    ->cursor() as $count
            ) {
                foreach ($count->lines as $line) {
                    $data = $this->varianceRow(
                        $count,
                        $line,
                        $canViewCosts,
                    );

                    yield [
                        $data['countNumber'],
                        $data['countedAt'],
                        $data['locationName'],
                        $data['storageLocationName'],
                        $data['itemName'],
                        $data['itemSku'],
                        $data['expectedBaseQuantity'],
                        $data['countedQuantity'],
                        $data['countUnitSymbol'],
                        $data['countedBaseQuantity'],
                        $data['baseUnitSymbol'],
                        $data['varianceBaseQuantity'],
                        $data['varianceUnitCost'],
                        $data['varianceTotalCost'],
                    ];
                }
            }
        })();

        return CsvExport::download(
            'stock-count-variance.csv',
            $header,
            $rows,
        );
    }

    /**
     * Build the tenant-scoped, server-authoritative query for the stock-count
     * operations index.
     *
     * @return array{
     *     0: array{
     *         search: string|null,
     *         view: 'all'|'open'|'draft'|'submitted'|'finalized'|'cancelled'|'variance',
     *         locationId: int|null,
     *         storageLocationId: int|null,
     *         from: string|null,
     *         to: string|null,
     *         sort: 'latest'|'number'|'status'|'counted_at'|'finalized_at',
     *         direction: 'asc'|'desc',
     *         perPage: int
     *     },
     *     1: EloquentBuilder<StockCount>
     * }
     */
    private function indexQuery(
        Request $request,
        Organization $organization,
    ): array {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],
            'view' => [
                'nullable',
                Rule::in([
                    'all',
                    'open',
                    'draft',
                    'submitted',
                    'finalized',
                    'cancelled',
                    'variance',
                ]),
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
            'storage_location_id' => [
                'nullable',
                'integer',
                Rule::exists('storage_locations', 'id')->where(
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
                Rule::in([
                    'latest',
                    'number',
                    'status',
                    'counted_at',
                    'finalized_at',
                ]),
            ],
            'direction' => [
                'nullable',
                Rule::in(['asc', 'desc']),
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in([10, 25, 50]),
            ],
        ]);

        $search = isset($validated['search'])
            ? trim((string) $validated['search'])
            : null;

        if ($search === '') {
            $search = null;
        }

        $view = match ($validated['view'] ?? 'all') {
            'open' => 'open',
            'draft' => 'draft',
            'submitted' => 'submitted',
            'finalized' => 'finalized',
            'cancelled' => 'cancelled',
            'variance' => 'variance',
            default => 'all',
        };

        $locationId = isset($validated['location_id'])
            ? (int) $validated['location_id']
            : null;

        $storageLocationId = isset($validated['storage_location_id'])
            ? (int) $validated['storage_location_id']
            : null;

        $from = isset($validated['from'])
            ? (string) $validated['from']
            : null;

        $to = isset($validated['to'])
            ? (string) $validated['to']
            : null;

        $sort = match ($validated['sort'] ?? 'latest') {
            'number' => 'number',
            'status' => 'status',
            'counted_at' => 'counted_at',
            'finalized_at' => 'finalized_at',
            default => 'latest',
        };

        $direction = ($validated['direction'] ?? 'desc') === 'asc'
            ? 'asc'
            : 'desc';

        $perPage = isset($validated['per_page'])
            ? (int) $validated['per_page']
            : 10;

        if (
            $from !== null
            && $to !== null
            && $from > $to
        ) {
            throw ValidationException::withMessages([
                'from' => __('The from date must not be after the to date.'),
            ]);
        }

        if (
            $locationId !== null
            && $storageLocationId !== null
            && ! StorageLocation::query()
                ->where('organization_id', $organization->id)
                ->where('location_id', $locationId)
                ->whereKey($storageLocationId)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'storage_location_id' => __(
                    'The selected storage location does not belong to the selected location.',
                ),
            ]);
        }

        $query = StockCount::query()
            ->with([
                'location:id,name',
                'storageLocation:id,name',
                'submitter:id,name',
            ])
            ->withCount([
                'lines as variance_item_count' => static function ($lineQuery): void {
                    $lineQuery->where(
                        'variance_base_quantity',
                        '!=',
                        0,
                    );
                },
            ])
            ->where('organization_id', $organization->id);

        if ($search !== null) {
            $query->where(
                function (EloquentBuilder $searchQuery) use ($search): void {
                    $searchQuery
                        ->whereLike(
                            'number',
                            "%{$search}%",
                        )
                        ->orWhereHas(
                            'location',
                            fn ($locationQuery) => $locationQuery->whereLike(
                                'name',
                                "%{$search}%",
                            ),
                        )
                        ->orWhereHas(
                            'storageLocation',
                            fn ($storageQuery) => $storageQuery->whereLike(
                                'name',
                                "%{$search}%",
                            ),
                        );
                },
            );
        }

        match ($view) {
            'open' => $query->whereIn('status', [
                StockCountStatus::Draft->value,
                StockCountStatus::Submitted->value,
            ]),
            'draft' => $query->where(
                'status',
                StockCountStatus::Draft->value,
            ),
            'submitted' => $query->where(
                'status',
                StockCountStatus::Submitted->value,
            ),
            'finalized' => $query->where(
                'status',
                StockCountStatus::Finalized->value,
            ),
            'cancelled' => $query->where(
                'status',
                StockCountStatus::Cancelled->value,
            ),
            'variance' => $query
                ->where(
                    'status',
                    StockCountStatus::Finalized->value,
                )
                ->whereHas(
                    'lines',
                    fn ($lineQuery) => $lineQuery->where(
                        'variance_base_quantity',
                        '!=',
                        0,
                    ),
                ),
            default => $query,
        };

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        if ($storageLocationId !== null) {
            $query->where('storage_location_id', $storageLocationId);
        }

        if ($from !== null) {
            $query->where(
                'counted_at',
                '>=',
                CarbonImmutable::parse(
                    $from,
                    $organization->timezone,
                )
                    ->startOfDay()
                    ->utc(),
            );
        }

        if ($to !== null) {
            $query->where(
                'counted_at',
                '<=',
                CarbonImmutable::parse(
                    $to,
                    $organization->timezone,
                )
                    ->endOfDay()
                    ->utc(),
            );
        }

        return [
            [
                'search' => $search,
                'view' => $view,
                'locationId' => $locationId,
                'storageLocationId' => $storageLocationId,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            $query,
        ];
    }

    /**
     * Build organization-wide Stock Counts metrics without mixing filtered
     * table state into the operational headline numbers.
     *
     * @return array{
     *     totalCount: int,
     *     openCount: int,
     *     finalizedTodayCount: int,
     *     varianceAlertCount: int
     * }
     */
    private function indexSummary(Organization $organization): array
    {
        $baseQuery = StockCount::query()
            ->where('organization_id', $organization->id);

        $today = CarbonImmutable::now($organization->timezone);

        return [
            'totalCount' => (clone $baseQuery)->count(),
            'openCount' => (clone $baseQuery)
                ->whereIn('status', [
                    StockCountStatus::Draft->value,
                    StockCountStatus::Submitted->value,
                ])
                ->count(),
            'finalizedTodayCount' => (clone $baseQuery)
                ->where(
                    'status',
                    StockCountStatus::Finalized->value,
                )
                ->whereBetween('finalized_at', [
                    $today->startOfDay()->utc(),
                    $today->endOfDay()->utc(),
                ])
                ->count(),
            'varianceAlertCount' => (clone $baseQuery)
                ->where(
                    'status',
                    StockCountStatus::Finalized->value,
                )
                ->whereHas(
                    'lines',
                    fn ($lineQuery) => $lineQuery->where(
                        'variance_base_quantity',
                        '!=',
                        0,
                    ),
                )
                ->count(),
        ];
    }

    /**
     * Serialize one Stock Counts index row from persisted workflow evidence.
     *
     * @return array{
     *     id: int,
     *     number: string,
     *     status: string,
     *     locationName: string,
     *     storageLocationName: string,
     *     countedByName: string|null,
     *     countedAt: string|null,
     *     finalizedAt: string|null,
     *     varianceItemCount: int|null
     * }
     */
    private function indexRowData(StockCount $count): array
    {
        return [
            'id' => $count->id,
            'number' => $count->number,
            'status' => $count->status->value,
            'locationName' => $count->location->name,
            'storageLocationName' => $count->storageLocation->name,
            'countedByName' => $count->submitter?->name,
            'countedAt' => $count->counted_at?->toIso8601String(),
            'finalizedAt' => $count->finalized_at?->toIso8601String(),
            'varianceItemCount' => $count->status === StockCountStatus::Finalized
                ? (int) ($count->getAttribute('variance_item_count') ?? 0)
                : null,
        ];
    }

    /**
     * Apply only explicitly supported sort keys so index ordering stays
     * deterministic and cannot accept arbitrary columns from the request.
     *
     * @param  EloquentBuilder<StockCount>  $query
     * @param  'latest'|'number'|'status'|'counted_at'|'finalized_at'  $sort
     * @param  'asc'|'desc'  $direction
     * @return EloquentBuilder<StockCount>
     */
    private function applyIndexSort(
        EloquentBuilder $query,
        string $sort,
        string $direction,
    ): EloquentBuilder {
        if ($sort === 'latest') {
            return $query->orderByDesc('id');
        }

        return $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id');
    }

    /**
     * Return all tenant locations so historical counts remain filterable even
     * after a location is deactivated.
     *
     * @return list<array{id: int, name: string}>
     */
    private function indexLocationOptions(Organization $organization): array
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
     * Return tenant storage locations, narrowed by the selected location when
     * present, while preserving historical locations in the filter.
     *
     * @return list<array{id: int, locationId: int, name: string}>
     */
    private function indexStorageLocationOptions(
        Organization $organization,
        ?int $locationId,
    ): array {
        $query = StorageLocation::query()
            ->where('organization_id', $organization->id);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return array_values(
            $query
                ->orderBy('name')
                ->get([
                    'id',
                    'location_id',
                    'name',
                ])
                ->map(
                    static fn (StorageLocation $storageLocation): array => [
                        'id' => $storageLocation->id,
                        'locationId' => $storageLocation->location_id,
                        'name' => $storageLocation->name,
                    ],
                )
                ->all(),
        );
    }

    /**
     * Build the shared tenant-scoped, filtered query behind every rendering
     * of the finalized Count Variance report.
     *
     * @return array{0: array{locationId: int|null, from: string|null, to: string|null}, 1: EloquentBuilder<StockCount>}
     */
    private function varianceQuery(
        Request $request,
        Organization $organization,
    ): array {
        $validated = $request->validate([
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

        $locationId = isset(
            $validated['location_id'],
        )
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
            abort(422, 'The from date must not be after the to date.');
        }

        $query = StockCount::query()
            ->with([
                'location:id,name',
                'storageLocation:id,name',
                'lines.inventoryItem.baseUnitOfMeasure',
                'lines.countUnit:id,name,symbol',
                'lines.movement',
            ])
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where(
                'status',
                StockCountStatus::Finalized->value,
            );

        if ($locationId !== null) {
            $query->where(
                'location_id',
                $locationId,
            );
        }

        if ($from !== null) {
            $query->where(
                'counted_at',
                '>=',
                CarbonImmutable::parse(
                    $from,
                    $organization->timezone,
                )
                    ->startOfDay()
                    ->utc(),
            );
        }

        if ($to !== null) {
            $query->where(
                'counted_at',
                '<=',
                CarbonImmutable::parse(
                    $to,
                    $organization->timezone,
                )
                    ->endOfDay()
                    ->utc(),
            );
        }

        return [
            [
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
    private function varianceRow(
        StockCount $count,
        StockCountLine $line,
        bool $canViewCosts,
    ): array {
        return [
            'countId' => $count->id,
            'countNumber' => $count->number,
            'countedAt' => $count->counted_at
                ?->toIso8601String(),
            'locationName' => $count->location->name,
            'storageLocationName' => $count->storageLocation->name,
            'itemName' => $line->inventoryItem->name,
            'itemSku' => $line->inventoryItem->sku,
            'expectedBaseQuantity' => $line->expected_base_quantity,
            'countedQuantity' => $line->counted_quantity,
            'countUnitSymbol' => $line->countUnit->symbol,
            'countedBaseQuantity' => $line->counted_base_quantity,
            'baseUnitSymbol' => $line
                ->inventoryItem
                ->baseUnitOfMeasure
                ->symbol,
            'varianceBaseQuantity' => $line->variance_base_quantity,
            'varianceUnitCost' => $canViewCosts
                ? $line->variance_unit_cost
                : null,
            'varianceTotalCost' => $canViewCosts
                ? $line->variance_total_cost
                : null,
            'movementId' => $line->movement?->id,
        ];
    }

    /**
     * Serialize one stock-count aggregate without leaking protected costs.
     *
     * @return array<string, mixed>
     */
    private function stockCountData(
        StockCount $count,
        bool $canViewCosts,
    ): array {
        return [
            'id' => $count->id,
            'number' => $count->number,
            'status' => $count->status->value,
            'locationId' => $count->location_id,
            'locationName' => $count->location->name,
            'storageLocationId' => $count->storage_location_id,
            'storageLocationName' => $count->storageLocation->name,
            'countedAt' => $count->counted_at
                ?->toIso8601String(),
            'createdBy' => $count->creator?->name,
            'submittedBy' => $count->submitter?->name,
            'finalizedBy' => $count->finalizer?->name,
            'finalizedAt' => $count->finalized_at
                ?->toIso8601String(),
            'lines' => $count
                ->lines
                ->map(
                    static fn (
                        StockCountLine $line,
                    ): array => [
                        'id' => $line->id,
                        'inventoryItemId' => $line->inventory_item_id,
                        'itemName' => $line->inventoryItem->name,
                        'itemSku' => $line->inventoryItem->sku,
                        'expectedBaseQuantity' => $line->expected_base_quantity,
                        'countedQuantity' => $line->counted_quantity,
                        'countUnitId' => $line->count_unit_id,
                        'countUnitSymbol' => $line->countUnit->symbol,
                        'countedBaseQuantity' => $line->counted_base_quantity,
                        'baseUnitSymbol' => $line
                            ->inventoryItem
                            ->baseUnitOfMeasure
                            ->symbol,
                        'varianceBaseQuantity' => $line->variance_base_quantity,
                        'varianceUnitCost' => $canViewCosts
                                ? $line->variance_unit_cost
                                : null,
                        'varianceTotalCost' => $canViewCosts
                                ? $line->variance_total_cost
                                : null,
                        'notes' => $line->notes,
                        'movementId' => $line->movement?->id,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * Build tenant-scoped active form selections.
     *
     * @return array<string, mixed>
     */
    private function formOptions(
        Organization $organization,
    ): array {
        $locations = Location::query()
            ->where(
                'organization_id',
                $organization->id,
            )
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

        $storageLocations =
            StorageLocation::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where('active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'location_id',
                    'name',
                ])
                ->map(
                    static fn (
                        StorageLocation $storageLocation,
                    ): array => [
                        'id' => $storageLocation->id,
                        'locationId' => $storageLocation->location_id,
                        'name' => $storageLocation->name,
                    ],
                )
                ->values()
                ->all();

        $inventoryItems = InventoryItem::query()
            ->with('baseUnitOfMeasure')
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->whereHas(
                'baseUnitOfMeasure',
                fn ($query) => $query
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where('active', true),
            )
            ->orderBy('name')
            ->get()
            ->map(
                static fn (
                    InventoryItem $inventoryItem,
                ): array => [
                    'id' => $inventoryItem->id,
                    'name' => $inventoryItem->name,
                    'sku' => $inventoryItem->sku,
                    'baseUnitId' => $inventoryItem
                        ->base_unit_of_measure_id,
                    'baseUnitSymbol' => $inventoryItem
                        ->baseUnitOfMeasure
                        ->symbol,
                ],
            )
            ->values()
            ->all();

        $units = UnitOfMeasure::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'symbol',
            ])
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
            'locationOptions' => $locations,
            'storageLocationOptions' => $storageLocations,
            'inventoryItemOptions' => $inventoryItems,
            'unitOptions' => $units,
            'currency' => $organization->currency,
        ];
    }

    /**
     * Permit count history to operators or report readers.
     */
    private function authorizeCountRead(
        Organization $organization,
    ): void {
        if (
            ! Gate::allows(
                OrganizationPermission::CountsCreate->value,
                $organization,
            )
            && ! Gate::allows(
                OrganizationPermission::CountsFinalize->value,
                $organization,
            )
            && ! Gate::allows(
                OrganizationPermission::ReportsView->value,
                $organization,
            )
        ) {
            abort(403);
        }
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
