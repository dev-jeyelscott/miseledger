<?php

namespace App\Console\Commands;

use App\Actions\Platform\CaptureDatabaseTableSizeSnapshots;
use Illuminate\Console\Command;

final class SnapshotDatabaseTableSizes extends Command
{
    protected $signature = 'platform-health:snapshot-table-sizes';

    protected $description = 'Capture a daily PostgreSQL table-size snapshot for Platform Health growth reporting.';

    public function __construct(private readonly CaptureDatabaseTableSizeSnapshots $captureSnapshots)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->captureSnapshots->handle();

        $this->info("Captured table-size snapshots for {$count} table(s).");

        return self::SUCCESS;
    }
}
