<?php

use App\Mcp\Servers\MiseLedgerMcpServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('miseledger', MiseLedgerMcpServer::class);
