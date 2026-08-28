<?php

/*
|--------------------------------------------------------------------------
| Production Backup Configuration Contract
|--------------------------------------------------------------------------
|
| This is the single config('backup.*') contract the scheduled PostgreSQL
| backup command uses. The repository is provider-agnostic: it targets any
| restic-supported, off-host, encrypted repository (S3-compatible object
| storage, B2, Azure, GCS, SFTP, or a REST server) selected and configured
| by the operator entirely through runtime environment variables. No
| provider, bucket, or credential is committed here.
|
*/

return [

    // Native restic environment variables. RESTIC_REPOSITORY identifies the
    // operator-selected off-host destination; RESTIC_PASSWORD is the
    // repository encryption password restic uses to encrypt every archive
    // at rest independently of the storage provider's own encryption.
    'restic_repository' => env('RESTIC_REPOSITORY'),
    'restic_password' => env('RESTIC_PASSWORD'),

    'retention' => [
        'daily' => (int) env('BACKUP_RETENTION_DAILY', 7),
        'weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 4),
        'monthly' => (int) env('BACKUP_RETENTION_MONTHLY', 12),
    ],

    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '02:00'),

    // Generic HTTP webhook the operator provisions for backup-failure
    // alerting. No specific alerting vendor is assumed or hardcoded.
    'alert_webhook_url' => env('BACKUP_ALERT_WEBHOOK_URL'),
];
