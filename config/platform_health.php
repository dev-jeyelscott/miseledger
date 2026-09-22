<?php

/*
|--------------------------------------------------------------------------
| Platform Health Configuration Contract
|--------------------------------------------------------------------------
|
| Bounds for the read-only Platform Health probes (POC-V8). These values
| only control how much evidence is gathered and retained; they never
| enable any mutation, restart, or SQL-execution capability.
|
*/

return [

    // How many days of table-size snapshots to retain. Bounded to keep the
    // history table small while still supporting a meaningful recent trend.
    'table_growth_retention_days' => (int) env('PLATFORM_HEALTH_TABLE_GROWTH_RETENTION_DAYS', 35),

    // The lookback window used to compute a "growth over the last period"
    // delta from persisted snapshots.
    'table_growth_lookback_days' => (int) env('PLATFORM_HEALTH_TABLE_GROWTH_LOOKBACK_DAYS', 7),

    // Bounded top-N and time window for the Pulse-backed slow query summary.
    'slow_query_window_hours' => (int) env('PLATFORM_HEALTH_SLOW_QUERY_WINDOW_HOURS', 24),
    'slow_query_limit' => (int) env('PLATFORM_HEALTH_SLOW_QUERY_LIMIT', 10),

    // Finite timeout applied to bounded, allowlisted PostgreSQL health probes.
    'query_timeout_ms' => (int) env('PLATFORM_HEALTH_QUERY_TIMEOUT_MS', 2000),
];
