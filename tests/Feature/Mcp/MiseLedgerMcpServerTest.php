<?php

use App\Enums\AiRunStatus;
use App\Enums\OrganizationRole;
use App\Mcp\AiMcpExecutionContext;
use App\Mcp\AiMcpExecutionIdentityIssuer;
use App\Mcp\Servers\MiseLedgerMcpServer;
use App\Mcp\Tools\InventoryStockOnHandTool;
use App\Mcp\Tools\InventoryValuationTool;
use App\Mcp\Tools\OrganizationProfileTool;
use App\Mcp\Tools\PurchaseOrderSearchTool;
use App\Models\AiConversation;
use App\Models\AiRun;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

function establishMcpExecutionIdentity(): array
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
    app(AiMcpExecutionContext::class)->set(app(AiMcpExecutionIdentityIssuer::class)->issue($run));

    return [$organization, $user, $run];
}

test('the local MCP server exposes only its four typed read-only tools', function () {
    establishMcpExecutionIdentity();

    MiseLedgerMcpServer::tool(OrganizationProfileTool::class)->assertOk()->assertName('organization_profile');
    MiseLedgerMcpServer::tool(InventoryStockOnHandTool::class)->assertOk()->assertName('inventory_stock_on_hand');
    MiseLedgerMcpServer::tool(PurchaseOrderSearchTool::class)->assertOk()->assertName('procurement_purchase_orders');
    MiseLedgerMcpServer::tool(InventoryValuationTool::class)->assertOk()->assertName('reports_inventory_valuation');
});

test('the organization profile tool returns the worker-established organization\'s own identity', function () {
    [$organization] = establishMcpExecutionIdentity();

    MiseLedgerMcpServer::tool(OrganizationProfileTool::class)->assertOk()->assertStructuredContent([
        'name' => $organization->name,
        'slug' => $organization->slug,
        'timezone' => $organization->timezone,
        'currency' => $organization->currency,
    ]);
});

test('a tool rejects a filter identifier owned by another organization', function () {
    establishMcpExecutionIdentity();
    $foreignLocation = Location::factory()->create();

    MiseLedgerMcpServer::tool(InventoryStockOnHandTool::class, [
        'location_id' => $foreignLocation->id,
    ])->assertHasErrors(['selected location id is invalid']);
});

test('a tool enforces its row limit and records sanitized metadata', function () {
    [, , $run] = establishMcpExecutionIdentity();

    MiseLedgerMcpServer::tool(InventoryStockOnHandTool::class, [
        'limit' => 1001,
    ])->assertHasErrors(['limit']);

    MiseLedgerMcpServer::tool(InventoryStockOnHandTool::class, [
        'limit' => 1,
    ])->assertStructuredContent([
        'rows' => [],
        'metadata' => [
            'returned_row_count' => 0,
            'limit' => 1,
            'truncated' => false,
        ],
    ]);

    expect($run->toolCalls()->sole()->metadata)->toMatchArray([
        'resource_type' => 'stock_balance_report',
        'row_count' => 0,
        'truncated' => 'false',
    ]);
});
