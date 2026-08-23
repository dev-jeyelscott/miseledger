<?php

namespace App\Console\Commands;

use App\Actions\MasterImport\ImportInventoryMasterBatch;
use App\Actions\MasterImport\ImportResult;
use App\Models\Organization;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Console\Command;

class InventoryImportMaster extends Command
{
    protected $signature = 'inventory:import-master
        {organization : The organization ID owning the imported records}
        {--categories= : Path to a categories CSV file}
        {--units= : Path to a units of measure CSV file}
        {--items= : Path to an inventory items CSV file}
        {--conversions= : Path to an item unit conversions CSV file}
        {--suppliers= : Path to a suppliers CSV file}
        {--supplier-items= : Path to a supplier items CSV file}';

    protected $description =
        'Import approved inventory master data from CSV files.';

    public function handle(
        ImportInventoryMasterBatch $importInventoryMasterBatch,
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

        $sections = [
            'categories' => $this->option('categories'),
            'units' => $this->option('units'),
            'items' => $this->option('items'),
            'conversions' => $this->option('conversions'),
            'suppliers' => $this->option('suppliers'),
            'supplier_items' => $this->option('supplier-items'),
        ];

        $csvContentsBySection = [];

        foreach ($sections as $section => $path) {
            if ($path === null) {
                continue;
            }

            if (! is_file($path) || ! is_readable($path)) {
                $this->error("Cannot read the {$section} file: {$path}");

                return self::FAILURE;
            }

            $csvContentsBySection[$section] = (string) file_get_contents(
                $path,
            );
        }

        if ($csvContentsBySection === []) {
            $this->error('Provide at least one CSV file option to import.');

            return self::FAILURE;
        }

        $results = $importInventoryMasterBatch->handle(
            $organization,
            $csvContentsBySection,
        );

        $hasErrors = false;

        foreach ($results as $section => $result) {
            $this->reportSection($section, $result);

            if ($result->hasErrors()) {
                $hasErrors = true;
            }
        }

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    private function reportSection(
        string $section,
        ImportResult $result,
    ): void {
        $this->info(sprintf(
            '%s: %d created, %d updated, %d row error%s.',
            $section,
            $result->created,
            $result->updated,
            count($result->errors),
            count($result->errors) === 1 ? '' : 's',
        ));

        foreach ($result->errors as $error) {
            foreach ($error->messages as $message) {
                $this->line("  Row {$error->row}: {$message}");
            }
        }
    }
}
