<?php

/*
|--------------------------------------------------------------------------
| Platform Owner Alerts Configuration Contract
|--------------------------------------------------------------------------
|
| Bounded thresholds and time windows for the read-only platform-owner
| alert evaluators (POC-V9.2). Each evaluator documents the exact rule it
| applies; these values only control sensitivity and retention, never any
| mutation, restart, or SQL-execution capability.
|
*/

return [

    // A billing organization opens a failed-payment alert once it has at
    // least this many failed payment attempts inside the trailing window.
    'failed_payment_threshold' => (int) env('PLATFORM_ALERTS_FAILED_PAYMENT_THRESHOLD', 3),
    'failed_payment_window_hours' => (int) env('PLATFORM_ALERTS_FAILED_PAYMENT_WINDOW_HOURS', 24),

    // A table opens a growth-anomaly alert once its size has grown by at
    // least this ratio over the Platform Health lookback window, and only
    // once it already exceeds this minimum floor (bytes) to avoid noise on
    // small tables where a doubling is not operationally meaningful.
    'table_growth_alert_ratio' => (float) env('PLATFORM_ALERTS_TABLE_GROWTH_RATIO', 0.5),
    'table_growth_minimum_bytes' => (int) env('PLATFORM_ALERTS_TABLE_GROWTH_MIN_BYTES', 10 * 1024 * 1024),

    // A submitted/in-review/in-progress problem report opens an attention
    // alert once it has remained open for at least this many hours.
    'problem_report_attention_hours' => (int) env('PLATFORM_ALERTS_PROBLEM_REPORT_HOURS', 48),

    // Bounded page size for the Platform Alerts console.
    'per_page_options' => [15, 25, 50],
    'default_per_page' => 25,
];
