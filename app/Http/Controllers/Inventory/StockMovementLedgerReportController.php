<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\OrganizationPermission;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementLedgerReportController extends Controller
{
    /**
     * Report the immutable stock movement ledger, scoped to the active
     * tenant, preserving append order and movement traceability.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

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
            'type' => [
                'nullable',
                Rule::enum(StockMovementType::class),
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

        $storageLocationId = isset($validated['storage_location_id'])
            ? (int) $validated['storage_location_id']
            : null;

        $itemId = isset($validated['inventory_item_id'])
            ? (int) $validated['inventory_item_id']
            : null;

        $type = isset($validated['type']) && is_string($validated['type'])
            ? StockMovementType::from($validated['type'])
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

        $query = StockMovement::query()
            ->with([
                'location:id,name',
                'storageLocation:id,name',
                'inventoryItem:id,name,sku',
                'baseUnitOfMeasure:id,symbol',
                'creator:id,name',
            ])
            ->where('organization_id', $organization->id);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        if ($storageLocationId !== null) {
            $query->where('storage_location_id', $storageLocationId);
        }

        if ($itemId !== null) {
            $query->where('inventory_item_id', $itemId);
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($from !== null) {
            $query->whereDate('occurred_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('occurred_at', '<=', $to);
        }

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        $rows = $query
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(
                fn (StockMovement $movement): array => [
                    'id' => $movement->id,
                    'occurredAt' => $movement->occurred_at->toIso8601String(),
                    'locationId' => $movement->location_id,
                    'locationName' => $movement->location->name,
                    'storageLocationId' => $movement->storage_location_id,
                    'storageLocationName' => $movement->storageLocation->name,
                    'itemId' => $movement->inventory_item_id,
                    'itemName' => $movement->inventoryItem->name,
                    'itemSku' => $movement->inventoryItem->sku,
                    'type' => $movement->type->value,
                    'quantity' => (string) $movement->quantity,
                    'baseUnitSymbol' => $movement->baseUnitOfMeasure->symbol,
                    'unitCost' => $canViewCosts
                        ? $movement->unit_cost === null
                            ? null
                            : (string) $movement->unit_cost
                        : null,
                    'totalCost' => $canViewCosts
                        ? $movement->total_cost === null
                            ? null
                            : (string) $movement->total_cost
                        : null,
                    'referenceType' => $movement->reference_type,
                    'referenceId' => $movement->reference_id,
                    'actorName' => $movement->creator?->name,
                ],
            )
            ->toArray();

        return Inertia::render('inventory/stock-movement-ledger', [
            'rows' => $rows,
            'locationOptions' => $this->locationOptions($organization),
            'storageLocationOptions' => $this->storageLocationOptions(
                $organization,
                $locationId,
            ),
            'itemOptions' => $this->itemOptions($organization),
            'typeOptions' => $this->typeOptions(),
            'filters' => [
                'locationId' => $locationId,
                'storageLocationId' => $storageLocationId,
                'inventoryItemId' => $itemId,
                'type' => $type?->value,
                'from' => $from,
                'to' => $to,
            ],
            'currency' => $organization->currency,
            'canViewCosts' => $canViewCosts,
        ]);
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

    /**
     * @return list<array{id: int, name: string}>
     */
    private function storageLocationOptions(
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
                ->get(['id', 'name'])
                ->map(
                    static fn (StorageLocation $storageLocation): array => [
                        'id' => $storageLocation->id,
                        'name' => $storageLocation->name,
                    ],
                )
                ->all(),
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function itemOptions(Organization $organization): array
    {
        return array_values(
            InventoryItem::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(
                    static fn (InventoryItem $item): array => [
                        'id' => $item->id,
                        'name' => $item->name,
                    ],
                )
                ->all(),
        );
    }

    /**
     * @return list<string>
     */
    private function typeOptions(): array
    {
        return array_map(
            static fn (StockMovementType $type): string => $type->value,
            StockMovementType::cases(),
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
