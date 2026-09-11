<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Services\FiscalPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Support\SpreadsheetImportExport;

class ReceiptController extends Controller
{
    public function index(Request $request, FiscalPeriodService $fiscalPeriods)
    {
        $periods = $fiscalPeriods->all();
        $selectedPeriod = $fiscalPeriods->resolve(
            $request->integer('fiscal_period_id') ?: null,
            $request->integer('year') ?: null
        );
        $search = trim((string) $request->query('search', ''));
        $term = trim((string) $request->query('term', ''));

        $query = $this->receiptQuery($selectedPeriod, $search, $term);
        $summaryRows = (clone $query)->get();
        $summaryByType = $summaryRows
            ->groupBy(fn (Income $income) => $this->receiptType($income))
            ->map(fn ($rows, $type) => [
                'type' => $type,
                'count' => $rows->count(),
                'amount' => (float) $rows->sum(fn (Income $income) => (float) $income->amount),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();

        $receipts = $query
            ->latest('date_encoded')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Income $income) => [
                'id' => $income->id,
                'income_no' => $income->income_no,
                'receipt_no' => $income->receipt_no,
                'source' => $income->source,
                'description' => $income->description,
                'amount' => (float) $income->amount,
                'date_encoded' => $income->date_encoded?->toDateString(),
                'notes' => $income->notes,
                'receipt_type' => $this->receiptType($income),
            ]);

        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts,
            'fiscalPeriods' => $fiscalPeriods->options($periods),
            'filters' => [
                'fiscal_period_id' => $selectedPeriod?->id,
                'year' => $selectedPeriod?->year,
                'search' => $search,
                'term' => $term,
            ],
            'summary' => [
                'totalAmount' => (float) $summaryRows->sum(fn (Income $income) => (float) $income->amount),
                'recordCount' => $summaryRows->count(),
                'withReceiptNo' => $summaryRows->filter(fn (Income $income) => filled($income->receipt_no))->count(),
                'byType' => $summaryByType,
            ],
            'termOptions' => [
                ['value' => '', 'label' => 'All Receipt Types'],
                ['value' => 'enrollment', 'label' => 'Enrollment'],
                ['value' => 'premidterm', 'label' => 'Premidterm / Prelim'],
                ['value' => 'midterm', 'label' => 'Midterm'],
                ['value' => 'prefinal', 'label' => 'Pre-Final'],
                ['value' => 'final', 'label' => 'Final Exam'],
            ],
        ]);
    }

    public function exportCsv(Request $request, FiscalPeriodService $fiscalPeriods)
    {
        $periods = $fiscalPeriods->all();
        $selectedPeriod = $fiscalPeriods->resolve(
            $request->integer('fiscal_period_id') ?: null,
            $request->integer('year') ?: null
        );
        $search = trim((string) $request->query('search', ''));
        $term = trim((string) $request->query('term', ''));

        $fileName = 'receipts-export-'.now()->format('Y-m-d_His');
        $query = $this->receiptQuery($selectedPeriod, $search, $term);

        $rows = [['income_no', 'receipt_no', 'receipt_type', 'source', 'description', 'amount', 'date_encoded', 'notes']];

        $query->orderBy('date_encoded')->orderBy('id')->chunk(200, function ($rowsChunk) use (&$rows) {
            foreach ($rowsChunk as $income) {
                $rows[] = [
                    $income->income_no,
                    $income->receipt_no,
                    $this->receiptType($income),
                    $income->source,
                    $income->description,
                    $income->amount,
                    $income->date_encoded?->format('Y-m-d'),
                    $income->notes,
                ];
            }
        });

        return SpreadsheetImportExport::downloadXlsx($fileName, $rows);
    }

    public function importCsv(Request $request)
    {
        $request->validate(SpreadsheetImportExport::validationRules('csv_file', true));

        try {
            [$header, $rows] = SpreadsheetImportExport::readRows($request->file('csv_file'));
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['csv_file' => $exception->getMessage()]);
        }

        if (empty($header)) {
            return back()->withErrors(['csv_file' => 'CSV/Excel file is empty.']);
        }

        $required = ['receipt_no', 'source', 'description', 'amount', 'date_encoded'];
        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                return back()->withErrors(['csv_file' => "Missing required column: {$column}"]);
            }
        }

        $index = array_flip($header);
        $created = 0;
        $updated = 0;
        $skipped = [];
        $line = 1;

        foreach ($rows as $row) {
            $line++;
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $receiptNo = trim((string) ($row[$index['receipt_no']] ?? ''));
            $source = trim((string) ($row[$index['source']] ?? ''));
            $description = trim((string) ($row[$index['description']] ?? ''));
            $amount = (float) ($row[$index['amount']] ?? 0);
            $dateEncoded = trim((string) ($row[$index['date_encoded']] ?? ''));
            $notes = isset($index['notes']) ? trim((string) ($row[$index['notes']] ?? '')) : '';

            if ($receiptNo === '' || $source === '' || $description === '' || $dateEncoded === '') {
                $skipped[] = "Row {$line} is missing receipt_no, source, description, or date_encoded.";
                continue;
            }

            $income = Income::firstOrNew(['receipt_no' => $receiptNo]);
            $isNew = ! $income->exists;
            $income->source = $source;
            $income->description = $description;
            $income->amount = $amount;
            $income->date_encoded = $dateEncoded;
            $income->notes = $notes !== '' ? $notes : null;
            if ($isNew) {
                $income->income_no = sprintf('INC-%s-%04d', date('Y'), Income::count() + 1);
                $income->created_by_id = auth()->id();
            }
            $income->save();

            $isNew ? $created++ : $updated++;
        }

        if (($created + $updated) === 0) {
            return back()->withErrors([
                'csv_file' => $skipped ? 'No receipts were imported. '.implode(' ', array_slice($skipped, 0, 5)) : 'No receipts were imported.',
            ]);
        }

        $message = "Receipts imported successfully. Created: {$created}, Updated: {$updated}.";
        if ($skipped) {
            $message .= ' Skipped '.count($skipped).' invalid row(s).';
        }

        return redirect()->route('receipts.index')->with('success', $message);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receipt_no' => ['required', 'string', 'max:100', Rule::unique('incomes', 'receipt_no')],
            'source' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date_encoded' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $validated['receipt_no'] = trim($validated['receipt_no']);
        $validated['income_no'] = sprintf('INC-%s-%04d', date('Y'), Income::count() + 1);
        $validated['created_by_id'] = auth()->id();

        Income::create($validated);

        return redirect()->route('receipts.index')->with('success', 'Receipt created successfully.');
    }

    public function update(Request $request, Income $receipt)
    {
        abort_if(blank($receipt->receipt_no), 404);

        $validated = $request->validate([
            'receipt_no' => ['required', 'string', 'max:100', Rule::unique('incomes', 'receipt_no')->ignore($receipt->id)],
            'source' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date_encoded' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $validated['receipt_no'] = trim($validated['receipt_no']);
        $receipt->update($validated);

        return redirect()->route('receipts.index')->with('success', 'Receipt updated successfully.');
    }

    public function destroy(Income $receipt)
    {
        abort_if(blank($receipt->receipt_no), 404);

        $receipt->delete();

        return redirect()->route('receipts.index')->with('success', 'Receipt deleted successfully.');
    }

    private function receiptType(Income $income): string
    {
        $text = strtolower($income->source.' '.$income->description);

        return match (true) {
            str_contains($text, 'enrollment') => 'Enrollment',
            str_contains($text, 'premidterm'), str_contains($text, 'pre midterm'), str_contains($text, 'prelim') => 'Premidterm / Prelim',
            str_contains($text, 'midterm') => 'Midterm',
            str_contains($text, 'pre-final'), str_contains($text, 'prefinal') => 'Pre-Final',
            str_contains($text, 'final exam'), str_contains($text, 'final') => 'Final Exam',
            default => 'Cash Receipt',
        };
    }

    private function receiptQuery($selectedPeriod, string $search, string $term)
    {
        $query = Income::query()
            ->when($selectedPeriod, fn ($q) => $q->whereBetween('date_encoded', [
                $selectedPeriod->fiscalStart()->toDateString(),
                $selectedPeriod->fiscalEnd()->toDateString(),
            ]))
            ->whereNotNull('receipt_no')
            ->where('receipt_no', '<>', '');

        if ($term !== '') {
            $patterns = $this->termPatterns($term);
            $query->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhere('source', 'like', "%{$pattern}%")
                        ->orWhere('description', 'like', "%{$pattern}%");
                }
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('income_no', 'like', "%{$search}%")
                    ->orWhere('receipt_no', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function termPatterns(string $term): array
    {
        return match ($term) {
            'enrollment' => ['enrollment'],
            'premidterm' => ['premidterm', 'pre midterm', 'prelim'],
            'midterm' => ['midterm'],
            'prefinal' => ['pre-final', 'prefinal'],
            'final' => ['final exam'],
            default => [],
        };
    }
}
