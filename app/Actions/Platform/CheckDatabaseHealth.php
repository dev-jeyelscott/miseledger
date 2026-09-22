<?php

namespace App\Actions\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bounded, allowlisted PostgreSQL connectivity/version/size/capacity probe
 * for the read-only Platform Health page (POC-V8.2). Every statement here is
 * fixed: no operator- or user-entered value ever reaches SQL, and there is
 * no way to reach this class from a route that accepts arbitrary SQL.
 */
final class CheckDatabaseHealth
{
    /**
     * @return array{
     *     status: string,
     *     source: string,
     *     checkedAt: string,
     *     serverVersion: string|null,
     *     databaseSizeBytes: int|null,
     *     activeConnections: int|null,
     *     maxConnections: int|null,
     * }
     */
    public function handle(): array
    {
        $checkedAt = Carbon::now()->toIso8601String();

        try {
            $connection = DB::connection();
            $connection->beginTransaction();

            try {
                $connection->statement('SET TRANSACTION READ ONLY');
                $connection->statement("SET LOCAL statement_timeout = '".((int) config('platform_health.query_timeout_ms'))."ms'");

                $version = (string) $connection->selectOne('SELECT version() AS value')->value;

                $sizeBytes = (int) $connection->selectOne(
                    'SELECT pg_database_size(current_database()) AS value',
                )->value;

                $activeConnections = (int) $connection->selectOne(
                    'SELECT count(*) AS value FROM pg_stat_activity WHERE datname = current_database()',
                )->value;

                $maxConnections = (int) $connection->selectOne(
                    "SELECT setting AS value FROM pg_settings WHERE name = 'max_connections'",
                )->value;

                $connection->commit();
            } catch (Throwable $throwable) {
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }

                throw $throwable;
            }

            $status = 'healthy';

            if ($maxConnections > 0 && $activeConnections >= (int) round($maxConnections * 0.9)) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'source' => 'PostgreSQL catalog probe',
                'checkedAt' => $checkedAt,
                'serverVersion' => $version,
                'databaseSizeBytes' => $sizeBytes,
                'activeConnections' => $activeConnections,
                'maxConnections' => $maxConnections,
            ];
        } catch (Throwable) {
            return [
                'status' => 'down',
                'source' => 'PostgreSQL catalog probe',
                'checkedAt' => $checkedAt,
                'serverVersion' => null,
                'databaseSizeBytes' => null,
                'activeConnections' => null,
                'maxConnections' => null,
            ];
        }
    }
}
