<?php

namespace App\Actions\MasterImport;

use App\Actions\Suppliers\SaveSupplier;
use App\Models\Organization;
use App\Models\Supplier;
use App\Support\Csv\CsvTable;
use Illuminate\Validation\ValidationException;

final class ImportSuppliers
{
    public function __construct(
        private readonly SaveSupplier $saveSupplier,
    ) {}

    /**
     * Import suppliers from CSV content, matching an existing supplier by
     * code to decide between an update and a new creation.
     *
     * Expected columns: code, name, contact_name (optional), email
     * (optional), phone (optional), payment_terms (optional),
     * lead_time_days (optional), active.
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
            $code = strtoupper(trim($data['code'] ?? ''));
            $name = trim($data['name'] ?? '');
            $leadTimeDays = trim($data['lead_time_days'] ?? '');

            $rowErrors = [];

            if ($code === '') {
                $rowErrors[] = __('The code column is required.');
            }

            if ($name === '') {
                $rowErrors[] = __('The name column is required.');
            }

            if ($leadTimeDays !== '' && ! ctype_digit($leadTimeDays)) {
                $rowErrors[] = __(
                    'The lead_time_days column must be a whole number.',
                );
            }

            if ($rowErrors !== []) {
                $errors[] = new ImportRowError($row['number'], $rowErrors);

                continue;
            }

            $existing = Supplier::query()
                ->where('organization_id', $organization->getKey())
                ->where('code', $code)
                ->first();

            try {
                $this->saveSupplier->handle(
                    $organization,
                    [
                        'name' => $name,
                        'code' => $code,
                        'contact_name' => $this->nullableColumn(
                            $data['contact_name'] ?? '',
                        ),
                        'email' => $this->nullableColumn(
                            $data['email'] ?? '',
                        ),
                        'phone' => $this->nullableColumn(
                            $data['phone'] ?? '',
                        ),
                        'payment_terms' => $this->nullableColumn(
                            $data['payment_terms'] ?? '',
                        ),
                        'lead_time_days' => $leadTimeDays === ''
                            ? null
                            : (int) $leadTimeDays,
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

    private function nullableColumn(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
