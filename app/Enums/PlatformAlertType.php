<?php

namespace App\Enums;

/**
 * The bounded, explicitly-approved set of platform-owner alert conditions
 * (POC-V9.2). Every case corresponds to exactly one evaluator that owns
 * both opening and evaluator-observed recovery for that condition.
 */
enum PlatformAlertType: string
{
    case BillingFailedPayments = 'billing_failed_payments';
    case QueueBacklog = 'queue_backlog';
    case SlowQueries = 'slow_queries';
    case DatabaseHealth = 'database_health';
    case MigrationBacklog = 'migration_backlog';
    case TableGrowthAnomaly = 'table_growth_anomaly';
    case BackupFailure = 'backup_failure';
    case ProblemReportAttention = 'problem_report_attention';

    public function label(): string
    {
        return match ($this) {
            self::BillingFailedPayments => 'Repeated failed payments',
            self::QueueBacklog => 'Queue backlog',
            self::SlowQueries => 'Slow queries',
            self::DatabaseHealth => 'Database health',
            self::MigrationBacklog => 'Pending migrations',
            self::TableGrowthAnomaly => 'Table growth anomaly',
            self::BackupFailure => 'Backup failure',
            self::ProblemReportAttention => 'Problem report needs attention',
        };
    }
}
