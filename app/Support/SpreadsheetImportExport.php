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

            // Landscape + fit-to-page-width scaling makes the 9-column table
            // print/preview cleanly whether the physical sheet fed at print
            // time is A4 or Letter/"Short" bond — Excel recalculates the
            // scale against whatever paper is actually selected, so we don't
            // need to branch on paper size here. setFitToPage(true) is what
            // actually turns the FitToWidth/FitToHeight numbers on; without
            // it PhpSpreadsheet writes them but Excel ignores them and prints
            // at 100%, which is why the report was spilling across pages and
            // looking cut off.
            $worksheet->getPageSetup()
                ->setPaperSize(PageSetup::PAPERSIZE_A4)
                ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
                ->setFitToPage(true)
                ->setFitToWidth(1)
                ->setFitToHeight(0)
                ->setHorizontalCentered(true);
            $worksheet->getPageMargins()
                ->setTop(0.4)
                ->setRight(0.3)
                ->setBottom(0.4)
                ->setLeft(0.3);

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
                ['Responsibility Center', $metadata['department'] ?? 'All'],
                ['Category', $metadata['category'] ?? 'All'],
                ['Account Title', $metadata['account_title'] ?? 'All'],
            ];

            foreach (array_chunk($pairs, 2) as $pairRow) {
                $column = 1;
                foreach ($pairRow as [$label, $value]) {
                    $labelCell = self::cellCoordinate($column, $row);
                    $valueStartCell = self::cellCoordinate($column + 1, $row);
                    $valueEndCell = self::cellCoordinate($column + 3, $row);
                    $worksheet->setCellValue($labelCell, $label);
                    $worksheet->mergeCells("{$valueStartCell}:{$valueEndCell}");
                    $worksheet->setCellValueExplicit($valueStartCell, $value ?? '',
                        is_int($value) || is_float($value) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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

            // Freeze/repeat exactly where the metadata block actually ends,
            // instead of a hardcoded row number that silently drifts out of
            // sync (and freezes/repeats the wrong rows) if $pairs above ever
            // gains or loses an entry.
            $metadataEndRow = $row - 1;

            foreach ($sections as $section) {
                $row = ($section['type'] ?? '') === 'unified'
                    ? self::writeUnifiedSection($worksheet, $row, $section)
                    : self::writeReportSection($worksheet, $row, $section);
                $row += 2;
            }
            foreach ($metadata['notes'] ?? [] as $note) {
                $worksheet->mergeCells("A{$row}:I{$row}");
                $worksheet->setCellValueExplicit("A{$row}", $note, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $worksheet->getStyle("A{$row}:I{$row}")->getAlignment()->setWrapText(true);
                $worksheet->getRowDimension($row)->setRowHeight(42);
                $row++;
            }
            $lastRow = max(1, $row - 1);

            $worksheet->freezePane('A'.($metadataEndRow + 1));
            $worksheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, $metadataEndRow);
            $worksheet->getPageSetup()->setPrintArea("A1:{$lastColumn}{$lastRow}");
            self::applyFinancialReportColumnWidths($worksheet);
            $worksheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
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

    private static function writeUnifiedSection($sheet, int $row, array $section): int
    {
        $count = count($section['headers']);
        // Every section uses the same A:I boundary; spare columns widen text.
        $widths = array_fill(0, $count, 1);
        $widths[$count > 3 ? 2 : 0] += 9 - $count;
        $columns = []; $column = 1;
        foreach ($widths as $width) { $columns[] = $column; $column += $width; }
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValueExplicit("A{$row}", $section['title'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(36);
        $row++;
        $header = $row;
        $write = function (array $values, int $line, bool $heading = false) use ($sheet, $columns, $widths, $section) {
            foreach ($values as $i => $value) {
                $cell = Coordinate::stringFromColumnIndex($columns[$i]).$line;
                $last = Coordinate::stringFromColumnIndex($columns[$i] + $widths[$i] - 1).$line;
                if ($widths[$i] > 1) $sheet->mergeCells("{$cell}:{$last}");
                if (!$heading && (in_array($i, $section['money']) || $section['percent'] === $i) && is_numeric($value)) {
                    $sheet->setCellValue($cell, $section['percent'] === $i ? $value / 100 : $value);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($section['percent'] === $i ? '0.00%' : '"₱"#,##0.00');
                } else {
                    $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            $lines = 1;
            foreach ($values as $i => $value) $lines = max($lines, (int) ceil(mb_strlen((string) $value) / max(8, $widths[$i] * 12)));
            $sheet->getRowDimension($line)->setRowHeight(max($heading ? 42 : 36, min(400, $lines * 15)));
        };
        $write($section['headers'], $row++, true);
        $first = $row;
        foreach ($section['rows'] as $values) {
            $write($values, $row);
            if (isset($section['balanceColumns'])) {
                [$a, $p, $b] = array_map(fn ($i) => Coordinate::stringFromColumnIndex($columns[$i]), $section['balanceColumns']);
                $sheet->setCellValue("{$b}{$row}", "={$a}{$row}-{$p}{$row}");
                $pct = Coordinate::stringFromColumnIndex($columns[$section['percent']]);
                $sheet->setCellValue("{$pct}{$row}", "=IF({$a}{$row}>0,{$p}{$row}/{$a}{$row},0)");
            }
            $row++;
        }
        if (!$section['rows']) {
            $sheet->mergeCells("A{$row}:I{$row}");
            $sheet->setCellValue("A{$row}", 'No records match the selected report filters.');
            $row++;
        }
        $last = $row - 1;
        if ($section['totalLabel']) {
            $write(array_replace(array_fill(0, $count, ''), [0 => $section['totalLabel']], $section['totals']), $row);
            foreach ($section['money'] as $i) {
                $c = Coordinate::stringFromColumnIndex($columns[$i]);
                $sheet->setCellValue("{$c}{$row}", "=SUM({$c}{$first}:{$c}{$last})");
            }
            if ($section['percent'] !== null) {
                $a = Coordinate::stringFromColumnIndex($columns[$section['money'][0]]);
                $p = Coordinate::stringFromColumnIndex($columns[$section['money'][1]]);
                $pct = Coordinate::stringFromColumnIndex($columns[$section['percent']]);
                $sheet->setCellValue("{$pct}{$row}", "=IF({$a}{$row}>0,{$p}{$row}/{$a}{$row},0)");
            }
            $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
            $row++;
        }
        $last = $row - 1;
        $sheet->getStyle("A{$header}:I{$last}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("A{$header}:I{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$header}:I{$header}")->getFont()->setBold(true);
        return $row;
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
