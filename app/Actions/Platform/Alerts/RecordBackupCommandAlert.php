<?php

namespace App\Actions\Platform\Alerts;

use App\Enums\PlatformAlertSeverity;
use App\Enums\PlatformAlertType;

/**
 * Records the only backup evidence MiseLedger can actually observe locally:
 * the scheduled `backup:database` command's own exit code (POC-V9.2). This
 * is wired via `Schedule::command('backup:database')->onFailure()` /
 * `->onSuccess()` in routes/console.php, not a periodic evaluator, since
 * the command only runs once a day and its exit code is the sole
 * authoritative signal.
 */
final class RecordBackupCommandAlert
{
    private const FINGERPRINT = 'backup.command-failure';

    public function __construct(
        private readonly RecordPlatformAlertObservation $record,
        private readonly ResolvePlatformAlertObservation $resolve,
    ) {}

    public function recordFailure(): void
    {
        $this->record->handle(
            fingerprint: self::FINGERPRINT,
            type: PlatformAlertType::BackupFailure,
            source: 'backup:database command',
            severity: PlatformAlertSeverity::Critical,
            title: 'Scheduled database backup failed',
            summary: 'The scheduled backup:database command exited with a non-zero status. See scheduler logs for detail.',
            context: [],
        );
    }

    public function recordSuccess(): void
    {
        $this->resolve->handle(self::FINGERPRINT);
    }
}
