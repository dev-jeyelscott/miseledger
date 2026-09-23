<?php

namespace App\Actions\Onboarding;

use App\Actions\MasterImport\ImportInventoryItems;
use App\Actions\MasterImport\ImportRowError;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use App\Support\Csv\CsvTable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ImportOnboardingInventory
{
    public const MAX_DATA_ROWS = 1000;

    public const MODE_ITEMS = 'items';

    public const MODE_ITEMS_WITH_OPENING_STOCK = 'items_with_opening_stock';

    public function __construct(
        private readonly ImportInventoryItems $importInventoryItems,
        private readonly RecordOnboardingOpeningStock $recordOnboardingOpeningStock,
    ) {}

    /**
     * Validate and import setup inventory from CSV as one all-or-nothing unit.
     *
     * Item columns follow the existing master import contract (`sku`, `name`,
     * `base_unit_symbol`, optional `category_name`, `type`,
     * `yield_percentage`, `active`). Adding an `opening_quantity` column
     * switches to item + opening-stock mode, which also requires
     * `opening_unit_cost` on every row that has a quantity. Quantities are in
     * the item's base unit. A blank quantity leaves the item unresolved; it
     * never means "no opening stock".
     *
     * With `$commit = false` every write runs inside a transaction that is
     * always rolled back, so the preview reports exactly what the real import
     * would do. With `$commit = true` any row error rolls back the whole
     * import. Items are matched by SKU and opening stock uses the per-item
     * setup identity, so retrying the same file never duplicates records.
     *
     * @return array{
     *     mode: string,
     *     committed: bool,
     *     created: int,
     *     updated: int,
     *     openingStockRecorded: int,
     *     rows: list<array{row: int, sku: string, name: string, unit: string, openingQuantity: string|null, openingUnitCost: string|null}>,
     *     errors: list<array{row: int, messages: list<string>}>
     * }
     */
    public function handle(
        Organization $organization,
        User $actor,
        Location $location,
        string $csvContents,
        bool $commit,
    ): array {
        $rows = CsvTable::parse($csvContents);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => __('The CSV file has no data rows.'),
            ]);
        }

        if (count($rows) > self::MAX_DATA_ROWS) {
            throw ValidationException::withMessages([
                'file' => __('The CSV file exceeds the maximum of :max data rows.', [
                    'max' => self::MAX_DATA_ROWS,
                ]),
            ]);
        }

        $header = array_keys($rows[0]['data']);

        foreach (['sku', 'name', 'base_unit_symbol'] as $requiredColumn) {
            if (! in_array($requiredColumn, $header, true)) {
                throw ValidationException::withMessages([
                    'file' => __('The CSV file is missing the required ":column" column.', [
                        'column' => $requiredColumn,
                    ]),
                ]);
            }
        }

        $mode = in_array('opening_quantity', $header, true)
            ? self::MODE_ITEMS_WITH_OPENING_STOCK
            : self::MODE_ITEMS;

        $summaryRows = array_map(
            static fn (array $row): array => [
                'row' => $row['number'],
                'sku' => strtoupper(trim($row['data']['sku'] ?? '')),
                'name' => trim($row['data']['name'] ?? ''),
                'unit' => trim($row['data']['base_unit_symbol'] ?? ''),
                'openingQuantity' => $mode === self::MODE_ITEMS_WITH_OPENING_STOCK
                    && trim($row['data']['opening_quantity'] ?? '') !== ''
                        ? trim($row['data']['opening_quantity'])
                        : null,
                'openingUnitCost' => $mode === self::MODE_ITEMS_WITH_OPENING_STOCK
                    && trim($row['data']['opening_unit_cost'] ?? '') !== ''
                        ? trim($row['data']['opening_unit_cost'])
                        : null,
            ],
            $rows,
        );

        DB::beginTransaction();

        try {
            $itemResult = $this->importInventoryItems->handle(
                $organization,
                $csvContents,
            );

            $errors = $this->errorMap($itemResult->errors);
            $openingStockRecorded = 0;

            if ($mode === self::MODE_ITEMS_WITH_OPENING_STOCK) {
                foreach ($rows as $index => $row) {
                    $quantity = $summaryRows[$index]['openingQuantity'];

                    if ($quantity === null || isset($errors[$row['number']])) {
                        continue;
                    }

                    $message = $this->recordOpeningStock(
                        $organization,
                        $actor,
                        $location,
                        $summaryRows[$index]['sku'],
                        $quantity,
                        $summaryRows[$index]['openingUnitCost'],
                    );

                    if ($message !== null) {
                        $errors[$row['number']] = [$message];

                        continue;
                    }

                    $openingStockRecorded++;
                }
            }

            ksort($errors);

            $committed = $commit && $errors === [];

            if ($committed) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return [
            'mode' => $mode,
            'committed' => $committed,
            'created' => $itemResult->created,
            'updated' => $itemResult->updated,
            'openingStockRecorded' => $openingStockRecorded,
            'rows' => $summaryRows,
            'errors' => array_map(
                static fn (int $row, array $messages): array => [
                    'row' => $row,
                    'messages' => $messages,
                ],
                array_keys($errors),
                array_values($errors),
            ),
        ];
    }

    /**
     * Record one row's opening stock, returning a row error message on failure.
     */
    private function recordOpeningStock(
        Organization $organization,
        User $actor,
        Location $location,
        string $sku,
        string $quantity,
        ?string $unitCost,
    ): ?string {
        if (! is_numeric($quantity)) {
            return __('The opening_quantity column must be numeric.');
        }

        if ($unitCost === null || ! is_numeric($unitCost)) {
            return __('The opening_unit_cost column is required and must be numeric when opening_quantity is provided.');
        }

        if (BigDecimal::of(trim($unitCost))->isNegative()) {
            return __('The opening_unit_cost column must not be negative.');
        }

        $item = InventoryItem::query()
            ->where('organization_id', $organization->getKey())
            ->where('sku', $sku)
            ->where('active', true)
            ->first();

        if ($item === null) {
            return __('No active inventory item with sku ":sku" exists for this organization.', [
                'sku' => $sku,
            ]);
        }

        try {
            $this->recordOnboardingOpeningStock->handle(
                organization: $organization,
                inventoryItem: $item,
                location: $location,
                quantity: $quantity,
                baseUnitCost: $unitCost,
                actor: $actor,
            );
        } catch (ValidationException $exception) {
            return implode(' ', $exception->validator->errors()->all());
        }

        return null;
    }

    /**
     * @param  list<ImportRowError>  $rowErrors
     * @return array<int, list<string>>
     */
    private function errorMap(array $rowErrors): array
    {
        $errors = [];

        foreach ($rowErrors as $rowError) {
            $errors[$rowError->row] = $rowError->messages;
        }

        return $errors;
    }
}
