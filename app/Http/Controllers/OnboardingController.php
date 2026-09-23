<?php

namespace App\Http\Controllers;

use App\Actions\Onboarding\CreateSelectedStandardUnits;
use App\Actions\Onboarding\ImportOnboardingInventory;
use App\Actions\Onboarding\MarkItemWithoutOpeningStock;
use App\Actions\Onboarding\RecordOnboardingOpeningStock;
use App\Actions\Organizations\CreateLocation;
use App\Enums\OnboardingStep;
use App\Enums\OnboardingStepStatus;
use App\Enums\OrganizationPermission;
use App\Http\Requests\Onboarding\OnboardingInventoryImportRequest;
use App\Http\Requests\Onboarding\ResolveOnboardingOpeningStockRequest;
use App\Http\Requests\Onboarding\StoreOnboardingLocationRequest;
use App\Http\Requests\Onboarding\StoreOnboardingUnitsRequest;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Billing\FeatureCode;
use App\Support\Billing\OrganizationFeatureEntitlement;
use App\Support\Inventory\StandardUnits;
use App\Support\Onboarding\OrganizationSetupReadiness;
use App\Support\Onboarding\OrganizationSetupStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Maximum number of items listed for opening-stock resolution at once.
     */
    private const OPENING_STOCK_ITEM_LIMIT = 200;

    /**
     * Show the server-derived setup checklist and the current step.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $organization = $request->attributes->get('activeOrganization');

        if (! $organization instanceof Organization) {
            return Inertia::render('onboarding/show', [
                'organization' => null,
                'operationId' => (string) Str::uuid(),
                'currentStep' => OnboardingStep::Organization->value,
                'ready' => false,
                'steps' => array_map(
                    static fn (OnboardingStep $step): array => [
                        'key' => $step->value,
                        'required' => $step->isRequired(),
                        'status' => $step === OnboardingStep::Organization
                            ? OnboardingStepStatus::NotStarted->value
                            : OnboardingStepStatus::Locked->value,
                    ],
                    OnboardingStep::cases(),
                ),
            ]);
        }

        $status = OrganizationSetupReadiness::resolve($organization);

        if ($status->justCompleted) {
            return $this->completed();
        }

        $requestedStep = OnboardingStep::tryFrom((string) $request->query('step', ''));

        return Inertia::render('onboarding/show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'operationId' => null,
            'currentStep' => ($requestedStep ?? $this->defaultStep($status))->value,
            'ready' => $status->ready,
            'steps' => array_map(
                static fn (OnboardingStep $step): array => [
                    'key' => $step->value,
                    'required' => $step->isRequired(),
                    'status' => $status->statusFor($step)->value,
                ],
                OnboardingStep::cases(),
            ),
            'permissions' => [
                'manageOrganization' => $this->allows($organization, OrganizationPermission::OrganizationManage),
                'manageLocations' => $this->allows($organization, OrganizationPermission::LocationsManage),
                'manageInventory' => $this->allows($organization, OrganizationPermission::InventoryAdjust),
                'managePurchasing' => $this->allows($organization, OrganizationPermission::PurchasingManage),
                'manageUsers' => $this->allows($organization, OrganizationPermission::UsersManage),
            ],
            'purchasingAvailable' => OrganizationFeatureEntitlement::isGranted(
                $organization,
                FeatureCode::Purchasing,
            ),
            'counts' => [
                'items' => $status->activeItemCount,
                'unresolvedOpeningStock' => $status->unresolvedOpeningStockCount,
                'suppliers' => $status->supplierCount,
                'supplierItems' => $status->supplierItemCount,
                'members' => $status->memberCount,
            ],
            'locations' => $this->locations($organization),
            'unitCatalog' => $this->unitCatalog($organization),
            'units' => $this->units($organization),
            'items' => $this->items($organization),
        ]);
    }

    /**
     * Create a setup location from a name only.
     */
    public function storeLocation(
        StoreOnboardingLocationRequest $request,
        CreateLocation $createLocation,
    ): RedirectResponse {
        $organization = $this->requestOrganization($request->organization());

        $name = (string) $request->validated('name');

        $createLocation->handle($organization, [
            'name' => $name,
            'code' => CreateLocation::deriveCode($organization, $name),
        ]);

        return $this->continueSetup(
            $organization,
            OnboardingStep::Units,
            __('Location created.'),
        );
    }

    /**
     * Create the explicitly selected standard units.
     */
    public function storeUnits(
        StoreOnboardingUnitsRequest $request,
        CreateSelectedStandardUnits $createSelectedStandardUnits,
    ): RedirectResponse {
        $organization = $this->requestOrganization($request->organization());

        /** @var list<string> $symbols */
        $symbols = $request->validated('symbols');

        $created = $createSelectedStandardUnits->handle($organization, $symbols);

        return $this->continueSetup(
            $organization,
            OnboardingStep::Inventory,
            trans_choice(
                '{0} Selected units are already available.|{1} :count unit added.|[2,*] :count units added.',
                $created,
                ['count' => $created],
            ),
        );
    }

    /**
     * Validate a setup CSV without saving anything.
     */
    public function previewInventoryImport(
        OnboardingInventoryImportRequest $request,
        ImportOnboardingInventory $importOnboardingInventory,
    ): RedirectResponse {
        $result = $this->runInventoryImport($request, $importOnboardingInventory, commit: false);

        Inertia::flash('inventoryImport', $result);

        return to_route('onboarding.show', ['step' => OnboardingStep::Inventory->value]);
    }

    /**
     * Import a setup CSV as one all-or-nothing unit.
     */
    public function importInventory(
        OnboardingInventoryImportRequest $request,
        ImportOnboardingInventory $importOnboardingInventory,
    ): RedirectResponse {
        $organization = $this->requestOrganization($request->organization());

        $result = $this->runInventoryImport($request, $importOnboardingInventory, commit: true);

        if (! $result['committed']) {
            Inertia::flash('inventoryImport', $result);
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Nothing was imported. Fix the listed rows and try again.'),
            ]);

            return to_route('onboarding.show', ['step' => OnboardingStep::Inventory->value]);
        }

        return $this->continueSetup(
            $organization,
            OnboardingStep::OpeningStock,
            __('Imported :created new and :updated updated items.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
            ]),
        );
    }

    /**
     * Resolve one item's opening stock with a quantity or an explicit
     * "no opening stock" decision.
     */
    public function resolveOpeningStock(
        ResolveOnboardingOpeningStockRequest $request,
        string $inventoryItem,
        RecordOnboardingOpeningStock $recordOnboardingOpeningStock,
        MarkItemWithoutOpeningStock $markItemWithoutOpeningStock,
    ): RedirectResponse {
        $organization = $this->requestOrganization($request->organization());
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $item = InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->findOrFail((int) $inventoryItem);

        if ($request->validated('resolution') === ResolveOnboardingOpeningStockRequest::RESOLUTION_NONE) {
            $markItemWithoutOpeningStock->handle($organization, $item, $actor);

            return $this->continueSetup(
                $organization,
                OnboardingStep::OpeningStock,
                __(':item marked as having no opening stock.', ['item' => $item->name]),
            );
        }

        $location = Location::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->findOrFail((int) $request->validated('location_id'));

        $recordOnboardingOpeningStock->handle(
            organization: $organization,
            inventoryItem: $item,
            location: $location,
            quantity: (string) $request->validated('quantity'),
            baseUnitCost: (string) $request->validated('base_unit_cost'),
            actor: $actor,
        );

        return $this->continueSetup(
            $organization,
            OnboardingStep::OpeningStock,
            __('Opening stock recorded for :item.', ['item' => $item->name]),
        );
    }

    /**
     * Skip the optional supplier step.
     */
    public function skipSuppliers(Request $request): RedirectResponse
    {
        $organization = $this->authorizeSkip($request);

        Organization::query()
            ->whereKey($organization->id)
            ->whereNull('onboarding_suppliers_skipped_at')
            ->update(['onboarding_suppliers_skipped_at' => now()]);

        $organization->refresh();

        return $this->continueSetup(
            $organization,
            null,
            __('Supplier setup skipped. You can add suppliers anytime.'),
        );
    }

    /**
     * Skip the optional team invitation step once minimum setup is complete.
     */
    public function skipTeam(Request $request): RedirectResponse
    {
        $organization = $this->authorizeSkip($request);

        if (! OrganizationSetupReadiness::isReady($organization)) {
            abort(409, __('Team invitations open after minimum setup is complete.'));
        }

        Organization::query()
            ->whereKey($organization->id)
            ->whereNull('onboarding_team_skipped_at')
            ->update(['onboarding_team_skipped_at' => now()]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Team invitations skipped. You can invite people anytime from Members.'),
        ]);

        return to_route('dashboard');
    }

    /**
     * Continue to the next step (or the first unfinished one when null), or
     * to the dashboard the moment minimum setup first becomes complete.
     */
    private function continueSetup(
        Organization $organization,
        ?OnboardingStep $nextStep,
        string $message,
    ): RedirectResponse {
        $status = OrganizationSetupReadiness::resolve($organization);

        if ($status->justCompleted) {
            return $this->completed();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return to_route(
            'onboarding.show',
            $nextStep === null ? [] : ['step' => $nextStep->value],
        );
    }

    private function completed(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Setup complete. Stock workflows are now available. You can still add suppliers and invite your team from Setup.'),
        ]);

        return to_route('dashboard');
    }

    /**
     * @return array{mode: string, committed: bool, created: int, updated: int, openingStockRecorded: int, rows: list<array<string, mixed>>, errors: list<array{row: int, messages: list<string>}>}
     */
    private function runInventoryImport(
        OnboardingInventoryImportRequest $request,
        ImportOnboardingInventory $importOnboardingInventory,
        bool $commit,
    ): array {
        $organization = $this->requestOrganization($request->organization());
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $location = Location::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->findOrFail((int) $request->validated('location_id'));

        $contents = (string) $request->file('file')?->get();

        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        return $importOnboardingInventory->handle(
            $organization,
            $actor,
            $location,
            $contents,
            $commit,
        );
    }

    /**
     * Skipping optional steps changes organization workflow state.
     */
    private function authorizeSkip(Request $request): Organization
    {
        $organization = $this->requestOrganization(
            $request->attributes->get('activeOrganization'),
        );

        Gate::authorize(
            OrganizationPermission::OrganizationManage->value,
            $organization,
        );

        return $organization;
    }

    private function requestOrganization(mixed $organization): Organization
    {
        if (! $organization instanceof Organization) {
            abort(403);
        }

        return $organization;
    }

    private function defaultStep(OrganizationSetupStatus $status): OnboardingStep
    {
        foreach (OnboardingStep::cases() as $step) {
            $stepStatus = $status->statusFor($step);

            if (
                $stepStatus !== OnboardingStepStatus::Complete
                && $stepStatus !== OnboardingStepStatus::Skipped
                && $stepStatus !== OnboardingStepStatus::Locked
            ) {
                return $step;
            }
        }

        return OnboardingStep::Organization;
    }

    private function allows(Organization $organization, OrganizationPermission $permission): bool
    {
        return Gate::allows($permission->value, $organization);
    }

    /**
     * @return array<int, array{id: int, name: string, code: string}>
     */
    private function locations(Organization $organization): array
    {
        return Location::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'code'])
            ->map(static fn (Location $location): array => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{symbol: string, name: string, dimension: string, added: bool}>
     */
    private function unitCatalog(Organization $organization): array
    {
        $existingSymbols = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->pluck('symbol')
            ->all();

        return array_map(
            static fn (array $definition): array => [
                'symbol' => $definition['symbol'],
                'name' => $definition['name'],
                'dimension' => $definition['dimension'],
                'added' => in_array($definition['symbol'], $existingSymbols, true),
            ],
            StandardUnits::definitions(),
        );
    }

    /**
     * @return array<int, array{id: int, name: string, symbol: string}>
     */
    private function units(Organization $organization): array
    {
        return UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'symbol'])
            ->map(static fn (UnitOfMeasure $unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
            ])
            ->values()
            ->all();
    }

    /**
     * Active items with their opening-stock resolution, unresolved first.
     *
     * @return array<int, array{id: int, name: string, sku: string, unitSymbol: string, openingStock: string}>
     */
    private function items(Organization $organization): array
    {
        return InventoryItem::query()
            ->with('baseUnitOfMeasure:id,symbol')
            ->withExists('stockMovements')
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderByRaw(
                'CASE WHEN opening_stock_waived_at IS NULL AND NOT EXISTS (SELECT 1 FROM stock_movements WHERE stock_movements.inventory_item_id = inventory_items.id) THEN 0 ELSE 1 END',
            )
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::OPENING_STOCK_ITEM_LIMIT)
            ->get()
            ->map(static fn (InventoryItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'unitSymbol' => $item->baseUnitOfMeasure->symbol,
                'openingStock' => match (true) {
                    (bool) $item->stock_movements_exists => 'recorded',
                    $item->opening_stock_waived_at !== null => 'none',
                    default => 'unresolved',
                },
            ])
            ->values()
            ->all();
    }
}
