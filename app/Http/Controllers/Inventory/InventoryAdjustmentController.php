<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustInventory;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RecordInventoryAdjustmentRequest;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InventoryAdjustmentController extends Controller
{
    /**
     * Show the privileged manual stock-correction workflow.
     */
    public function create(Request $request): Response
    {
        $organization = $this->activeOrganization(
            $request,
        );

        Gate::authorize(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        return Inertia::render(
            'inventory/adjustments/create',
            [
                'operationId' => (string) Str::uuid(),
                'defaultOccurredAt' => CarbonImmutable::now(
                    $organization->timezone,
                )->format('Y-m-d\TH:i'),
                'timezone' => $organization->timezone,
                ...$this->formOptions($organization),
            ],
        );
    }

    /**
     * Record one audited manual adjustment through the shared stock ledger boundary.
     */
    public function store(
        RecordInventoryAdjustmentRequest $request,
        AdjustInventory $adjustInventory,
    ): RedirectResponse {
        $organization = $request->organization();
        $actor = $request->user();

        if (
            $organization === null
            || ! $actor instanceof User
        ) {
            abort(403);
        }

        $validated = $request->validated();

        $location = Location::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->findOrFail(
                (int) $validated['location_id'],
            );

        $storageLocation = StorageLocation::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where(
                'location_id',
                $location->id,
            )
            ->where('active', true)
            ->findOrFail(
                (int) $validated['storage_location_id'],
            );

        $inventoryItem = InventoryItem::query()
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
            ->findOrFail(
                (int) $validated['inventory_item_id'],
            );

        $unit = UnitOfMeasure::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->findOrFail(
                (int) $validated['unit_id'],
            );

        $movement = $adjustInventory->handle(
            organization: $organization,
            location: $location,
            storageLocation: $storageLocation,
            inventoryItem: $inventoryItem,
            quantity: (string) $validated['quantity'],
            unit: $unit,
            reason: (string) $validated['reason'],
            referenceType: 'manual_inventory_adjustment',
            referenceId: $inventoryItem->id,
            occurredAt: CarbonImmutable::parse(
                (string) $validated['occurred_at'],
                $organization->timezone,
            )->utc(),
            actor: $actor,
            idempotencyKey: 'inventory_adjustment:manual:'
                .(string) $validated['operation_id'],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(
                'Inventory adjustment #:id recorded for :item.',
                [
                    'id' => $movement->id,
                    'item' => $inventoryItem->name,
                ],
            ),
        ]);

        return to_route('inventory.items.index');
    }

    /**
     * Build active tenant-owned adjustment selections.
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
            ->get([
                'id',
                'name',
            ])
            ->map(
                static fn (
                    Location $location,
                ): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                ],
            )
            ->values()
            ->all();

        $storageLocations = StorageLocation::query()
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
            ->with('baseUnitOfMeasure:id,name,symbol')
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
                static fn (
                    UnitOfMeasure $unit,
                ): array => [
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
