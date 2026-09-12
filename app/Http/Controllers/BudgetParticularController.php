<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\AuditTrail;
use App\Models\BudgetParticular;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use App\Support\SpreadsheetImportExport;

class BudgetParticularController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:budget_categories,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $particulars = BudgetParticular::with('category', 'department')
            ->when($validated['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($validated['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('particular', 'like', "%{$search}%")
                        ->orWhere('account_code', 'like', "%{$search}%")
                        ->orWhere('account_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('BudgetParticulars/Index', [
            'particulars' => $particulars,
            'accountTitles' => $particulars,
            'categories' => BudgetCategory::all(),
            'departments' => Department::all(),
            'filters' => [
                'category_id' => $validated['category_id'] ?? '',
                'department_id' => $validated['department_id'] ?? '',
                'search' => $validated['search'] ?? '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'department_id' => 'required|exists:departments,id',
            'account_code' => 'required|string|max:20',
            'account_name' => 'required|string|max:255',
            'particular' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $particular = BudgetParticular::create($validated);
        AuditTrail::log($particular, 'created', auth()->user(), 'Account title created.');

        return redirect()->route('budget-particulars.index')->with('success', 'Account Title created successfully.');
    }

    public function update(Request $request, BudgetParticular $budgetParticular)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'department_id' => 'required|exists:departments,id',
            'account_code' => 'required|string|max:20',
            'account_name' => 'required|string|max:255',
            'particular' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $budgetParticular->update($validated);
        AuditTrail::log($budgetParticular, 'modified', auth()->user(), 'Account title updated.', [
            'changes' => $validated,
        ]);

        return redirect()->route('budget-particulars.index')->with('success', 'Account Title updated successfully.');
    }

    public function destroy(BudgetParticular $budgetParticular)
    {
        AuditTrail::log($budgetParticular, 'deleted', auth()->user(), 'Account title deleted.');
        $budgetParticular->delete();

        return redirect()->route('budget-particulars.index')->with('success', 'Account Title deleted successfully.');
    }

    public function exportCsv()
    {
        $filename = sprintf('account-titles-%s', now()->format('Ymd-His'));
        $particulars = BudgetParticular::with(['category', 'department'])->orderBy('particular')->get();

        $rows = [['budget_category', 'responsibility_center', 'account_code', 'account_name', 'account_title', 'description']];
        foreach ($particulars as $particular) {
            $rows[] = [
                $particular->category?->name,
                $particular->department?->code,
                $particular->account_code,
                $particular->account_name,
                $particular->particular,
                $particular->description,
            ];
        }

        return SpreadsheetImportExport::downloadXlsx($filename, $rows);
    }

    public function importCsv(Request $request)
    {
        $validated = $request->validate(SpreadsheetImportExport::validationRules('csv_file', false));

        try {
            [$headers, $rows] = SpreadsheetImportExport::readRows($validated['csv_file']);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['csv_file' => $exception->getMessage()]);
        }

        if (empty($headers)) {
            return back()->withErrors(['csv_file' => 'CSV/Excel file is empty.']);
        }

        $required = ['budget_category', 'responsibility_center', 'account_code', 'account_name', 'account_title', 'description'];
        if (array_diff($required, $headers)) {
            return back()->withErrors(['csv_file' => 'CSV/Excel must contain these columns: budget_category, responsibility_center, account_code, account_name, account_title, description.']);
        }

        $index = array_flip($headers);
        $created = 0;
        $updated = 0;
        $seen = [];
        $dataRows = 0;
        $duplicates = 0;
        $skipped = [];
        $line = 1;

        foreach ($rows as $row) {
            $line++;
            if (! array_filter($row, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }
            $dataRows++;

            $categoryRef = trim((string) ($row[$index['budget_category']] ?? ''));
            $departmentRef = trim((string) ($row[$index['responsibility_center']] ?? ''));
            $accountCode = trim((string) ($row[$index['account_code']] ?? ''));
            $accountName = trim((string) ($row[$index['account_name']] ?? ''));
            $particular = trim((string) ($row[$index['account_title']] ?? ''));
            $description = trim((string) ($row[$index['description']] ?? ''));

            if ($categoryRef === '' || $departmentRef === '' || $accountCode === '' || $accountName === '' || $particular === '') {
                $skipped[] = "Row {$line} is missing required values.";

                continue;
            }

            $category = BudgetCategory::where('name', $categoryRef)->orWhere('id', $categoryRef)->first();
            $department = Department::where('code', $departmentRef)->orWhere('name', $departmentRef)->orWhere('id', $departmentRef)->first();

            if (! $category || ! $department) {
                $missing = [];
                if (! $category) {
                    $missing[] = "budget category '{$categoryRef}'";
                }
                if (! $department) {
                    $missing[] = "responsibility center '{$departmentRef}'";
                }
                $skipped[] = 'Row '.$line.' could not find '.implode(' and ', $missing).'.';
                continue;
            }

            $key = strtolower($category->id.'|'.$department->id.'|'.$accountCode.'|'.$particular);
            if (isset($seen[$key])) {
                $duplicates++;
                continue;
            }
            $seen[$key] = true;

            $item = BudgetParticular::updateOrCreate(
                [
                    'category_id' => $category->id,
                    'department_id' => $department->id,
                    'account_code' => $accountCode,
                    'particular' => $particular,
                ],
                [
                    'account_name' => $accountName,
                    'description' => $description !== '' ? $description : null,
                ]
            );
            AuditTrail::log($item, 'imported', auth()->user(), $item->wasRecentlyCreated ? 'Account title created from CSV/Excel.' : 'Account title updated from CSV/Excel.');

            $item->wasRecentlyCreated ? $created++ : $updated++;
        }

        if ($dataRows === 0) {
            return back()->withErrors([
                'csv_file' => 'The CSV/Excel contains only the header row. Add at least one account title row before importing.',
            ]);
        }

        if (($created + $updated) === 0) {
            $details = $skipped ? ' '.implode(' ', array_slice($skipped, 0, 5)) : '';
            if (count($skipped) > 5) {
                $details .= ' And '.(count($skipped) - 5).' more row(s).';
            }
            if ($duplicates > 0) {
                $details .= " {$duplicates} duplicate row(s) were ignored.";
            }

            return back()->withErrors([
                'csv_file' => trim('No account titles were imported.'.$details),
            ]);
        }

        $message = "Account titles imported successfully. Created: {$created}, Updated: {$updated}.";
        if ($skipped || $duplicates) {
            $message .= ' Skipped '.count($skipped).' invalid row(s)';
            if ($duplicates) {
                $message .= " and {$duplicates} duplicate row(s)";
            }
            $message .= '.';
        }

        return redirect()
            ->route('budget-particulars.index')
            ->with('success', $message);
    }
}
