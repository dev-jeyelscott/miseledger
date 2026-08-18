<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeStorageLocationForStockMovementLedgerRedesignTest(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = "Storage {$code}";
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

function recordWasteForStockMovementLedgerRedesignTest(
    Organization $organization,
    Location $location,
    StorageLocation $storageLocation,
    InventoryItem $item,
    UnitOfMeasure $unit,
    int $referenceId,
): StockMovement {
    return app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $item,
        type: StockMovementType::Waste,
        baseQuantity: '-2',
        baseUnitOfMeasure: $unit,
        referenceType: 'waste_record',
        referenceId: $referenceId,
        occurredAt: now(),
        idempotencyKey: "stock-movement-ledger-redesign:waste:{$referenceId}",
    );
}

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->storageLocation =
        makeStorageLocationForStockMovementLedgerRedesignTest(
            $this->organization,
            $this->location,
            'A',
        );

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'dimension' => 'weight',
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'name' => 'Ledger Redesign Item',
        'sku' => 'LEDGER-REDESIGN',
        'active' => true,
    ]);

    $this->manager = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->manager->id,
        'role' => OrganizationRole::Manager,
    ]);

    $this->staff = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->staff->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    $this->openingMovement = app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '10',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: $this->item->id,
        occurredAt: now(),
        actor: $this->manager,
        idempotencyKey: "stock-movement-ledger-redesign:opening:{$this->item->id}",
        inboundUnitCost: '4.0000',
    );
});

test('summary counts movements by persisted quantity sign without summing units', function () {
    recordWasteForStockMovementLedgerRedesignTest(
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        7001,
    );

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.stock-movements.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->where('summary.totalCount', 2)
                ->where('summary.inboundCount', 1)
                ->where('summary.outboundCount', 1)
                ->where('summary.wasteCount', 1),
        );
});

test('source reference filter matches the reference type and scopes the summary', function () {
    $wasteMovement = recordWasteForStockMovementLedgerRedesignTest(
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        7002,
    );

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.stock-movements.index', [
            'reference' => 'waste_record',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $wasteMovement->id)
                ->where('filters.reference', 'waste_record')
                ->where('summary.totalCount', 1)
                ->where('summary.inboundCount', 0)
                ->where('summary.outboundCount', 1)
                ->where('summary.wasteCount', 1),
        );
});

test('source reference filter matches a hash prefixed reference id', function () {
    $wasteMovement = recordWasteForStockMovementLedgerRedesignTest(
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        7003,
    );

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.stock-movements.index', [
            'reference' => '#7003',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $wasteMovement->id)
                ->where('rows.data.0.referenceId', 7003)
                ->where('filters.reference', '#7003'),
        );
});

test('summary remains isolated to the active organization', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorageLocation =
        makeStorageLocationForStockMovementLedgerRedesignTest(
            $otherOrganization,
            $otherLocation,
            'B',
        );

    $otherUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
        'dimension' => 'weight',
    ]);

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $otherOrganization->id,
        'base_unit_of_measure_id' => $otherUnit->id,
        'active' => true,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $otherOrganization,
        location: $otherLocation,
        storageLocation: $otherStorageLocation,
        inventoryItem: $otherItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '99',
        baseUnitOfMeasure: $otherUnit,
        referenceType: 'opening_balance',
        referenceId: $otherItem->id,
        occurredAt: now(),
        idempotencyKey: "stock-movement-ledger-redesign:tenant:{$otherItem->id}",
        inboundUnitCost: '9.0000',
    );

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.stock-movements.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $this->openingMovement->id)
                ->where('summary.totalCount', 1)
                ->where('summary.inboundCount', 1),
        );
});

test('csv export applies source reference filters and omits cost columns without permission', function () {
    recordWasteForStockMovementLedgerRedesignTest(
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        7004,
    );

    $content = $this
        ->actingAs($this->staff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.stock-movements.export', [
            'reference' => 'waste_record',
        ]))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain('waste_record');
    expect($content)->toContain('7004');
    expect($content)->not->toContain('opening_balance');
    expect($content)->not->toContain('Unit Cost');
    expect($content)->not->toContain('Total Cost');
    expect($content)->not->toContain('4.0000');
});
