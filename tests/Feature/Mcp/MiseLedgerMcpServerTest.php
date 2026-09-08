<?php

use App\Enums\AiRunStatus;
use App\Enums\OrganizationRole;
use App\Mcp\AiMcpExecutionContext;
use App\Mcp\AiMcpExecutionIdentityIssuer;
use App\Mcp\Servers\MiseLedgerMcpServer;
use App\Mcp\Tools\OrganizationDataQueryTool;
use App\Models\AiConversation;
use App\Models\AiRun;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Supplier;
use App\Models\User;
use Laravel\Mcp\Server\Transport\FakeTransporter;

function establishMcpExecutionIdentity(OrganizationRole $role = OrganizationRole::Manager): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => $role,
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

test('the local MCP server exposes only the authoritative organization data query tool', function () {
    establishMcpExecutionIdentity();

    $server = app()->make(MiseLedgerMcpServer::class, ['transport' => new FakeTransporter]);

    expect($server->createContext()->tools()->map->name()->all())->toBe(['organization_data_query']);
    expect(app(OrganizationDataQueryTool::class)->name())->toBe('organization_data_query');
});

test('it returns only the worker-established organization inventory rows and bounded metadata', function () {
    [$organization, , $run] = establishMcpExecutionIdentity();
    $item = InventoryItem::factory()->create(['organization_id' => $organization->id]);
    InventoryItem::factory()->create();

    MiseLedgerMcpServer::tool(OrganizationDataQueryTool::class, [
        'resource' => 'inventory_items',
        'fields' => ['id', 'name', 'sku'],
        'limit' => 1,
    ])->assertOk()->assertStructuredContent([
        'rows' => [[
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
        ]],
        'metadata' => [
            'resource' => 'inventory_items',
            'page' => 1,
            'limit' => 1,
            'returned_row_count' => 1,
            'truncated' => false,
        ],
    ]);

    expect($run->toolCalls()->sole()->metadata)->toMatchArray([
        'resource_type' => 'inventory_items',
        'outcome' => 'succeeded',
        'row_count' => 1,
        'truncated' => 'false',
    ]);
});

test('it rejects organization IDs, unknown keys, SQL-shaped fields, and statement-shaped values', function () {
    establishMcpExecutionIdentity();

    foreach ([
        ['resource' => 'inventory_items', 'organization_id' => 1],
        ['resource' => 'inventory_items', 'fields' => ['organization_id']],
        ['resource' => 'inventory_items', 'filters' => [['field' => 'id; drop table organizations', 'operator' => 'eq', 'value' => 1]]],
        ['resource' => 'inventory_items', 'filters' => [['field' => 'id', 'operator' => 'eq', 'value' => ['select' => 'organizations']]]],
    ] as $arguments) {
        MiseLedgerMcpServer::tool(OrganizationDataQueryTool::class, $arguments)->assertHasErrors();
    }
});

test('it requires costs permission before selecting protected procurement data', function () {
    [$organization] = establishMcpExecutionIdentity(OrganizationRole::InventoryStaff);
    Supplier::factory()->create(['organization_id' => $organization->id]);

    MiseLedgerMcpServer::tool(OrganizationDataQueryTool::class, [
        'resource' => 'suppliers',
        'fields' => ['id', 'name'],
    ])->assertOk();

    MiseLedgerMcpServer::tool(OrganizationDataQueryTool::class, [
        'resource' => 'purchase_orders',
        'fields' => ['id', 'total'],
    ])->assertHasErrors(['Cost and valuation fields require costs.view permission.']);
});

test('it records rejected requests without retaining arguments or result data', function () {
    [, , $run] = establishMcpExecutionIdentity();

    MiseLedgerMcpServer::tool(OrganizationDataQueryTool::class, [
        'resource' => 'inventory_items',
        'fields' => ['organization_id'],
    ])->assertHasErrors();

    expect($run->toolCalls()->sole()->metadata)
        ->toMatchArray(['outcome' => 'rejected', 'error_code' => 'invalid_request'])
        ->not->toHaveKey('fields')
        ->not->toHaveKey('rows');
});
