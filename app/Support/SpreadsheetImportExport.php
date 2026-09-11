<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

        $readerPath = self::copyUploadWithExtension($file, $path);

        try {
            $reader = IOFactory::createReaderForFile($readerPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($readerPath);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Unable to read the uploaded spreadsheet file.', 0, $exception);
        } finally {
            if ($readerPath !== $path) {
                @unlink($readerPath);
            }
        }

        $worksheet = $spreadsheet->getActiveSheet();
        $highestColumn = $worksheet->getHighestDataColumn();
        $rows = [];

        foreach ($worksheet->getRowIterator() as $row) {
            $values = [];
            $cellIterator = $row->getCellIterator('A', $highestColumn);
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $values[] = self::cellValue($cell);
            }

            $rows[] = array_map(
                fn ($value) => $value === null ? '' : $value,
                $values
            );
        }

        if ($rows === []) {
            return [[], []];
        }

        $headers = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            array_values($rows[0])
        );

        $dataRows = [];
        foreach (array_slice($rows, 1) as $row) {
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

        unlink($tempPath);
        $tempPath .= '.xlsx';

        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        $worksheet->fromArray(array_map(
            fn ($row) => array_map(fn ($value) => $value === null ? '' : $value, $row),
            $rows
        ));

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()
            ->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private static function copyUploadWithExtension(UploadedFile $file, string $path): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if ($extension === '') {
            return $path;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'budget_import_');
        if ($tempPath === false) {
            return $path;
        }

        @unlink($tempPath);
        $tempPath .= '.'.$extension;

        if (! copy($path, $tempPath)) {
            return $path;
        }

        return $tempPath;
    }

    private static function cellValue(Cell $cell): mixed
    {
        $value = $cell->getValue();

        if ($value === null || $value === '') {
            return '';
        }

        if (Date::isDateTime($cell) && is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return $value;
    }
}
