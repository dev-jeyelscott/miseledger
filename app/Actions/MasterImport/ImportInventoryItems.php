<?php

namespace App\Actions\MasterImport;

use App\Actions\Inventory\SaveInventoryItem;
use App\Enums\InventoryItemType;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Support\Csv\CsvTable;
use Illuminate\Validation\ValidationException;

final class ImportInventoryItems
{
    public function __construct(
        private readonly SaveInventoryItem $saveInventoryItem,
    ) {}

    /**
     * Import inventory items from CSV content, matching an existing item
     * by SKU to decide between an update and a new creation.
     *
     * Expected columns: sku, name, base_unit_symbol, category_name
     * (optional), type (optional, defaults to ingredient), yield_percentage
     * (optional, defaults to 100.00), active. The base unit must already
     * exist for the organization; it is looked up, never created here.
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
            $sku = strtoupper(trim($data['sku'] ?? ''));
            $name = trim($data['name'] ?? '');
            $baseUnitSymbol = trim($data['base_unit_symbol'] ?? '');
            $categoryName = trim($data['category_name'] ?? '');
            $type = strtolower(trim($data['type'] ?? '')) ?: 'ingredient';
            $yieldPercentage = trim($data['yield_percentage'] ?? '') ?: '100.00';

            $rowErrors = [];

            if ($sku === '') {
                $rowErrors[] = __('The sku column is required.');
            }

            if ($name === '') {
                $rowErrors[] = __('The name column is required.');
            }

            if ($baseUnitSymbol === '') {
                $rowErrors[] = __(
                    'The base_unit_symbol column is required.',
                );
            }

            if (InventoryItemType::tryFrom($type) === null) {
                $rowErrors[] = __(
                    'The type column must be a valid inventory item type.',
                );
            }

            if ($rowErrors !== []) {
                $errors[] = new ImportRowError($row['number'], $rowErrors);

                continue;
            }

            $baseUnit = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->where('symbol', $baseUnitSymbol)
                ->first();

            if ($baseUnit === null) {
                $errors[] = new ImportRowError($row['number'], [
                    __(
                        'No unit of measure with symbol ":symbol" exists for this organization.',
                        ['symbol' => $baseUnitSymbol],
                    ),
                ]);

                continue;
            }

            $categoryId = null;

            if ($categoryName !== '') {
                $category = InventoryCategory::query()
                    ->where('organization_id', $organization->getKey())
                    ->where('name', $categoryName)
                    ->first();

                if ($category === null) {
                    $errors[] = new ImportRowError($row['number'], [
                        __(
                            'No category named ":name" exists for this organization.',
                            ['name' => $categoryName],
                        ),
                    ]);

                    continue;
                }

                $categoryId = $category->id;
            }

            $existing = InventoryItem::query()
                ->where('organization_id', $organization->getKey())
                ->where('sku', $sku)
                ->first();

            try {
                $this->saveInventoryItem->handle(
                    $organization,
                    [
                        'name' => $name,
                        'sku' => $sku,
                        'base_unit_of_measure_id' => $baseUnit->id,
                        'inventory_category_id' => $categoryId,
                        'type' => InventoryItemType::from($type),
                        'yield_percentage' => $yieldPercentage,
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
