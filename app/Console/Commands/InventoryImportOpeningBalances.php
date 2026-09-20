<?php

namespace App\Console\Commands;

use App\Actions\MasterImport\ImportOpeningBalances;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Console\Command;

class InventoryImportOpeningBalances extends Command
{
    /**
     * A deterministic upper bound on the opening-balance file size, enforced
     * before the file is opened for parsing.
     */
    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;

    protected $signature = 'inventory:import-opening-balances
        {organization : The organization ID owning the imported records}
        {actor : The user ID recording the opening balances}
        {batch : A stable batch identifier reused on retry to prevent duplicate stock movements}
        {file : Path to an opening balance CSV file}';

    protected $description =
        'Import initial stock quantities from a CSV file through the opening-balance workflow.';

    public function handle(
        ImportOpeningBalances $importOpeningBalances,
    ): int {
        $organization = Organization::query()->find(
            $this->argument('organization'),
        );

        if ($organization === null) {
            $this->error('No organization exists with that ID.');

            return self::FAILURE;
        }

        if (OrganizationSubscriptionAccessResolver::resolve($organization)->isReadOnly()) {
            $this->error('This organization is read-only until its subscription is resolved.');

            return self::FAILURE;
        }

        $actor = User::query()->find($this->argument('actor'));

        if ($actor === null) {
            $this->error('No user exists with that ID.');

            return self::FAILURE;
        }

        $file = (string) $this->argument('file');

        if (! is_file($file) || ! is_readable($file)) {
            $this->error("Cannot read the opening balance file: {$file}");

            return self::FAILURE;
        }

        $fileSize = filesize($file);

        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE_BYTES) {
            $this->error(sprintf(
                'The opening balance file exceeds the maximum allowed size of %d bytes.',
                self::MAX_FILE_SIZE_BYTES,
            ));

            return self::FAILURE;
        }

        $stream = fopen($file, 'r');

        if ($stream === false) {
            $this->error("Cannot open the opening balance file: {$file}");

            return self::FAILURE;
        }

        try {
            $result = $importOpeningBalances->handle(
                $organization,
                $actor,
                (string) $this->argument('batch'),
                $stream,
            );
        } finally {
            fclose($stream);
        }

        $this->info(sprintf(
            'Opening balances: %d created, %d skipped as already imported, %d row error%s.',
            $result->created,
            $result->skipped,
            count($result->errors),
            count($result->errors) === 1 ? '' : 's',
        ));

        foreach ($result->errors as $error) {
            foreach ($error->messages as $message) {
                $this->line("  Row {$error->row}: {$message}");
            }
        }

        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
