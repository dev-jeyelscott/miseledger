<?php

namespace App\Actions\MasterImport;

use App\Models\Organization;

final class ImportInventoryMasterBatch
{
    public function __construct(
        private readonly ImportInventoryCategories $importInventoryCategories,
        private readonly ImportUnitsOfMeasure $importUnitsOfMeasure,
        private readonly ImportInventoryItems $importInventoryItems,
        private readonly ImportInventoryItemUnitConversions $importInventoryItemUnitConversions,
        private readonly ImportSuppliers $importSuppliers,
        private readonly ImportSupplierItems $importSupplierItems,
    ) {}

    /**
     * Import an approved inventory master data set in dependency order, so
     * later files can reference records created earlier in the same batch.
     *
     * Every section is optional; omitted sections are skipped entirely.
     * Each section imports independently, so a failing row in one section
     * never blocks another section from importing.
     *
     * @param  array{
     *     categories?: string,
     *     units?: string,
     *     items?: string,
     *     conversions?: string,
     *     suppliers?: string,
     *     supplier_items?: string
     * }  $csvContentsBySection
     * @return array<string, ImportResult>
     */
    public function handle(
        Organization $organization,
        array $csvContentsBySection,
    ): array {
        $results = [];

        if (isset($csvContentsBySection['categories'])) {
            $results['categories'] = $this->importInventoryCategories->handle(
                $organization,
                $csvContentsBySection['categories'],
            );
        }

        if (isset($csvContentsBySection['units'])) {
            $results['units'] = $this->importUnitsOfMeasure->handle(
                $organization,
                $csvContentsBySection['units'],
            );
        }

        if (isset($csvContentsBySection['items'])) {
            $results['items'] = $this->importInventoryItems->handle(
                $organization,
                $csvContentsBySection['items'],
            );
        }

        if (isset($csvContentsBySection['conversions'])) {
            $results['conversions'] = $this
                ->importInventoryItemUnitConversions
                ->handle(
                    $organization,
                    $csvContentsBySection['conversions'],
                );
        }

        if (isset($csvContentsBySection['suppliers'])) {
            $results['suppliers'] = $this->importSuppliers->handle(
                $organization,
                $csvContentsBySection['suppliers'],
            );
        }

        if (isset($csvContentsBySection['supplier_items'])) {
            $results['supplier_items'] = $this->importSupplierItems->handle(
                $organization,
                $csvContentsBySection['supplier_items'],
            );
        }

        return $results;
    }
}
