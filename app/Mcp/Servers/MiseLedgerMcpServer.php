<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\InventoryStockOnHandTool;
use App\Mcp\Tools\InventoryValuationTool;
use App\Mcp\Tools\PurchaseOrderSearchTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('miseledger')]
#[Version('1.0.0')]
#[Instructions('Use only the listed read-only organization inventory, procurement, and report tools. Results are restricted to the worker-established organization and may be truncated.')]
class MiseLedgerMcpServer extends Server
{
    protected array $tools = [
        InventoryStockOnHandTool::class,
        PurchaseOrderSearchTool::class,
        InventoryValuationTool::class,
    ];
}
