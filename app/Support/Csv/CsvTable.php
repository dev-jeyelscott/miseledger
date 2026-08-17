<?php

namespace App\Support\Csv;

final class CsvTable
{
    /**
     * Parse raw CSV content into header-keyed rows, skipping blank lines.
     *
     * Row numbers are 1-based and count the header line, so the first data
     * row is reported as row 2, matching what a spreadsheet author sees.
     *
     * @return list<array{number: int, data: array<string, string>}>
     */
    public static function parse(string $contents): array
    {
        $lines = preg_split("/\r\n|\r|\n/", $contents) ?: [];

        $header = null;
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $fields = str_getcsv($line);

            if ($header === null) {
                $header = array_map(
                    static fn (mixed $column): string => strtolower(
                        trim((string) $column),
                    ),
                    $fields,
                );

                continue;
            }

            $data = [];

            foreach ($header as $position => $column) {
                $data[$column] = trim((string) ($fields[$position] ?? ''));
            }

            $rows[] = [
                'number' => $index + 1,
                'data' => $data,
            ];
        }

        return $rows;
    }

    /**
     * Interpret a CSV boolean column, defaulting blank values to true so
     * omitted "active" columns import as active.
     */
    public static function parseBoolean(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return ! in_array(
            $normalized,
            ['0', 'false', 'no', 'inactive'],
            true,
        );
    }
}
