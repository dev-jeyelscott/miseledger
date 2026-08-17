<?php

namespace App\Actions\MasterImport;

use App\Actions\Inventory\SaveUnitOfMeasure;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Support\Csv\CsvTable;
use App\Support\Inventory\StandardUnits;
use Illuminate\Validation\ValidationException;

final class ImportUnitsOfMeasure
{
    public function __construct(
        private readonly SaveUnitOfMeasure $saveUnitOfMeasure,
    ) {}

    /**
     * Import units of measure from CSV content, matching an existing unit
     * by symbol to decide between an update and a new creation.
     *
     * Expected columns: symbol, name, dimension, active. A blank dimension
     * is only accepted for a reserved standard symbol, whose dimension is
     * never guessed for anything else.
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
            $symbol = trim($data['symbol'] ?? '');
            $name = trim($data['name'] ?? '');
            $dimension = strtolower(trim($data['dimension'] ?? ''));

            $rowErrors = [];

            if ($symbol === '') {
                $rowErrors[] = __('The symbol column is required.');
            }

            if ($name === '') {
                $rowErrors[] = __('The name column is required.');
            }

            if ($dimension === '') {
                $dimension = StandardUnits::dimensionFor($symbol) ?? '';
            }

            if ($dimension === '') {
                $rowErrors[] = __(
                    'The dimension column is required and cannot be guessed for a non-standard symbol.',
                );
            }

            if ($rowErrors !== []) {
                $errors[] = new ImportRowError($row['number'], $rowErrors);

                continue;
            }

            $existing = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->where('symbol', $symbol)
                ->first();

            try {
                $this->saveUnitOfMeasure->handle(
                    $organization,
                    [
                        'name' => $name,
                        'symbol' => $symbol,
                        'dimension' => $dimension,
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
