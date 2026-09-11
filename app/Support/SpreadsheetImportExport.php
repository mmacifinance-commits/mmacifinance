<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpreadsheetImportExport
{
    public static function validationRules(string $field = 'csv_file', bool $withMax = true): array
    {
        $rules = [
            'required',
            'file',
            'mimes:csv,txt,xls,xlsx',
        ];

        if ($withMax) {
            $rules[] = 'max:10240';
        }

        return [$field => $rules];
    }

    public static function readRows(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            throw new \RuntimeException('The uploaded spreadsheet file could not be opened.');
        }

        $python = self::pythonExecutable();
        $script = <<<'PY'
import csv
import json
import os
import sys
from pathlib import Path

try:
    from openpyxl import load_workbook
except Exception:
    load_workbook = None

try:
    import xlrd
except Exception:
    xlrd = None


def read_csv(path):
    with open(path, newline='', encoding='utf-8-sig') as handle:
        return list(csv.reader(handle))


def read_xlsx(path):
    if load_workbook is None:
        raise RuntimeError('openpyxl is required to read .xlsx files.')
    workbook = load_workbook(path, read_only=True, data_only=True)
    sheet = workbook.active
    return [list(row) for row in sheet.iter_rows(values_only=True)]


def read_xls(path):
    if xlrd is None:
        raise RuntimeError('xlrd is required to read .xls files.')
    workbook = xlrd.open_workbook(path)
    sheet = workbook.sheet_by_index(0)
    return [list(sheet.row_values(row_index)) for row_index in range(sheet.nrows)]


path = sys.argv[1]
ext = Path(path).suffix.lower()

if ext in {'.csv', '.txt'}:
    payload = read_csv(path)
elif ext in {'.xlsx', '.xlsm'}:
    payload = read_xlsx(path)
elif ext == '.xls':
    payload = read_xls(path)
else:
    raise SystemExit(2)

print(json.dumps(payload))
PY;

        $process = new Process([$python, '-c', $script, $path]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Unable to read the uploaded spreadsheet file.');
        }

        $payload = json_decode(trim((string) $process->getOutput()), true);

        if (! is_array($payload) || empty($payload)) {
            return [[], []];
        }

        $headers = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            array_values($payload[0])
        );

        $dataRows = [];
        foreach (array_slice($payload, 1) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cleanRow = array_map(fn ($value) => $value === null ? '' : $value, $row);
            if (count(array_filter($cleanRow, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $dataRows[] = $cleanRow;
        }

        return [$headers, $dataRows];
    }

    public static function downloadXlsx(string $filename, array $rows): BinaryFileResponse
    {
        if (! str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'budget_excel_');
        if ($tempPath === false) {
            throw new \RuntimeException('Unable to create a temporary Xlsx file.');
        }

        $python = self::pythonExecutable();
        $script = <<<'PY'
import json
import sys
from openpyxl import Workbook

path = sys.argv[1]
rows = json.loads(sys.argv[2])

workbook = Workbook()
worksheet = workbook.active

for row in rows:
    worksheet.append([value if value is not None else '' for value in row])

workbook.save(path)
PY;

        $process = new Process([
            $python,
            '-c',
            $script,
            $tempPath,
            json_encode($rows),
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            $tempPath = str_replace(['.xlsx', '.xls'], '', $tempPath).'.tmp';
            @unlink($tempPath);
            throw new \RuntimeException('Unable to generate the Excel export file.');
        }

        return response()
            ->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private static function pythonExecutable(): string
    {
        foreach (['PYTHON', 'PYTHON3'] as $envKey) {
            $configured = getenv($envKey);
            if (is_string($configured) && $configured !== '' && self::executableExists($configured)) {
                return $configured;
            }
        }

        $candidates = PHP_OS_FAMILY === 'Windows'
            ? ['python', 'py']
            : ['python3', 'python'];

        foreach ($candidates as $candidate) {
            if (self::executableExists($candidate)) {
                return $candidate;
            }
        }

        return 'python';
    }

    private static function executableExists(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (str_contains($path, '/') || str_contains($path, '\\') || str_contains($path, '.exe')) {
            return is_executable($path) || file_exists($path);
        }

        $process = new Process(PHP_OS_FAMILY === 'Windows' ? ['where', $path] : ['which', $path]);
        $process->run();

        return $process->isSuccessful();
    }
}
