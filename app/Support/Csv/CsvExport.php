<?php

namespace App\Support\Csv;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExport
{
    /**
     * Stream a CSV download row-by-row so large exports keep a low, constant
     * memory footprint and do not block a worker while buffering the file.
     *
     * @param  list<string>  $header
     * @param  iterable<list<string|int|float|null>>  $rows
     */
    public static function download(
        string $filename,
        array $header,
        iterable $rows,
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($header, $rows): void {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                fputcsv($handle, $header);

                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' => 'text/csv',
            ],
        );
    }
}
