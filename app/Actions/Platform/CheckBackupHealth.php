<?php

namespace App\Actions\Platform;

/**
 * Represents backup/restore readiness using only evidence the application
 * can actually prove (POC-V8.6). It never infers a successful backup or
 * restore from configuration presence or archive presence: the monthly/
 * on-demand `billing-restore-readiness.yml` GitHub Actions workflow job
 * summary remains the sole approved verified-restore evidence until a
 * separately approved authenticated ingestion mechanism exists. No backup
 * credential, repository URL, or restore control is exposed here.
 */
final class CheckBackupHealth
{
    /**
     * @return array{
     *     status: string,
     *     source: string,
     *     configured: bool,
     *     verificationSource: string,
     *     lastVerifiedRestore: null,
     *     workflowPath: string,
     * }
     */
    public function handle(): array
    {
        $configured = filled(config('backup.restic_repository'))
            && filled(config('backup.restic_password'))
            && filled(config('backup.alert_webhook_url'));

        return [
            'status' => $configured ? 'configured' : 'warning',
            'source' => 'config(backup.*) and docs/deployment.md',
            'configured' => $configured,
            'verificationSource' => 'external',
            // Deliberately null: MiseLedger has no application-owned
            // last-verified-backup record. Fabricating a value here would
            // violate the evidence boundary this action exists to enforce.
            'lastVerifiedRestore' => null,
            'workflowPath' => '.github/workflows/billing-restore-readiness.yml',
        ];
    }
}
