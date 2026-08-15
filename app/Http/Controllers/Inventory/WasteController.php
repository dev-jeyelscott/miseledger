<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConvertQuantity;
use App\Actions\Inventory\RecordWaste;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RecordWasteRequest;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use App\Models\WasteRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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
            'inventoryItemId' => null,
            'wasteReasonId' => null,
            'from' => null,
            'to' => null,
        ];

        $rows = null;

        if ($canViewReport) {
            [$filters, $rows] = $this->reportData(
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
     * Build filters and a bounded tenant-scoped page of immutable waste rows.
     *
     * @return array{
     *     0: array<string, int|string|null>,
     *     1: LengthAwarePaginator<int, array<string, mixed>>
     * }
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

        $inventoryItemId =
            isset($validated['inventory_item_id'])
            ? (int) $validated['inventory_item_id']
            : null;

        $wasteReasonId =
            isset($validated['waste_reason_id'])
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

        $query = WasteRecord::query()
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
            );

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        if ($inventoryItemId !== null) {
            $query->where(
                'inventory_item_id',
                $inventoryItemId,
            );
        }

        if ($wasteReasonId !== null) {
            $query->where(
                'waste_reason_id',
                $wasteReasonId,
            );
        }

        if ($from !== null) {
            $query->where(
                'occurred_at',
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
                'occurred_at',
                '<=',
                CarbonImmutable::parse(
                    $to,
                    $organization->timezone,
                )
                    ->endOfDay()
                    ->utc(),
            );
        }

        $rows = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(
                static fn (
                    WasteRecord $record,
                ): array => [
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
                ],
            );

        return [
            [
                'locationId' => $locationId,
                'inventoryItemId' => $inventoryItemId,
                'wasteReasonId' => $wasteReasonId,
                'from' => $from,
                'to' => $to,
            ],
            $rows,
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
            )->format('Y-m-d\TH:i'),
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
