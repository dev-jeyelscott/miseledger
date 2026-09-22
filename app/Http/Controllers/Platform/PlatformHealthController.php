<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\CheckBackupHealth;
use App\Actions\Platform\CheckDatabaseHealth;
use App\Actions\Platform\CheckMigrationHealth;
use App\Actions\Platform\CheckQueueHealth;
use App\Actions\Platform\CheckRedisHealth;
use App\Actions\Platform\CheckSlowQueryDiagnostics;
use App\Actions\Platform\CheckTableGrowthHealth;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the read-only Platform Health page (POC-V8). This page only ever
 * reports bounded, allowlisted evidence; it exposes no deploy, restart,
 * migrate, repair, or SQL-execution control.
 */
final class PlatformHealthController extends Controller
{
    public function index(
        CheckDatabaseHealth $checkDatabaseHealth,
        CheckMigrationHealth $checkMigrationHealth,
        CheckSlowQueryDiagnostics $checkSlowQueryDiagnostics,
        CheckTableGrowthHealth $checkTableGrowthHealth,
        CheckBackupHealth $checkBackupHealth,
        CheckRedisHealth $checkRedisHealth,
        CheckQueueHealth $checkQueueHealth,
    ): Response {
        return Inertia::render('admin/health', [
            'database' => fn () => $checkDatabaseHealth->handle(),
            'migrations' => fn () => $checkMigrationHealth->handle(),
            'slowQueries' => fn () => $checkSlowQueryDiagnostics->handle(),
            'tableGrowth' => fn () => $checkTableGrowthHealth->handle(),
            'backup' => fn () => $checkBackupHealth->handle(),
            'redis' => fn () => $checkRedisHealth->handle(),
            'queues' => fn () => $checkQueueHealth->handle(),
        ]);
    }
}
