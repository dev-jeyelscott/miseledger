<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\InventoryStockOnHandTool;
use App\Mcp\Tools\InventoryValuationTool;
use App\Mcp\Tools\OrganizationProfileTool;
use App\Mcp\Tools\PurchaseOrderSearchTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('miseledger')]
#[Version('1.0.0')]
#[Instructions('Use only the listed read-only organization profile, inventory, procurement, and report tools. Results are restricted to the worker-established organization and may be truncated. These tools are the only way to see this organization\'s real data — for any question about the organization, however phrased (its identity, inventory or stock levels including out-of-stock items with quantity_on_hand of 0, purchase orders, or valuation), always call the matching tool before answering, and never claim the data or an account lookup is unavailable without having tried the relevant tool first.')]
class MiseLedgerMcpServer extends Server
{
    protected array $tools = [
        OrganizationProfileTool::class,
        InventoryStockOnHandTool::class,
        PurchaseOrderSearchTool::class,
        InventoryValuationTool::class,
    ];
}
