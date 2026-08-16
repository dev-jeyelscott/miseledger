<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConvertQuantity;
use App\Actions\Inventory\RecordWaste;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RecordWasteRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use App\Models\WasteRecord;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

/**
 * @phpstan-type WasteReportFilters array{
 *     locationId: int|null,
 *     inventoryCategoryId: int|null,
 *     inventoryItemId: int|null,
 *     wasteReasonId: int|null,
 *     from: string|null,
 *     to: string|null
 * }
 * @phpstan-type WasteQuantityTotal array{
 *     baseUnitId: int,
 *     quantity: string,
 *     unitSymbol: string
 * }
 * @phpstan-type WasteSummary array{
 *     recordCount: int,
 *     quantityTotals: list<WasteQuantityTotal>,
 *     totalCost: string|null
 * }
 * @phpstan-type WasteByReasonRow array{
 *     reasonId: int,
 *     reasonName: string,
 *     recordCount: int,
 *     quantityTotals: list<WasteQuantityTotal>,
 *     totalCost: string|null
 * }
 * @phpstan-type WasteByEmployeeRow array{
 *     employeeId: int|null,
 *     employeeName: string,
 *     recordCount: int,
 *     quantityTotals: list<WasteQuantityTotal>,
 *     totalCost: string|null
 * }
 * @phpstan-type WasteByItemRow array{
 *     itemId: int,
 *     itemName: string,
 *     itemSku: string,
 *     baseUnitId: int,
 *     baseUnitSymbol: string,
 *     recordCount: int,
 *     totalQuantity: string,
 *     totalCost: string|null
 * }
 * @phpstan-type WasteByLocationRow array{
 *     locationId: int,
 *     locationName: string,
 *     recordCount: int,
 *     quantityTotals: list<WasteQuantityTotal>,
 *     totalCost: string|null
 * }
 * @phpstan-type WasteAggregateReport array{
 *     summary: WasteSummary,
 *     byReason: list<WasteByReasonRow>,
 *     byEmployee: list<WasteByEmployeeRow>,
 *     byItem: list<WasteByItemRow>,
 *     byLocation: list<WasteByLocationRow>
 * }
 * @phpstan-type WasteReportRow array{
 *     recordId: int,
 *     occurredAt: string,
 *     locationName: string,
 *     storageLocationName: string,
 *     itemName: string,
 *     itemSku: string,
 *     reasonName: string,
 *     quantity: string,
 *     unitSymbol: string,
 *     baseQuantity: string,
 *     baseUnitSymbol: string,
 *     unitCost: string|null,
 *     totalCost: string|null,
 *     recordedBy: string|null,
 *     notes: string|null,
 *     movementId: int|null
 * }
 */
