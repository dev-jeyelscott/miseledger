<?php

namespace App\Actions\MasterImport;

use App\Actions\Inventory\CreateInventoryItemUnit;
use App\Actions\Inventory\UpdateInventoryItemUnit;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Support\Csv\CsvTable;
use Illuminate\Validation\ValidationException;

final class ImportInventoryItemUnitConversions
{
    public function __construct(
        private readonly CreateInventoryItemUnit $createInventoryItemUnit,
        private readonly UpdateInventoryItemUnit $updateInventoryItemUnit,
    ) {}

    /**
     * Import item-specific unit conversions from CSV content, matching an
     * existing conversion by item SKU and unit symbol to decide between an
     * update and a new creation.
     *
     * Expected columns: item_sku, unit_symbol, quantity_in_base_unit,
     * active. The conversion factor must be present and numeric on every
     * row; it is never inferred from other rows or units.
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
            $itemSku = strtoupper(trim($data['item_sku'] ?? ''));
            $unitSymbol = trim($data['unit_symbol'] ?? '');
            $quantity = trim($data['quantity_in_base_unit'] ?? '');
            $active = CsvTable::parseBoolean($data['active'] ?? '');

            $rowErrors = [];

            if ($itemSku === '') {
                $rowErrors[] = __('The item_sku column is required.');
            }

            if ($unitSymbol === '') {
                $rowErrors[] = __('The unit_symbol column is required.');
            }

            if ($quantity === '' || ! is_numeric($quantity)) {
                $rowErrors[] = __(
                    'The quantity_in_base_unit column must be an explicit numeric value.',
                );
            }

            if ($rowErrors !== []) {
                $errors[] = new ImportRowError($row['number'], $rowErrors);

                continue;
            }

            $item = InventoryItem::query()
                ->where('organization_id', $organization->getKey())
                ->where('sku', $itemSku)
                ->first();

            if ($item === null) {
                $errors[] = new ImportRowError($row['number'], [
                    __(
                        'No inventory item with sku ":sku" exists for this organization.',
                        ['sku' => $itemSku],
                    ),
                ]);

                continue;
            }

            $unitOfMeasure = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->where('symbol', $unitSymbol)
                ->first();

            if ($unitOfMeasure === null) {
                $errors[] = new ImportRowError($row['number'], [
                    __(
                        'No unit of measure with symbol ":symbol" exists for this organization.',
                        ['symbol' => $unitSymbol],
                    ),
                ]);

                continue;
            }

            $existing = InventoryItemUnit::query()
                ->where('inventory_item_id', $item->getKey())
                ->where('unit_of_measure_id', $unitOfMeasure->getKey())
                ->first();

            try {
                if ($existing === null) {
                    $this->createInventoryItemUnit->handle(
                        $organization,
                        $item,
                        $unitOfMeasure->id,
                        $quantity,
                        $active,
                    );

                    $created++;
                } else {
                    $this->updateInventoryItemUnit->handle(
                        $organization,
                        $item,
                        $existing,
                        $quantity,
                        $active,
                    );

                    $updated++;
                }
            } catch (ValidationException $exception) {
                $errors[] = new ImportRowError(
                    $row['number'],
                    array_values($exception->validator->errors()->all()),
                );
            }
        }

        return new ImportResult($created, $updated, $errors);
    }
}
