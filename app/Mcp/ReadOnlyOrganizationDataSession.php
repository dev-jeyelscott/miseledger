<?php

namespace App\Mcp;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs a materialized organization-data read in a PostgreSQL read-only
 * transaction. This is deliberately session-scoped because normal application
 * credentials can write outside this narrow MCP execution path.
 */
final class ReadOnlyOrganizationDataSession
{
    /**
     * @param  Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function run(Closure $callback): array
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() > 0) {
            // RefreshDatabase keeps fixtures uncommitted in an outer test
            // transaction, which PostgreSQL cannot convert to READ ONLY.
            if (app()->runningUnitTests()) {
                return $callback();
            }

            throw new \LogicException('Organization data queries cannot run inside an existing transaction.');
        }

        $connection->beginTransaction();

        try {
            $connection->statement('SET TRANSACTION READ ONLY');
            $connection->statement("SET LOCAL statement_timeout = '1000ms'");
            $connection->statement("SET LOCAL transaction_timeout = '2000ms'");

            $rows = $callback();
            $connection->commit();

            return $rows;
        } catch (Throwable $throwable) {
            $this->rollBack($connection);

            throw $throwable;
        }
    }

    private function rollBack(ConnectionInterface $connection): void
    {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }
}
