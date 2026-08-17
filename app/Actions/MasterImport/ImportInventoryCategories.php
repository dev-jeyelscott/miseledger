<?php

namespace App\Actions\MasterImport;

use App\Actions\Inventory\SaveInventoryCategory;
use App\Models\InventoryCategory;
use App\Models\Organization;
use App\Support\Csv\CsvTable;
use Illuminate\Validation\ValidationException;

final class ImportInventoryCategories
{
    public function __construct(
        private readonly SaveInventoryCategory $saveInventoryCategory,
    ) {}

    /**
     * Import categories from CSV content, matching an existing category by
     * name to decide between an update and a new creation.
     *
     * Expected columns: name, active.
     */
    public function handle(
        Organization $organization,
        string $csvContents,
    ): ImportResult {
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach (CsvTable::parse($csvContents) as $row) {
            $data = $row['data'];
            $name = trim($data['name'] ?? '');

            if ($name === '') {
                $errors[] = new ImportRowError(
                    $row['number'],
                    [__('The name column is required.')],
                );

                continue;
            }

            $existing = InventoryCategory::query()
                ->where('organization_id', $organization->getKey())
                ->where('name', $name)
                ->first();

            try {
                $this->saveInventoryCategory->handle(
                    $organization,
                    [
                        'name' => $name,
                        'active' => CsvTable::parseBoolean(
                            $data['active'] ?? '',
                        ),
                    ],
                    $existing,
                );
            } catch (ValidationException $exception) {
                $errors[] = new ImportRowError(
                    $row['number'],
                    array_values($exception->validator->errors()->all()),
                );

                continue;
            }

            $existing === null ? $created++ : $updated++;
        }

        return new ImportResult($created, $updated, $errors);
    }
}
