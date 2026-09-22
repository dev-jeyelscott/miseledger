<?php

namespace App\Actions\Platform;

use App\Models\DatabaseTableSizeSnapshot;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reads persisted daily table-size snapshots (POC-V8.5) to report real
 * table growth over a bounded lookback window. A point-in-time size is
 * never reported as growth: when fewer than two distinct capture dates
 * exist yet, the page must show "No growth history yet", not 0% growth.
 */
final class CheckTableGrowthHealth
{
    /**
     * @return array{
     *     status: string,
     *     source: string,
     *     checkedAt: string,
     *     lookbackDays: int,
     *     hasHistory: bool,
     *     tables: list<array{schemaName: string, tableName: string, currentBytes: int, deltaBytes: int|null, capturedOn: string}>,
     * }
     */
    public function handle(): array
    {
        $checkedAt = Carbon::now()->toIso8601String();
        $lookbackDays = (int) config('platform_health.table_growth_lookback_days');

        try {
            $latestDate = DatabaseTableSizeSnapshot::query()->max('captured_on');

            if ($latestDate === null) {
                return [
                    'status' => 'unknown',
                    'source' => 'database_table_size_snapshots',
                    'checkedAt' => $checkedAt,
                    'lookbackDays' => $lookbackDays,
                    'hasHistory' => false,
                    'tables' => [],
                ];
            }

            $latestDate = Carbon::parse($latestDate)->toDateString();

            $current = DatabaseTableSizeSnapshot::query()
                ->where('captured_on', $latestDate)
                ->get()
                ->keyBy(fn (DatabaseTableSizeSnapshot $snapshot): string => $snapshot->schema_name.'.'.$snapshot->table_name);

            $baselineDate = DatabaseTableSizeSnapshot::query()
                ->where('captured_on', '<=', Carbon::parse($latestDate)->subDays($lookbackDays))
                ->max('captured_on');

            $baseline = $baselineDate !== null
                ? DatabaseTableSizeSnapshot::query()
                    ->where('captured_on', Carbon::parse($baselineDate)->toDateString())
                    ->get()
                    ->keyBy(fn (DatabaseTableSizeSnapshot $snapshot): string => $snapshot->schema_name.'.'.$snapshot->table_name)
                : null;

            $tables = $current->map(function (DatabaseTableSizeSnapshot $snapshot) use ($baseline): array {
                $key = $snapshot->schema_name.'.'.$snapshot->table_name;
                $previous = $baseline?->get($key);

                return [
                    'schemaName' => $snapshot->schema_name,
                    'tableName' => $snapshot->table_name,
                    'currentBytes' => $snapshot->total_bytes,
                    'deltaBytes' => $previous !== null ? $snapshot->total_bytes - $previous->total_bytes : null,
                    'capturedOn' => $snapshot->captured_on->toDateString(),
                ];
            })
                ->sortByDesc('currentBytes')
                ->values()
                ->all();

            return [
                'status' => 'healthy',
                'source' => 'database_table_size_snapshots',
                'checkedAt' => $checkedAt,
                'lookbackDays' => $lookbackDays,
                'hasHistory' => $baseline !== null,
                'tables' => array_slice($tables, 0, 15),
            ];
        } catch (Throwable) {
            return [
                'status' => 'unknown',
                'source' => 'database_table_size_snapshots',
                'checkedAt' => $checkedAt,
                'lookbackDays' => $lookbackDays,
                'hasHistory' => false,
                'tables' => [],
            ];
        }
    }
}
