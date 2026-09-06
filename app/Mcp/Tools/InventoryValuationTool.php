<?php

namespace App\Mcp\Tools;

use App\Enums\OrganizationPermission;
use App\Mcp\AiMcpExecutionIdentityResolver;
use App\Models\StockBalance;
use App\Support\Inventory\StockBalanceReportQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('reports_inventory_valuation')]
#[Description('Read bounded current inventory valuation rows for the worker-established organization. Monetary fields require costs.view permission.')]
#[IsReadOnly]
final class InventoryValuationTool extends MiseLedgerReadTool
{
    protected function requiredPermission(): OrganizationPermission
    {
        return OrganizationPermission::ReportsView;
    }

    public function handle(Request $request, AiMcpExecutionIdentityResolver $identityResolver, StockBalanceReportQuery $stockBalanceReportQuery): ResponseFactory
    {
        $startedAt = hrtime(true);
        $identity = $this->authorize($identityResolver);
        $validated = $request->validate(['location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('organization_id', $identity->organization->id)], 'inventory_category_id' => ['nullable', 'integer', Rule::exists('inventory_categories', 'id')->where('organization_id', $identity->organization->id)], 'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT]]);
        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $canViewCosts = Gate::forUser($identity->user)->allows(OrganizationPermission::CostsView->value, $identity->organization);
        $rows = $stockBalanceReportQuery->valuation($identity->organization, isset($validated['location_id']) ? (int) $validated['location_id'] : null, isset($validated['inventory_category_id']) ? (int) $validated['inventory_category_id'] : null)->orderBy('location_id')->orderBy('inventory_item_id')->limit($limit + 1)->get()
            ->map(static fn (StockBalance $balance): array => ['location' => $balance->location->name, 'item_id' => $balance->inventory_item_id, 'item' => $balance->inventoryItem->name, 'sku' => $balance->inventoryItem->sku, 'category' => $balance->inventoryItem->inventoryCategory?->name, 'quantity_on_hand' => $balance->quantity_on_hand, 'unit' => $balance->inventoryItem->baseUnitOfMeasure->symbol, 'average_unit_cost' => $canViewCosts ? $balance->average_unit_cost : null, 'inventory_value' => $canViewCosts ? $balance->inventory_value : null])->all();
        $result = $this->boundedRows($rows, $limit);
        $this->recordAudit($identity, 'succeeded', $startedAt, ['resource_type' => 'inventory_valuation_report', 'row_count' => $result['metadata']['returned_row_count'], 'truncated' => $result['metadata']['truncated'] ? 'true' : 'false']);

        return Response::structured($result);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['location_id' => $schema->integer()->min(1)->description('Organization location ID.'), 'inventory_category_id' => $schema->integer()->min(1)->description('Organization inventory category ID.'), 'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(self::DEFAULT_LIMIT)->description('Maximum rows, capped at 1000.')];
    }
}
