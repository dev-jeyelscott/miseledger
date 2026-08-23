<?php

namespace App\Console\Commands;

use App\Actions\MasterImport\ImportOpeningBalances;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Console\Command;

class InventoryImportOpeningBalances extends Command
{
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

        $result = $importOpeningBalances->handle(
            $organization,
            $actor,
            (string) $this->argument('batch'),
            (string) file_get_contents($file),
        );

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
