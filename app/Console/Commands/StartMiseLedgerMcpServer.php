<?php

namespace App\Console\Commands;

use App\Mcp\AiMcpExecutionContext;
use Illuminate\Console\Command;
use Laravel\Mcp\Server\Registrar;

class StartMiseLedgerMcpServer extends Command
{
    protected $signature = 'ai:mcp:start {--execution-identity= : A worker-issued signed AI execution identity}';

    protected $description = 'Start MiseLedger\'s worker-authenticated local MCP server.';

    /**
     * Execute the console command.
     */
    public function handle(
        AiMcpExecutionContext $context,
        Registrar $registrar,
    ): int {
        $signedIdentity = $this->option('execution-identity');

        if (! is_string($signedIdentity) || $signedIdentity === '') {
            $this->components->error('A worker-issued --execution-identity is required.');

            return self::FAILURE;
        }

        $context->set($signedIdentity);

        $server = $registrar->getLocalServer('miseledger');

        if ($server === null) {
            $this->components->error('The local MiseLedger MCP server is not registered.');

            return self::FAILURE;
        }

        $server();

        return self::SUCCESS;
    }
}
