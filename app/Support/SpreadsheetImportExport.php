<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
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

    public static function downloadFinancialReportXlsx(string $filename, array $metadata, array $sections): BinaryFileResponse
    {
        return self::downloadPreparedXlsx($filename, function (Spreadsheet $spreadsheet) use ($metadata, $sections): void {
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle('Financial Report');
            $worksheet->getSheetView()->setZoomScale(100);
            $worksheet->setShowGridlines(false);
            $worksheet->getDefaultRowDimension()->setRowHeight(20);
            $worksheet->getDefaultColumnDimension()->setWidth(14);
            $worksheet->getPageSetup()
                ->setPaperSize(PageSetup::PAPERSIZE_A4)
                ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
                ->setFitToWidth(1)
                ->setFitToHeight(0);
            $worksheet->getPageMargins()
                ->setTop(0.35)
                ->setRight(0.25)
                ->setBottom(0.35)
                ->setLeft(0.25);

            $lastColumn = 'I';
            $row = 1;

            $worksheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $worksheet->setCellValue("A{$row}", $metadata['report_label'] ?? 'Financial Report');
            $worksheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17233D']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $worksheet->getRowDimension($row)->setRowHeight(28);
            $row += 3;

            $pairs = [
                ['Fiscal Year', $metadata['fiscal_year'] ?? 'N/A'],
                ['Fiscal Period', $metadata['fiscal_period'] ?? 'N/A'],
                ['Allocation Month', $metadata['allocation_month'] ?? 'All Fiscal Months'],
                ['Date Range', $metadata['date_range'] ?? 'All posting dates'],
                ['Generated By', $metadata['generated_by'] ?? 'System'],
                ['Generated At', $metadata['generated_at'] ?? now()->format('M d, Y h:i A')],
                ['Total Receipts', $metadata['total_receipts'] ?? 0],
                ['Available Cash', $metadata['available_cash'] ?? 0],
            ];

            foreach (array_chunk($pairs, 2) as $pairRow) {
                $column = 1;
                foreach ($pairRow as [$label, $value]) {
                    $labelCell = self::cellCoordinate($column, $row);
                    $valueStartCell = self::cellCoordinate($column + 1, $row);
                    $valueEndCell = self::cellCoordinate($column + 3, $row);
                    $worksheet->setCellValue($labelCell, $label);
                    $worksheet->mergeCells("{$valueStartCell}:{$valueEndCell}");
                    $worksheet->setCellValue($valueStartCell, $value);
                    $worksheet->getStyle("{$labelCell}:{$valueEndCell}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                    ]);
                    $worksheet->getStyle($labelCell)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '475569']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                    ]);
                    if (is_numeric($value) && (str_contains((string) $label, 'Total') || str_contains((string) $label, 'Cash'))) {
                        $worksheet->getStyle($valueStartCell)->getNumberFormat()->setFormatCode('"₱"#,##0.00');
                    }
                    $worksheet->getStyle("{$labelCell}:{$valueEndCell}")->getAlignment()->setWrapText(true);
                    $column += 5;
                }
                $worksheet->getRowDimension($row)->setRowHeight(24);
                $row++;
            }
            $row += 2;

            foreach ($sections as $section) {
                $row = self::writeReportSection($worksheet, $row, $section);
                $row += 2;
            }

            $worksheet->freezePane('A9');
            self::applyFinancialReportColumnWidths($worksheet);
            $worksheet->getStyle("A1:{$lastColumn}".max(1, $row))->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        });
    }

    private static function downloadPreparedXlsx(string $filename, callable $prepare): BinaryFileResponse
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
        $prepare($spreadsheet);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()
            ->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private static function writeReportSection($worksheet, int $row, array $section): int
    {
        $headers = self::reportHeaders($section);
        $rows = self::reportRows($section);
        $lastColumn = 'I';

        $worksheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $worksheet->setCellValue("A{$row}", $section['title'] ?? 'Report Section');
        $worksheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '111827']]],
        ]);
        $row++;

        $headerRow = $row;
        foreach ($headers as $index => $header) {
            $worksheet->setCellValue(self::cellCoordinate($index + 1, $headerRow), $header);
        }
        $worksheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '111827']]],
            'alignment' => ['wrapText' => true],
        ]);
        $worksheet->getRowDimension($headerRow)->setRowHeight(34);
        $row++;

        $firstDataRow = $row;
        foreach ($rows as $dataRow) {
            foreach (array_values($dataRow) as $index => $value) {
                $worksheet->setCellValue(self::cellCoordinate($index + 1, $row), $value);
            }
            self::applyReportRowFormulas($worksheet, $row, $section['type'] ?? '');
            $row++;
        }

        if ($rows === []) {
            $worksheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $worksheet->setCellValue("A{$row}", 'No records match the selected report filters.');
            $worksheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['rgb' => '64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);
            $row++;
        }

        $lastDataRow = max($firstDataRow, $row - 1);
        $totalRow = $row;
        self::mergeReportSectionWideCells($worksheet, $section['type'] ?? '', $headerRow, $firstDataRow, $lastDataRow, $rows !== []);
        self::writeReportTotalRow($worksheet, $totalRow, $firstDataRow, $lastDataRow, $section['type'] ?? '');

        $worksheet->getStyle("A{$firstDataRow}:{$lastColumn}{$totalRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            'alignment' => ['wrapText' => true],
        ]);
        $worksheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
        ]);
        $worksheet->mergeCells("A{$totalRow}:B{$totalRow}");
        $worksheet->getRowDimension($totalRow)->setRowHeight(24);
        $worksheet->getStyle("A{$headerRow}:{$lastColumn}{$totalRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        return $totalRow;
    }

    private static function mergeReportSectionWideCells($worksheet, string $type, int $headerRow, int $firstDataRow, int $lastDataRow, bool $hasRows): void
    {
        if ($type === 'receipts') {
            $worksheet->mergeCells("E{$headerRow}:G{$headerRow}");
            if ($hasRows) {
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $worksheet->mergeCells("E{$row}:G{$row}");
                }
            }
        }

        if ($type === 'disbursements') {
            $worksheet->mergeCells("F{$headerRow}:G{$headerRow}");
            if ($hasRows) {
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $worksheet->mergeCells("F{$row}:G{$row}");
                }
            }
        }
    }

    private static function reportHeaders(array $section): array
    {
        return match ($section['type'] ?? '') {
            'receipts' => [
                'Receipt No.', 'Income No.', 'Receipt Type', 'Source',
                'Description', '', '', 'Receipt Date', 'Amount',
            ],
            'disbursements' => [
                'DSB No.', 'Expense Ref', 'Allocation Month', 'Expense Date',
                'Disbursement Date', 'Payee', '', 'Status', 'Amount',
            ],
            default => array_pad(array_slice($section['headers'] ?? [], 0, 9), 9, ''),
        };
    }

    private static function reportRows(array $section): array
    {
        return array_map(function (array $row) use ($section): array {
            $values = array_values($row);

            return match ($section['type'] ?? '') {
                'receipts' => [
                    $values[0] ?? '',
                    $values[1] ?? '',
                    $values[2] ?? '',
                    $values[3] ?? '',
                    $values[4] ?? '',
                    '',
                    '',
                    $values[5] ?? '',
                    $values[6] ?? 0,
                ],
                'disbursements' => [
                    $values[0] ?? '',
                    $values[1] ?? '',
                    $values[2] ?? '',
                    $values[3] ?? '',
                    $values[4] ?? '',
                    $values[5] ?? '',
                    '',
                    $values[6] ?? '',
                    $values[7] ?? 0,
                ],
                default => array_pad(array_slice($values, 0, 9), 9, ''),
            };
        }, $section['rows'] ?? []);
    }

    private static function applyReportRowFormulas($worksheet, int $row, string $type): void
    {
        if ($type === 'budget') {
            $worksheet->setCellValue("H{$row}", "=F{$row}-G{$row}");
            $worksheet->setCellValue("I{$row}", "=IF(F{$row}>0,G{$row}/F{$row},0)");
            $worksheet->getStyle("F{$row}:H{$row}")->getNumberFormat()->setFormatCode('"₱"#,##0.00');
            $worksheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('0.00%');
        }

        if (in_array($type, ['receipts', 'disbursements'], true)) {
            $worksheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('"₱"#,##0.00');
        }
    }

    private static function writeReportTotalRow($worksheet, int $row, int $firstDataRow, int $lastDataRow, string $type): void
    {
        $worksheet->setCellValue("A{$row}", $type === 'budget' ? 'TOTAL' : 'TOTAL '.strtoupper($type));

        if ($type === 'budget') {
            $worksheet->setCellValue("F{$row}", "=SUM(F{$firstDataRow}:F{$lastDataRow})");
            $worksheet->setCellValue("G{$row}", "=SUM(G{$firstDataRow}:G{$lastDataRow})");
            $worksheet->setCellValue("H{$row}", "=F{$row}-G{$row}");
            $worksheet->setCellValue("I{$row}", "=IF(F{$row}>0,G{$row}/F{$row},0)");
            $worksheet->getStyle("F{$row}:H{$row}")->getNumberFormat()->setFormatCode('"₱"#,##0.00');
            $worksheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('0.00%');
            return;
        }

        if ($type === 'receipts') {
            $worksheet->setCellValue("I{$row}", "=SUM(I{$firstDataRow}:I{$lastDataRow})");
            $worksheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('"₱"#,##0.00');
            return;
        }

        if ($type === 'disbursements') {
            $worksheet->setCellValue("I{$row}", "=SUM(I{$firstDataRow}:I{$lastDataRow})");
            $worksheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('"₱"#,##0.00');
            return;
        }
    }

    private static function columnName(int $columnNumber): string
    {
        return Coordinate::stringFromColumnIndex($columnNumber);
    }

    private static function cellCoordinate(int $columnNumber, int $rowNumber): string
    {
        return self::columnName($columnNumber).$rowNumber;
    }

    private static function applyFinancialReportColumnWidths($worksheet): void
    {
        $widths = [
            'A' => 14,
            'B' => 14,
            'C' => 15,
            'D' => 18,
            'E' => 25,
            'F' => 14,
            'G' => 15,
            'H' => 14,
            'I' => 12,
        ];

        foreach ($widths as $column => $width) {
            $worksheet->getColumnDimension($column)->setWidth($width);
        }
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
