<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryItem;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryItemRequest;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryItemController extends Controller
{
    /**
     * Show inventory items belonging to the active organization.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryView->value,
            $organization,
        );

        $items = $organization
            ->inventoryItems()
            ->with('baseUnitOfMeasure:id,name,symbol')
            ->withCount('unitConversions')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (InventoryItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'active' => $item->active,
                    'conversionCount' => (
                        $item->unit_conversions_count ?? 0
                    ),
                    'baseUnitOfMeasure' => [
                        'id' => $item->baseUnitOfMeasure->id,
                        'name' => $item->baseUnitOfMeasure->name,
                        'symbol' => $item->baseUnitOfMeasure->symbol,
                        'active' => $item->baseUnitOfMeasure->active,
                    ],
                ],
            )
            ->values()
            ->all();

        return Inertia::render('inventory/items/index', [
            'items' => $items,
            'canManage' => Gate::allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            ),
        ]);
    }

    /**
     * Show the inventory-item creation form.
     */
    public function create(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        return Inertia::render('inventory/items/create', [
            'units' => $this->activeUnitOptions($organization),
        ]);
    }

    /**
     * Persist a new inventory item.
     */
    public function store(
        SaveInventoryItemRequest $request,
        SaveInventoryItem $saveInventoryItem,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        $item = $saveInventoryItem->handle(
            $organization,
            [
                'name' => (string) $request->validated('name'),
                'sku' => (string) $request->validated('sku'),
                'base_unit_of_measure_id' => (int) $request->validated(
                    'base_unit_of_measure_id',
                ),
                'active' => (bool) $request->validated('active'),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Inventory item created.'),
        ]);

        return to_route('inventory.items.edit', $item);
    }

    /**
     * Show inventory-item and alternate-UOM configuration.
     */
    public function edit(
        Request $request,
        string $inventoryItem,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        $item = $organization
            ->inventoryItems()
            ->with([
                'baseUnitOfMeasure:id,name,symbol,active',
                'unitConversions' => fn ($query) => $query
                    ->with('unitOfMeasure:id,name,symbol,active')
                    ->orderBy('id'),
            ])
            ->findOrFail($inventoryItem);

        $activeUnits = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $usedUnitIds = $item
            ->unitConversions
            ->pluck('unit_of_measure_id')
            ->all();

        $availableConversionUnits = $activeUnits
            ->reject(
                static fn (UnitOfMeasure $unit): bool => (
                    $unit->id === $item->base_unit_of_measure_id
                    || in_array($unit->id, $usedUnitIds, true)
                ),
            )
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

        return Inertia::render('inventory/items/edit', [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'active' => $item->active,
                'baseUnitOfMeasure' => [
                    'id' => $item->baseUnitOfMeasure->id,
                    'name' => $item->baseUnitOfMeasure->name,
                    'symbol' => $item->baseUnitOfMeasure->symbol,
                    'active' => $item->baseUnitOfMeasure->active,
                ],
                'unitConversions' => $item
                    ->unitConversions
                    ->map(
                        static fn (
                            InventoryItemUnit $conversion,
                        ): array => [
                            'id' => $conversion->id,
                            'quantityInBaseUnit' => (
                                $conversion->quantity_in_base_unit
                            ),
                            'active' => $conversion->active,
                            'unitOfMeasure' => [
                                'id' => $conversion->unitOfMeasure->id,
                                'name' => $conversion
                                    ->unitOfMeasure
                                    ->name,
                                'symbol' => $conversion
                                    ->unitOfMeasure
                                    ->symbol,
                                'active' => $conversion
                                    ->unitOfMeasure
                                    ->active,
                            ],
                        ],
                    )
                    ->values()
                    ->all(),
            ],
            'units' => $activeUnits
                ->map(
                    static fn (UnitOfMeasure $unit): array => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'symbol' => $unit->symbol,
                        'active' => $unit->active,
                    ],
                )
                ->values()
                ->all(),
            'availableConversionUnits' => (
                $availableConversionUnits
            ),
        ]);
    }

    /**
     * Update an inventory item.
     */
    public function update(
        SaveInventoryItemRequest $request,
        string $inventoryItem,
        SaveInventoryItem $saveInventoryItem,
    ): RedirectResponse {
        $organization = $request->organization();
        $item = $request->inventoryItem();

        if ($organization === null || $item === null) {
            abort(403);
        }

        $saveInventoryItem->handle(
            $organization,
            [
                'name' => (string) $request->validated('name'),
                'sku' => (string) $request->validated('sku'),
                'base_unit_of_measure_id' => (int) $request->validated(
                    'base_unit_of_measure_id',
                ),
                'active' => (bool) $request->validated('active'),
            ],
            $item,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Inventory item updated.'),
        ]);

        return to_route(
            'inventory.items.edit',
            $inventoryItem,
        );
    }

    /**
     * Build active UOM choices for item forms.
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     symbol: string,
     *     active: bool
     * }>
     */
    private function activeUnitOptions(
        Organization $organization,
    ): array {
        $units = $organization
            ->unitsOfMeasure()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(
                static fn (UnitOfMeasure $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'active' => $unit->active,
                ],
            )
            ->all();

        return array_values($units);
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
