<?php

namespace App\Actions\Platform;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Read-only migration state reporting for the Platform Health page
 * (POC-V8.3), built on the same Migrator/repository APIs `migrate:status`
 * uses. This never runs, rolls back, refreshes, or freshens migrations.
 */
final class CheckMigrationHealth
{
    public function __construct(private readonly Migrator $migrator) {}

    /**
     * @return array{
     *     status: string,
     *     source: string,
     *     checkedAt: string,
     *     appliedCount: int|null,
     *     pendingCount: int|null,
     *     pendingMigrations: list<string>,
     * }
     */
    public function handle(): array
    {
        $checkedAt = Carbon::now()->toIso8601String();

        try {
            if (! $this->migrator->repositoryExists()) {
                return [
                    'status' => 'unknown',
                    'source' => 'Laravel migration repository',
                    'checkedAt' => $checkedAt,
                    'appliedCount' => null,
                    'pendingCount' => null,
                    'pendingMigrations' => [],
                ];
            }

            $ran = $this->migrator->getRepository()->getRan();

            $files = $this->migrator->getMigrationFiles(
                array_unique([database_path('migrations'), ...$this->migrator->paths()]),
            );

            $pending = array_values(array_diff(array_keys($files), $ran));

            return [
                'status' => $pending === [] ? 'healthy' : 'warning',
                'source' => 'Laravel migration repository',
                'checkedAt' => $checkedAt,
                'appliedCount' => count($ran),
                'pendingCount' => count($pending),
                'pendingMigrations' => array_slice($pending, 0, 20),
            ];
        } catch (Throwable) {
            return [
                'status' => 'unknown',
                'source' => 'Laravel migration repository',
                'checkedAt' => $checkedAt,
                'appliedCount' => null,
                'pendingCount' => null,
                'pendingMigrations' => [],
            ];
        }
    }
}
