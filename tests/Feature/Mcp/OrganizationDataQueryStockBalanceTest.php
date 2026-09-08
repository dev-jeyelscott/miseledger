<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\AiRunStatus;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Mcp\AiMcpExecutionContext;
use App\Mcp\AiMcpExecutionIdentityIssuer;
use App\Mcp\Servers\MiseLedgerMcpServer;
use App\Mcp\Tools\OrganizationDataQueryTool;
use App\Models\AiConversation;
use App\Models\AiRun;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StorageLocation;
use App\Models\User;

/**
 * Establish a valid organization-scoped identity for MCP stock-query tests.
 *
 * @return array{Organization, User}
 */
function establishStockQueryMcpIdentity(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::Manager,
        'ai_enabled' => true,
    ]);

    $conversation = AiConversation::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    $run = AiRun::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'status' => AiRunStatus::Running,
    ]);

    app(AiMcpExecutionContext::class)->set(
        app(AiMcpExecutionIdentityIssuer::class)->issue($run),
    );

    return [$organization, $user];
}

/**
 * Create an active storage location inside the given organization and location.
 */
function createStockQueryStorage(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storageLocation = new StorageLocation;

    $storageLocation->forceFill([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'name' => 'Storage '.$code,
        'code' => $code,
        'active' => true,
    ])->save();

    return $storageLocation->refresh();
}

/**
 * Record an opening balance through the authoritative stock-ledger action.
 */
function recordStockQueryOpeningBalance(
    Organization $organization,
    Location $location,
    StorageLocation $storageLocation,
    InventoryItem $item,
    string $quantity,
    int $referenceId,
): void {
    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: $quantity,
        baseUnitOfMeasure: $item->baseUnitOfMeasure()->firstOrFail(),
        referenceType: 'McpStockQueryTest',
        referenceId: $referenceId,
        occurredAt: now()->subSecond(),
        inboundUnitCost: '1.0000',
    );
}

/**
 * Reduce an existing balance through the authoritative stock-ledger action.
 */
function recordStockQueryWaste(
    Organization $organization,
    Location $location,
    StorageLocation $storageLocation,
    InventoryItem $item,
    string $quantity,
    int $referenceId,
): void {
    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $item,
        type: StockMovementType::Waste,
        baseQuantity: $quantity,
        baseUnitOfMeasure: $item->baseUnitOfMeasure()->firstOrFail(),
        referenceType: 'McpStockQueryTest',
        referenceId: $referenceId,
        occurredAt: now(),
    );
}

test('stock balance queries can return zero quantity rows with their requested item relation', function (): void {
    [$organization] = establishStockQueryMcpIdentity();

    $location = Location::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $storageLocation = createStockQueryStorage(
        $organization,
        $location,
        'ZERO',
    );

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Zero Stock Item',
        'sku' => 'ZERO-STOCK',
    ]);

    recordStockQueryOpeningBalance(
        $organization,
        $location,
        $storageLocation,
        $item,
        '5.000000',
        1,
    );

    recordStockQueryWaste(
        $organization,
        $location,
        $storageLocation,
        $item,
        '-5.000000',
        2,
    );

    MiseLedgerMcpServer::tool(
        OrganizationDataQueryTool::class,
        [
            'resource' => 'stock_balances',
            'fields' => ['quantity_on_hand'],
            'filters' => [
                [
                    'field' => 'quantity_on_hand',
                    'operator' => 'eq',
                    'value' => '0',
                ],
            ],
            'relations' => ['item'],
        ],
    )
        ->assertOk()
        ->assertStructuredContent([
            'rows' => [
                [
                    'quantity_on_hand' => '0.000000',
                    'item' => [
                        'id' => $item->id,
                        'name' => 'Zero Stock Item',
                        'sku' => 'ZERO-STOCK',
                    ],
                ],
            ],
            'metadata' => [
                'resource' => 'stock_balances',
                'page' => 1,
                'limit' => 50,
                'returned_row_count' => 1,
                'truncated' => false,
            ],
        ]);
});

test('stock balance aggregates sum current quantity by item without crossing organizations', function (): void {
    [$organization] = establishStockQueryMcpIdentity();

    $location = Location::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $firstStorage = createStockQueryStorage(
        $organization,
        $location,
        'FIRST',
    );

    $secondStorage = createStockQueryStorage(
        $organization,
        $location,
        'SECOND',
    );

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
    ]);

    recordStockQueryOpeningBalance(
        $organization,
        $location,
        $firstStorage,
        $item,
        '2.000000',
        10,
    );

    recordStockQueryOpeningBalance(
        $organization,
        $location,
        $secondStorage,
        $item,
        '3.000000',
        11,
    );

    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $otherStorage = createStockQueryStorage(
        $otherOrganization,
        $otherLocation,
        'OTHER',
    );

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    recordStockQueryOpeningBalance(
        $otherOrganization,
        $otherLocation,
        $otherStorage,
        $otherItem,
        '99.000000',
        12,
    );

    MiseLedgerMcpServer::tool(
        OrganizationDataQueryTool::class,
        [
            'operation' => 'aggregate',
            'resource' => 'stock_balances',
            'aggregates' => [
                [
                    'operation' => 'sum',
                    'field' => 'quantity_on_hand',
                ],
            ],
            'group_by' => ['item_id'],
            'sort' => [
                [
                    'field' => 'item_id',
                    'direction' => 'asc',
                ],
            ],
        ],
    )
        ->assertOk()
        ->assertStructuredContent([
            'rows' => [
                [
                    'item_id' => $item->id,
                    'sum_quantity_on_hand' => '5.000000',
                ],
            ],
            'metadata' => [
                'resource' => 'stock_balances',
                'page' => 1,
                'limit' => 50,
                'returned_row_count' => 1,
                'truncated' => false,
            ],
        ]);
});

test('sum aggregation rejects fields that the catalog has not marked as summable', function (): void {
    establishStockQueryMcpIdentity();

    MiseLedgerMcpServer::tool(
        OrganizationDataQueryTool::class,
        [
            'operation' => 'aggregate',
            'resource' => 'inventory_items',
            'aggregates' => [
                [
                    'operation' => 'sum',
                    'field' => 'name',
                ],
            ],
        ],
    )->assertHasErrors();
});
