<?php

namespace App\Actions\Platform;

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Pulse;
use Throwable;

/**
 * Bounded, sanitized slow-query summary for the Platform Health page
 * (POC-V8.4), read from Phase 7's Pulse `SlowQueries` recorder aggregates
 * rather than a parallel query profiler. Pulse never records raw bind
 * values for this recorder, only the SQL text (optionally length-limited)
 * and file:line location, so nothing here exposes customer content.
 */
final class CheckSlowQueryDiagnostics
{
    public function __construct(private readonly Pulse $pulse) {}

    /**
     * @return array{
     *     status: string,
     *     source: string,
     *     checkedAt: string,
     *     windowHours: int,
     *     queries: list<array{sql: string, location: string|null, maxDurationMs: int, avgDurationMs: int, count: int}>,
     * }
     */
    public function handle(): array
    {
        $checkedAt = Carbon::now()->toIso8601String();
        $windowHours = (int) config('platform_health.slow_query_window_hours');
        $limit = (int) config('platform_health.slow_query_limit');

        if (! config('pulse.enabled')) {
            return [
                'status' => 'unknown',
                'source' => 'Pulse SlowQueries recorder',
                'checkedAt' => $checkedAt,
                'windowHours' => $windowHours,
                'queries' => [],
            ];
        }

        try {
            $rows = $this->pulse->aggregate(
                'slow_query',
                ['max', 'avg', 'count'],
                CarbonInterval::hours($windowHours),
                'max',
                'desc',
                $limit,
            );

            /** @var list<array{sql: string, location: string|null, maxDurationMs: int, avgDurationMs: int, count: int}> $queries */
            $queries = $rows->map(function (object $row): array {
                [$sql, $location] = json_decode((string) $row->key, true, flags: JSON_THROW_ON_ERROR);

                /** @var int $max */
                $max = $row->max; // @phpstan-ignore property.notFound (Pulse's aggregate() row shape is dynamic per requested aggregate)
                /** @var int $avg */
                $avg = $row->avg; // @phpstan-ignore property.notFound
                /** @var int $count */
                $count = $row->count; // @phpstan-ignore property.notFound

                return [
                    'sql' => (string) $sql,
                    'location' => $location !== null ? (string) $location : null,
                    'maxDurationMs' => (int) $max,
                    'avgDurationMs' => (int) round((float) $avg),
                    'count' => (int) $count,
                ];
            })->values()->all();

            return [
                'status' => $queries === [] ? 'healthy' : 'warning',
                'source' => 'Pulse SlowQueries recorder',
                'checkedAt' => $checkedAt,
                'windowHours' => $windowHours,
                'queries' => $queries,
            ];
        } catch (Throwable) {
            return [
                'status' => 'unknown',
                'source' => 'Pulse SlowQueries recorder',
                'checkedAt' => $checkedAt,
                'windowHours' => $windowHours,
                'queries' => [],
            ];
        }
    }
}
