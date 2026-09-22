<?php

namespace App\Actions\Platform;

use App\Models\DatabaseTableSizeSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Captures one daily, per-table PostgreSQL storage-metadata snapshot from a
 * fixed, parameter-free catalog query (POC-V8.5). Only schema/table
 * identifiers and catalog-reported byte sizes are read; no business table
 * contents are scanned or persisted. Idempotent for the capture date: a
 * second run on the same day updates the same rows instead of duplicating
 * them, and bounded retention is enforced after every capture.
 */
final class CaptureDatabaseTableSizeSnapshots
{
    /**
     * System schemas are excluded because they never hold MiseLedger
     * business or operational data and would only add noise to growth
     * history.
     *
     * @var list<string>
     */
    private const EXCLUDED_SCHEMAS = ['pg_catalog', 'information_schema', 'pg_toast'];

    public function handle(): int
    {
        $capturedOn = Carbon::today();

        /** @var list<object{schema_name: string, table_name: string, total_bytes: int, table_bytes: int, index_bytes: int}> $rows */
        $rows = DB::select(
            "SELECT n.nspname AS schema_name,
                    c.relname AS table_name,
                    pg_total_relation_size(c.oid) AS total_bytes,
                    pg_relation_size(c.oid) AS table_bytes,
                    pg_indexes_size(c.oid) AS index_bytes
             FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE c.relkind = 'r'
               AND n.nspname NOT IN ('".implode("','", self::EXCLUDED_SCHEMAS)."')",
        );

        DB::transaction(function () use ($rows, $capturedOn): void {
            foreach ($rows as $row) {
                DatabaseTableSizeSnapshot::query()->updateOrCreate(
                    [
                        'captured_on' => $capturedOn,
                        'schema_name' => $row->schema_name,
                        'table_name' => $row->table_name,
                    ],
                    [
                        'total_bytes' => (int) $row->total_bytes,
                        'table_bytes' => (int) $row->table_bytes,
                        'index_bytes' => (int) $row->index_bytes,
                    ],
                );
            }
        });

        $retentionDays = (int) config('platform_health.table_growth_retention_days');

        DatabaseTableSizeSnapshot::query()
            ->where('captured_on', '<', $capturedOn->clone()->subDays($retentionDays))
            ->delete();

        return count($rows);
    }
}
