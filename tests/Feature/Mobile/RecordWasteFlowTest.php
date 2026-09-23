<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WasteReason;
use App\Models\WasteRecord;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $storage = new StorageLocation;
    $storage->organization_id = $this->organization->id;
    $storage->location_id = $this->location->id;
    $storage->name = 'Main Storage';
    $storage->code = 'MAIN';
    $storage->active = true;
    $storage->save();
    $this->storage = $storage;

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Mobile Waste Item',
        'sku' => 'MOBILE-WASTE',
        'active' => true,
    ]);

    $this->reason = WasteReason::query()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Spoilage',
        'active' => true,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storage,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '1000',
        baseUnitOfMeasure: $this->baseUnit,
        referenceType: 'opening_balance',
        referenceId: $this->item->id,
        occurredAt: now()->subHour(),
        idempotencyKey: "mobile-waste-test:opening:{$this->item->id}",
        inboundUnitCost: '0.25',
    );

    $this->kitchenUser = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->kitchenUser->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $this->unauthorizedUser = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->unauthorizedUser->id,
        'role' => OrganizationRole::Auditor,
    ]);
});

function actAsMobileWasteUser(User $user, Organization $organization, Location $location): void
{
    test()->actingAs($user)->withSession([
        'active_organization_id' => $organization->id,
        'mobile_location_by_org' => [$organization->id => $location->id],
    ]);
}

/**
 * @return array<string, mixed>
 */
function mobileWastePayload(
    InventoryItem $item,
    StorageLocation $storage,
    WasteReason $reason,
    UnitOfMeasure $unit,
    ?string $operationId = null,
): array {
    return [
        'operation_id' => $operationId ?? (string) Str::uuid(),
        'storage_location_id' => $storage->id,
        'inventory_item_id' => $item->id,
        'waste_reason_id' => $reason->id,
        'quantity' => '0.5',
        'unit_id' => $unit->id,
        'notes' => 'Mobile waste test',
    ];
}

test('the record screen renders the resolved item, storage options, and reasons', function () {
    actAsMobileWasteUser($this->kitchenUser, $this->organization, $this->location);

    $this->get("/mobile/waste?item_id={$this->item->id}")
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('mobile/waste/record')
                ->where('item.inventoryItemId', $this->item->id)
                ->has('storageLocationOptions', 1)
                ->where('storageLocationOptions.0.id', $this->storage->id)
                ->has('wasteReasonOptions', 1)
                ->where('wasteReasonOptions.0.id', $this->reason->id)
                ->has('operationId'),
        );
});

test('recording waste from mobile produces the identical WasteRecord/StockMovement shape as desktop', function () {
    actAsMobileWasteUser($this->kitchenUser, $this->organization, $this->location);

    $payload = mobileWastePayload($this->item, $this->storage, $this->reason, $this->baseUnit);

    $this->post('/mobile/waste', $payload)->assertRedirect('/mobile/scan');

    $record = WasteRecord::query()
        ->where('organization_id', $this->organization->id)
        ->sole();

    $movement = StockMovement::query()
        ->where('type', StockMovementType::Waste->value)
        ->sole();

    expect($record->location_id)->toBe($this->location->id)
        ->and($record->storage_location_id)->toBe($this->storage->id)
        ->and($record->inventory_item_id)->toBe($this->item->id)
        ->and($record->waste_reason_id)->toBe($this->reason->id)
        ->and($record->quantity)->toBe('0.500000')
        ->and($record->base_quantity)->toBe('0.500000')
        ->and($record->unit_cost)->toBe('0.2500')
        ->and($record->total_cost)->toBe('0.1250')
        ->and($record->recorded_by)->toBe($this->kitchenUser->id)
        ->and($movement->reference_type)->toBe('waste_record')
        ->and($movement->reference_id)->toBe($record->id)
        ->and($movement->quantity)->toBe('-0.500000')
        ->and(
            StockBalance::query()->sole()->quantity_on_hand,
        )->toBe('999.500000');
});

test('duplicate operation_id retry returns the existing record without a second movement', function () {
    actAsMobileWasteUser($this->kitchenUser, $this->organization, $this->location);

    $operationId = (string) Str::uuid();
    $payload = mobileWastePayload($this->item, $this->storage, $this->reason, $this->baseUnit, $operationId);

    $this->post('/mobile/waste', $payload)->assertRedirect('/mobile/scan');
    $this->post('/mobile/waste', $payload)->assertRedirect('/mobile/scan');

    expect(WasteRecord::query()->count())->toBe(1)
        ->and(
            StockMovement::query()
                ->where('type', StockMovementType::Waste->value)
                ->count(),
        )->toBe(1);
});

test('KitchenStaff can record waste', function () {
    actAsMobileWasteUser($this->kitchenUser, $this->organization, $this->location);

    $payload = mobileWastePayload($this->item, $this->storage, $this->reason, $this->baseUnit);

    $this->post('/mobile/waste', $payload)->assertRedirect('/mobile/scan');

    expect(WasteRecord::query()->count())->toBe(1);
});

test('a role without WasteRecord is rejected with 403', function () {
    actAsMobileWasteUser($this->unauthorizedUser, $this->organization, $this->location);

    $payload = mobileWastePayload($this->item, $this->storage, $this->reason, $this->baseUnit);

    $this->post('/mobile/waste', $payload)->assertForbidden();

    expect(WasteRecord::query()->count())->toBe(0);

    $this->get("/mobile/waste?item_id={$this->item->id}")->assertForbidden();
});
