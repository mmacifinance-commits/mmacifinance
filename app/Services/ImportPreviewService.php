<?php

namespace App\Services;

use App\Support\SpreadsheetImportExport;
use Illuminate\Http\UploadedFile;

class ImportPreviewService
{
    public function preview(UploadedFile $file, array $requiredColumns, callable $inspectRow, int $limit = 100): array
    {
        [$header, $rows] = SpreadsheetImportExport::readRows($file);
        $header = array_map(fn ($value) => trim((string) $value), $header);
        $missing = array_values(array_diff($requiredColumns, $header));
        $index = array_flip($header);
        $valid = [];
        $invalid = [];
        $duplicates = [];
        $seen = [];
        $line = 1;
        $validCount = 0;
        $invalidCount = count($missing);
        $duplicateCount = 0;

        foreach ($rows as $row) {
            $line++;
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($header as $columnIndex => $column) {
                $data[$column] = $row[$columnIndex] ?? null;
            }

            $result = $inspectRow($data, $line, $index);
            $key = $result['key'] ?? null;

            if ($key && isset($seen[$key])) {
                $duplicateCount++;
                $duplicates[] = [
                    'line' => $line,
                    'message' => "Duplicate row key also appears on line {$seen[$key]}.",
                    'row' => $data,
                ];
            }
            if ($key) {
                $seen[$key] = $line;
            }

            if (($result['valid'] ?? false)) {
                $validCount++;
                if (count($valid) < $limit) {
                    $valid[] = ['line' => $line, 'row' => $data, 'message' => $result['message'] ?? 'Ready to import.'];
                }
            } else {
                $invalidCount++;
                if (count($invalid) < $limit) {
                    $invalid[] = ['line' => $line, 'row' => $data, 'message' => $result['message'] ?? 'Invalid row.'];
                }
            }
        }

        return [
            'headers' => $header,
            'missing_columns' => $missing,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'duplicate_count' => $duplicateCount,
            'valid_rows' => $valid,
            'invalid_rows' => $missing
                ? [['line' => null, 'message' => 'Missing required columns: '.implode(', ', $missing), 'row' => []]]
                : $invalid,
            'duplicates' => $duplicates,
        ];
    }
}
