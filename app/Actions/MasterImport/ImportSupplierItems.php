<?php

namespace App\Actions\MasterImport;

use App\Actions\Suppliers\RecordSupplierItemPrice;
use App\Actions\Suppliers\SaveSupplierItem;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Support\Csv\CsvTable;
use Illuminate\Validation\ValidationException;

final class ImportSupplierItems
{
    public function __construct(
        private readonly SaveSupplierItem $saveSupplierItem,
        private readonly RecordSupplierItemPrice $recordSupplierItemPrice,
    ) {}

    /**
     * Import supplier purchase-pack mappings from CSV content, matching an
     * existing mapping by supplier code and supplier SKU to decide between
     * an update and a new creation.
     *
     * Expected columns: supplier_code, item_sku, supplier_sku, description
     * (optional), purchase_unit_symbol, base_quantity, current_price
     * (optional, only applied when creating a new mapping so existing
     * append-only price history is never mutated by re-import), active.
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
            $supplierCode = strtoupper(trim($data['supplier_code'] ?? ''));
            $itemSku = strtoupper(trim($data['item_sku'] ?? ''));
            $supplierSku = strtoupper(trim($data['supplier_sku'] ?? ''));
            $purchaseUnitSymbol = trim($data['purchase_unit_symbol'] ?? '');
            $baseQuantity = trim($data['base_quantity'] ?? '');
            $currentPrice = trim($data['current_price'] ?? '');
            $description = trim($data['description'] ?? '');

            $rowErrors = [];

            if ($supplierCode === '') {
                $rowErrors[] = __('The supplier_code column is required.');
            }

            if ($itemSku === '') {
                $rowErrors[] = __('The item_sku column is required.');
            }

            if ($supplierSku === '') {
                $rowErrors[] = __('The supplier_sku column is required.');
            }

            if ($purchaseUnitSymbol === '') {
                $rowErrors[] = __(
                    'The purchase_unit_symbol column is required.',
                );
            }

            if ($baseQuantity === '' || ! is_numeric($baseQuantity)) {
                $rowErrors[] = __(
                    'The base_quantity column must be an explicit numeric value.',
                );
            }

            if ($currentPrice !== '' && ! is_numeric($currentPrice)) {
                $rowErrors[] = __(
                    'The current_price column must be numeric when present.',
                );
            }

            if ($rowErrors !== []) {
                $errors[] = new ImportRowError($row['number'], $rowErrors);

                continue;
            }

            $supplier = Supplier::query()
                ->where('organization_id', $organization->getKey())
                ->where('code', $supplierCode)
                ->first();

            if ($supplier === null) {
                $errors[] = new ImportRowError($row['number'], [
                    __(
                        'No supplier with code ":code" exists for this organization.',
                        ['code' => $supplierCode],
                    ),
                ]);

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

            $purchaseUnit = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->where('symbol', $purchaseUnitSymbol)
                ->first();

            if ($purchaseUnit === null) {
                $errors[] = new ImportRowError($row['number'], [
                    __(
                        'No unit of measure with symbol ":symbol" exists for this organization.',
                        ['symbol' => $purchaseUnitSymbol],
                    ),
                ]);

                continue;
            }

            $existing = SupplierItem::query()
                ->where('organization_id', $organization->getKey())
                ->where('supplier_id', $supplier->getKey())
                ->where('supplier_sku', $supplierSku)
                ->first();

            try {
                $supplierItem = $this->saveSupplierItem->handle(
                    $organization,
                    $supplier,
                    [
                        'inventory_item_id' => $item->id,
                        'supplier_sku' => $supplierSku,
                        'description' => $description === ''
                            ? null
                            : $description,
                        'purchase_unit_of_measure_id' => $purchaseUnit->id,
                        'base_quantity' => $baseQuantity,
                        'active' => CsvTable::parseBoolean(
                            $data['active'] ?? '',
                        ),
                    ],
                    $existing,
                );

                if ($existing === null && $currentPrice !== '') {
                    $this->recordSupplierItemPrice->handle(
                        $organization,
                        $supplierItem,
                        $currentPrice,
                    );
                }
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