class WasteController extends Controller
{
    /**
     * Show the waste recording workspace and permission-scoped report.
     */
    public function index(
        Request $request,
        ConvertQuantity $convertQuantity,
    ): Response {
        $organization = $this->activeOrganization($request);

        $canRecord = Gate::allows(
            OrganizationPermission::WasteRecord->value,
            $organization,
        );

        $canViewReport = Gate::allows(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        if (! $canRecord && ! $canViewReport) {
            abort(403);
        }

        $canManageReasons = Gate::allows(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        $canViewCosts = $canViewReport
            && Gate::allows(
                OrganizationPermission::CostsView->value,
                $organization,
            );

        $filters = [
            'locationId' => null,
            'inventoryCategoryId' => null,
            'inventoryItemId' => null,
            'wasteReasonId' => null,
            'from' => null,
            'to' => null,
        ];

        $rows = null;
        $report = null;

        if ($canViewReport) {
            [$filters, $rows, $report] = $this->reportData(
                $request,
                $organization,
                $canViewCosts,
            );
        }

        $allReasons = WasteReason::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'active',
            ])
            ->map(
                static fn (WasteReason $reason): array => [
                    'id' => $reason->id,
                    'name' => $reason->name,
                    'active' => $reason->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('waste/index', [
            'rows' => $rows,
            'report' => $report,
            'filters' => $filters,
            'currency' => $organization->currency,
            'canRecord' => $canRecord,
            'canManageReasons' => $canManageReasons,
            'canViewReport' => $canViewReport,
            'canViewCosts' => $canViewCosts,
            'wasteReasons' => $allReasons,
            'recordForm' => $canRecord
                ? $this->recordFormData(
                    $organization,
                    $convertQuantity,
                )
                : null,
            'reportOptions' => $canViewReport
                ? $this->reportOptions($organization)
                : null,
        ]);
    }

    /**
     * Immediately finalize one waste operation through the stock ledger.
     */
    public function store(
        RecordWasteRequest $request,
        RecordWaste $recordWaste,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();

        if (
            $organization === null
            || ! $actor instanceof User
        ) {
            abort(403);
        }

        $record = $recordWaste->handle(
            $organization,
            $actor,
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(
                'Waste #:id recorded.',
                ['id' => $record->id],
            ),
        ]);

        return to_route('waste.index');
    }

    /**
     * Build filters, aggregate reports, and a bounded page of immutable waste evidence.
     *
     * @return array{0: WasteReportFilters, 1: array<array-key, mixed>, 2: WasteAggregateReport}
     */
    private function reportData(
        Request $request,
        Organization $organization,
        bool $canViewCosts,
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
            'inventory_category_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_categories', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                ),
            ],
            'inventory_item_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_items', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                ),
            ],
            'waste_reason_id' => [
                'nullable',
                'integer',
                Rule::exists('waste_reasons', 'id')->where(
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

        $locationId = isset($validated['location_id'])
            ? (int) $validated['location_id']
            : null;

        $inventoryCategoryId = isset($validated['inventory_category_id'])
            ? (int) $validated['inventory_category_id']
            : null;

        $inventoryItemId = isset($validated['inventory_item_id'])
            ? (int) $validated['inventory_item_id']
            : null;

        $wasteReasonId = isset($validated['waste_reason_id'])
            ? (int) $validated['waste_reason_id']
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

        $filters = [
            'locationId' => $locationId,
            'inventoryCategoryId' => $inventoryCategoryId,
            'inventoryItemId' => $inventoryItemId,
            'wasteReasonId' => $wasteReasonId,
            'from' => $from,
            'to' => $to,
        ];

        $evidenceQuery = $this->reportEvidenceQuery(
            $organization,
            $filters,
        );

        $rows = WasteRecord::query()
            ->with([
                'location:id,name',
                'storageLocation:id,name',
                'inventoryItem.baseUnitOfMeasure:id,symbol',
                'wasteReason:id,name',
                'unit:id,symbol',
                'recorder:id,name',
                'movement:id,reference_type,reference_id',
            ])
            ->where(
                'organization_id',
                $organization->id,
            )
            ->whereIn(
                'id',
                (clone $evidenceQuery)->select('waste_records.id'),
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(
                fn (
                    WasteRecord $record,
                ): array => $this->wasteReportRow(
                    $record,
                    $canViewCosts,
                ),
            )
            ->toArray();

        return [
            $filters,
            $rows,
            $this->aggregateReport(
                $evidenceQuery,
                $canViewCosts,
            ),
        ];
    }

    /**
     * Build the shared tenant-scoped evidence query used by every Waste report.
     *
     * @param  WasteReportFilters  $filters
     */
    private function reportEvidenceQuery(
        Organization $organization,
        array $filters,
    ): Builder {
        $query = DB::table('waste_records')
            ->join(
                'inventory_items',
                function (JoinClause $join): void {
                    $join
                        ->on(
                            'inventory_items.id',
                            '=',
                            'waste_records.inventory_item_id',
                        )
                        ->on(
                            'inventory_items.organization_id',
                            '=',
                            'waste_records.organization_id',
                        );
                },
            )
            ->join(
                'units_of_measure as base_units',
                function (JoinClause $join): void {
                    $join
                        ->on(
                            'base_units.id',
                            '=',
                            'inventory_items.base_unit_of_measure_id',
                        )
                        ->on(
                            'base_units.organization_id',
                            '=',
                            'waste_records.organization_id',
                        );
                },
            )
            ->where(
                'waste_records.organization_id',
                $organization->id,
            );

        if ($filters['locationId'] !== null) {
            $query->where(
                'waste_records.location_id',
                $filters['locationId'],
            );
        }

        if ($filters['inventoryCategoryId'] !== null) {
            $query->where(
                'inventory_items.inventory_category_id',
                $filters['inventoryCategoryId'],
            );
        }

        if ($filters['inventoryItemId'] !== null) {
            $query->where(
                'waste_records.inventory_item_id',
                $filters['inventoryItemId'],
            );
        }

        if ($filters['wasteReasonId'] !== null) {
            $query->where(
                'waste_records.waste_reason_id',
                $filters['wasteReasonId'],
            );
        }

        if ($filters['from'] !== null) {
            $query->where(
                'waste_records.occurred_at',
                '>=',
                CarbonImmutable::parse(
                    $filters['from'],
                    $organization->timezone,
                )
                    ->startOfDay()
                    ->utc(),
            );
        }

        if ($filters['to'] !== null) {
            $query->where(
                'waste_records.occurred_at',
                '<=',
                CarbonImmutable::parse(
                    $filters['to'],
                    $organization->timezone,
                )
                    ->endOfDay()
                    ->utc(),
            );
        }

        return $query;
    }

    /**
     * Build every aggregate Waste report from the exact same filtered evidence.
     *
     * @return WasteAggregateReport
     */
    private function aggregateReport(
        Builder $query,
        bool $canViewCosts,
    ): array {
        return [
            'summary' => $this->wasteSummary(
                clone $query,
                $canViewCosts,
            ),
            'byReason' => $this->wasteByReason(
                clone $query,
                $canViewCosts,
            ),
            'byEmployee' => $this->wasteByEmployee(
                clone $query,
                $canViewCosts,
            ),
            'byItem' => $this->wasteByItem(
                clone $query,
                $canViewCosts,
            ),
            'byLocation' => $this->wasteByLocation(
                clone $query,
                $canViewCosts,
            ),
        ];
    }

    /**
     * Summarize filtered waste without combining quantities from unlike base units.
     *
     * @return WasteSummary
     */
    private function wasteSummary(
        Builder $query,
        bool $canViewCosts,
    ): array {
        $rows = $this->addAggregateSelects(
            $query->select([
                'base_units.id as base_unit_id',
                'base_units.symbol as base_unit_symbol',
            ]),
            $canViewCosts,
        )
            ->groupBy(
                'base_units.id',
                'base_units.symbol',
            )
            ->orderBy('base_units.symbol')
            ->orderBy('base_units.id')
            ->get();

        $recordCount = 0;
        $totalCost = '0.0000';
        /** @var list<WasteQuantityTotal> $quantityTotals */
        $quantityTotals = [];

        foreach ($rows as $row) {
            $recordCount += (int) $row->record_count;
            $quantityTotals[] = $this->quantityTotal($row);

            if ($canViewCosts) {
                $totalCost = $this->addDecimal(
                    $totalCost,
                    $row->total_cost,
                    4,
                );
            }
        }

        return [
            'recordCount' => $recordCount,
            'quantityTotals' => $quantityTotals,
            'totalCost' => $canViewCosts
                ? $totalCost
                : null,
        ];
    }

    /**
     * Group filtered immutable waste evidence by retained business reason.
     *
     * @return list<WasteByReasonRow>
     */
    private function wasteByReason(
        Builder $query,
        bool $canViewCosts,
    ): array {
        $rows = $this->addAggregateSelects(
            $query
                ->join(
                    'waste_reasons',
                    function (JoinClause $join): void {
                        $join
                            ->on(
                                'waste_reasons.id',
                                '=',
                                'waste_records.waste_reason_id',
                            )
                            ->on(
                                'waste_reasons.organization_id',
                                '=',
                                'waste_records.organization_id',
                            );
                    },
                )
                ->select([
                    'waste_reasons.id as reason_id',
                    'waste_reasons.name as reason_name',
                    'base_units.id as base_unit_id',
                    'base_units.symbol as base_unit_symbol',
                ]),
            $canViewCosts,
        )
            ->groupBy(
                'waste_reasons.id',
                'waste_reasons.name',
                'base_units.id',
                'base_units.symbol',
            )
            ->get();

        /** @var array<int, WasteByReasonRow> $report */
        $report = [];
        foreach ($rows as $row) {
            $reasonId = (int) $row->reason_id;

            if (! isset($report[$reasonId])) {
                $report[$reasonId] = [
                    'reasonId' => $reasonId,
                    'reasonName' => (string) $row->reason_name,
                    'recordCount' => 0,
                    'quantityTotals' => [],
                    'totalCost' => $canViewCosts
                        ? '0.0000'
                        : null,
                ];
            }

            $report[$reasonId]['recordCount'] += (int) $row->record_count;
            $report[$reasonId]['quantityTotals'][] = $this->quantityTotal($row);

            if ($canViewCosts) {
                $report[$reasonId]['totalCost'] = $this->addDecimal(
                    (string) $report[$reasonId]['totalCost'],
                    $row->total_cost,
                    4,
                );
            }
        }

        $report = array_values($report);

        usort(
            $report,
            static fn (array $left, array $right): int => strcasecmp(
                $left['reasonName'],
                $right['reasonName'],
            ),
        );

        return $report;
    }

    /**
     * Group filtered immutable waste evidence by the user who recorded it.
     *
     * @return list<WasteByEmployeeRow>
     */
    private function wasteByEmployee(
        Builder $query,
        bool $canViewCosts,
    ): array {
        $rows = $this->addAggregateSelects(
            $query
                ->leftJoin(
                    'users',
                    'users.id',
                    '=',
                    'waste_records.recorded_by',
                )
                ->select([
                    'waste_records.recorded_by as employee_id',
                    'users.name as employee_name',
                    'base_units.id as base_unit_id',
                    'base_units.symbol as base_unit_symbol',
                ]),
            $canViewCosts,
        )
            ->groupBy(
                'waste_records.recorded_by',
                'users.name',
                'base_units.id',
                'base_units.symbol',
            )
            ->get();

        /** @var array<string, WasteByEmployeeRow> $report */
        $report = [];

        foreach ($rows as $row) {
            $employeeId = $row->employee_id === null
                ? null
                : (int) $row->employee_id;
            $key = $employeeId === null
                ? 'unknown'
                : (string) $employeeId;
            $employeeName = is_string($row->employee_name)
                && trim($row->employee_name) !== ''
                ? $row->employee_name
                : 'Unknown user';

            if (! isset($report[$key])) {
                $report[$key] = [
                    'employeeId' => $employeeId,
                    'employeeName' => $employeeName,
                    'recordCount' => 0,
                    'quantityTotals' => [],
                    'totalCost' => $canViewCosts
                        ? '0.0000'
                        : null,
                ];
            }

            $report[$key]['recordCount'] += (int) $row->record_count;
            $report[$key]['quantityTotals'][] = $this->quantityTotal($row);

            if ($canViewCosts) {
                $report[$key]['totalCost'] = $this->addDecimal(
                    (string) $report[$key]['totalCost'],
                    $row->total_cost,
                    4,
                );
            }
        }

        $report = array_values($report);

        usort(
            $report,
            static fn (array $left, array $right): int => strcasecmp(
                $left['employeeName'],
                $right['employeeName'],
            ),
        );

        return $report;
    }

    /**
     * Group filtered immutable waste evidence by inventory item.
     *
     * @return list<WasteByItemRow>
     */
    private function wasteByItem(
        Builder $query,
        bool $canViewCosts,
    ): array {
        $rows = $this->addAggregateSelects(
            $query->select([
                'inventory_items.id as item_id',
                'inventory_items.name as item_name',
                'inventory_items.sku as item_sku',
                'base_units.id as base_unit_id',
                'base_units.symbol as base_unit_symbol',
            ]),
            $canViewCosts,
        )
            ->groupBy(
                'inventory_items.id',
                'inventory_items.name',
                'inventory_items.sku',
                'base_units.id',
                'base_units.symbol',
            )
            ->orderBy('inventory_items.name')
            ->orderBy('inventory_items.id')
            ->get()
            ->map(
                fn (stdClass $row): array => [
                    'itemId' => (int) $row->item_id,
                    'itemName' => (string) $row->item_name,
                    'itemSku' => (string) $row->item_sku,
                    'baseUnitId' => (int) $row->base_unit_id,
                    'baseUnitSymbol' => (string) $row->base_unit_symbol,
                    'recordCount' => (int) $row->record_count,
                    'totalQuantity' => $this->fixedDecimal(
                        $row->total_quantity,
                        6,
                    ),
                    'totalCost' => $canViewCosts
                        ? $this->fixedDecimal(
                            $row->total_cost,
                            4,
                        )
                        : null,
                ],
            )
            ->all();

        return array_values($rows);
    }

    /**
     * Group filtered immutable waste evidence by restaurant location.
     *
     * @return list<WasteByLocationRow>
     */
    private function wasteByLocation(
        Builder $query,
        bool $canViewCosts,
    ): array {
        $rows = $this->addAggregateSelects(
            $query
                ->join(
                    'locations',
                    function (JoinClause $join): void {
                        $join
                            ->on(
                                'locations.id',
                                '=',
                                'waste_records.location_id',
                            )
                            ->on(
                                'locations.organization_id',
                                '=',
                                'waste_records.organization_id',
                            );
                    },
                )
                ->select([
                    'locations.id as location_id',
                    'locations.name as location_name',
                    'base_units.id as base_unit_id',
                    'base_units.symbol as base_unit_symbol',
                ]),
            $canViewCosts,
        )
            ->groupBy(
                'locations.id',
                'locations.name',
                'base_units.id',
                'base_units.symbol',
            )
            ->get();

        /** @var array<int, WasteByLocationRow> $report */
        $report = [];
        foreach ($rows as $row) {
            $locationId = (int) $row->location_id;

            if (! isset($report[$locationId])) {
                $report[$locationId] = [
                    'locationId' => $locationId,
                    'locationName' => (string) $row->location_name,
                    'recordCount' => 0,
                    'quantityTotals' => [],
                    'totalCost' => $canViewCosts
                        ? '0.0000'
                        : null,
                ];
            }

            $report[$locationId]['recordCount'] += (int) $row->record_count;
            $report[$locationId]['quantityTotals'][] = $this->quantityTotal($row);

            if ($canViewCosts) {
                $report[$locationId]['totalCost'] = $this->addDecimal(
                    (string) $report[$locationId]['totalCost'],
                    $row->total_cost,
                    4,
                );
            }
        }

        $report = array_values($report);

        usort(
            $report,
            static fn (array $left, array $right): int => strcasecmp(
                $left['locationName'],
                $right['locationName'],
            ),
        );

        return $report;
    }

    /**
     * Add fixed aggregate values while avoiding protected cost selection when unauthorized.
     */
    private function addAggregateSelects(
        Builder $query,
        bool $canViewCosts,
    ): Builder {
        $query
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw(
                'SUM(waste_records.base_quantity) as total_quantity',
            );

        if ($canViewCosts) {
            $query->selectRaw(
                'SUM(waste_records.total_cost) as total_cost',
            );
        }

        return $query;
    }

    /**
     * Normalize one grouped base-UOM quantity into the report contract.
     *
     * @return WasteQuantityTotal
     */
    private function quantityTotal(stdClass $row): array
    {
        return [
            'baseUnitId' => (int) $row->base_unit_id,
            'quantity' => $this->fixedDecimal(
                $row->total_quantity,
                6,
            ),
            'unitSymbol' => (string) $row->base_unit_symbol,
        ];
    }

    /**
     * Add decimal aggregate values without converting through binary floating point.
     *
     * @param  int<0, max>  $scale
     */
    private function addDecimal(
        string $current,
        mixed $value,
        int $scale,
    ): string {
        return (string) BigDecimal::of($current)
            ->plus(BigDecimal::of((string) $value))
            ->toScale(
                $scale,
                RoundingMode::HalfUp,
            );
    }

    /**
     * Normalize database aggregate output to the persisted report precision.
     *
     * @param  int<0, max>  $scale
     */
    private function fixedDecimal(
        mixed $value,
        int $scale,
    ): string {
        return (string) BigDecimal::of((string) $value)
            ->toScale(
                $scale,
                RoundingMode::HalfUp,
            );
    }

    /**
     * Map one waste record to the stable report-row contract.
     *
     * @return WasteReportRow
     */
    private function wasteReportRow(
        WasteRecord $record,
        bool $canViewCosts,
    ): array {
        return [
            'recordId' => $record->id,
            'occurredAt' => $record->occurred_at
                ->toIso8601String(),
            'locationName' => $record->location->name,
            'storageLocationName' => $record
                ->storageLocation
                ->name,
            'itemName' => $record
                ->inventoryItem
                ->name,
            'itemSku' => $record
                ->inventoryItem
                ->sku,
            'reasonName' => $record
                ->wasteReason
                ->name,
            'quantity' => $record->quantity,
            'unitSymbol' => $record->unit->symbol,
            'baseQuantity' => $record->base_quantity,
            'baseUnitSymbol' => $record
                ->inventoryItem
                ->baseUnitOfMeasure
                ->symbol,
            'unitCost' => $canViewCosts
                ? $record->unit_cost
                : null,
            'totalCost' => $canViewCosts
                ? $record->total_cost
                : null,
            'recordedBy' => $record
                ->recorder
                ?->name,
            'notes' => $record->notes,
            'movementId' => $record
                ->movement
                ?->id,
        ];
    }

    /**
     * Build active tenant-owned options for new waste recording.
     *
     * @return array<string, mixed>
     */
    private function recordFormData(
        Organization $organization,
        ConvertQuantity $convertQuantity,
    ): array {
        $units = UnitOfMeasure::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->orderBy('name')
            ->get();

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
            ->get();

        return [
            'operationId' => (string) Str::uuid(),
            'defaultOccurredAt' => CarbonImmutable::now(
                $organization->timezone,
            )->format('Y-m-d\\TH:i'),
            'locationOptions' => Location::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(
                    static fn (
                        Location $location,
                    ): array => [
                        'id' => $location->id,
                        'name' => $location->name,
                    ],
                )
                ->values()
                ->all(),
            'storageLocationOptions' => StorageLocation::query()
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
                        StorageLocation $storage,
                    ): array => [
                        'id' => $storage->id,
                        'locationId' => $storage->location_id,
                        'name' => $storage->name,
                    ],
                )
                ->values()
                ->all(),
            'inventoryItemOptions' => $inventoryItems
                ->map(
                    fn (
                        InventoryItem $item,
                    ): array => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'baseUnitSymbol' => $item
                            ->baseUnitOfMeasure
                            ->symbol,
                        'validUnitIds' => $this->validUnitIds(
                            $organization,
                            $item,
                            $units,
                            $convertQuantity,
                        ),
                    ],
                )
                ->values()
                ->all(),
            'unitOptions' => $units
                ->map(
                    static fn (
                        UnitOfMeasure $unit,
                    ): array => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'symbol' => $unit->symbol,
                    ],
                )
                ->values()
                ->all(),
            'reasonOptions' => WasteReason::query()
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
                    static fn (
                        WasteReason $reason,
                    ): array => [
                        'id' => $reason->id,
                        'name' => $reason->name,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * Resolve selectable units through the existing authoritative converter.
     *
     * @param  Collection<int, UnitOfMeasure>  $units
     * @return list<int>
     */
    private function validUnitIds(
        Organization $organization,
        InventoryItem $item,
        Collection $units,
        ConvertQuantity $convertQuantity,
    ): array {
        return array_values(
            $units
                ->filter(
                    function (UnitOfMeasure $unit) use (
                        $organization,
                        $item,
                        $convertQuantity,
                    ): bool {
                        try {
                            $convertQuantity->handle(
                                $organization,
                                $item,
                                '0.000001',
                                $unit,
                                $item->baseUnitOfMeasure,
                            );

                            return true;
                        } catch (ValidationException) {
                            return false;
                        }
                    },
                )
                ->map(
                    static fn (UnitOfMeasure $unit): int => $unit->id,
                )
                ->all(),
        );
    }

    /**
     * Build historical filter options without hiding inactive master data.
     *
     * @return array<string, mixed>
     */
    private function reportOptions(
        Organization $organization,
    ): array {
        return [
            'locations' => Location::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(
                    static fn (
                        Location $location,
                    ): array => [
                        'id' => $location->id,
                        'name' => $location->name,
                    ],
                )
                ->values()
                ->all(),
            'inventoryCategories' => InventoryCategory::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(
                    static fn (
                        InventoryCategory $category,
                    ): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                    ],
                )
                ->values()
                ->all(),
            'inventoryItems' => InventoryItem::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'sku',
                ])
                ->map(
                    static fn (
                        InventoryItem $item,
                    ): array => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                    ],
                )
                ->values()
                ->all(),
            'wasteReasons' => WasteReason::query()
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
                    static fn (
                        WasteReason $reason,
                    ): array => [
                        'id' => $reason->id,
                        'name' => $reason->name,
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
