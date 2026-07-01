<?php

namespace App\Services\Import;

use Generator;
use InvalidArgumentException;
use SplFileObject;

class LegacyCsvReader
{
    public function rows(string $path): Generator
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("CSV file not found: {$path}");
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');

        $headers = null;

        foreach ($file as $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $row = array_map(
                fn ($value) => is_string($value) ? trim($value) : $value,
                $row
            );

            if ($headers === null) {
                $headers = array_map(
                    function ($header) {
                        $header = trim((string) $header);

                        return preg_replace('/^\xEF\xBB\xBF/', '', $header);
                    },
                    $row
                );

                continue;
            }

            if (count($row) !== count($headers)) {
                $row = array_pad($row, count($headers), null);
                $row = array_slice($row, 0, count($headers));
            }

            yield array_combine($headers, $row);
        }
    }

    public function countDataRows(string $path): int
    {
        $count = 0;

        foreach ($this->rows($path) as $_row) {
            $count++;
        }

        return $count;
    }
}
