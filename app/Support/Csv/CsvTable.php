<?php

namespace App\Support\Csv;

use RuntimeException;

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
        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open an in-memory stream for CSV parsing.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        $header = null;
        $rows = [];
        $lineNumber = 0;
        $consumedBytes = 0;

        while (($fields = fgetcsv($stream)) !== false) {
            $streamPosition = ftell($stream);

            if ($streamPosition === false) {
                throw new RuntimeException('Unable to read the current position of the CSV stream.');
            }

            $consumed = substr($contents, $consumedBytes, $streamPosition - $consumedBytes);
            $consumedBytes = $streamPosition;

            $linesInRecord = max(preg_match_all("/\r\n|\r|\n/", $consumed), 1);
            $recordStartLine = $lineNumber + 1;
            $lineNumber += $linesInRecord;

            $isBlank = count($fields) === 1
                && ($fields[0] === null || trim((string) $fields[0]) === '');

            if ($isBlank) {
                continue;
            }

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
                'number' => $recordStartLine,
                'data' => $data,
            ];
        }

        fclose($stream);

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
