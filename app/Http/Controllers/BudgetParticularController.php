<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class BudgetParticularController extends Controller
{
    public function index()
    {
        $particulars = BudgetParticular::with('category', 'department')->latest()->get();

        return Inertia::render('BudgetParticulars/Index', [
            'particulars' => $particulars,
            'accountTitles' => $particulars,
            'categories' => BudgetCategory::all(),
            'departments' => Department::all(),
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

        BudgetParticular::create($validated);

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

        return redirect()->route('budget-particulars.index')->with('success', 'Account Title updated successfully.');
    }

    public function destroy(BudgetParticular $budgetParticular)
    {
        $budgetParticular->delete();

        return redirect()->route('budget-particulars.index')->with('success', 'Account Title deleted successfully.');
    }

    public function exportCsv()
    {
        $filename = sprintf('account-titles-%s.csv', now()->format('Ymd-His'));
        $particulars = BudgetParticular::with(['category', 'department'])->orderBy('particular')->get();

        return Response::streamDownload(function () use ($particulars) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['budget_category', 'responsibility_center', 'account_code', 'account_name', 'account_title', 'description']);

            foreach ($particulars as $particular) {
                fputcsv($out, [
                    $particular->category?->name,
                    $particular->department?->code,
                    $particular->account_code,
                    $particular->account_name,
                    $particular->particular,
                    $particular->description,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importCsv(Request $request)
    {
        $validated = $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $handle = fopen($validated['csv_file']->getRealPath(), 'r');
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), fgetcsv($handle) ?: []);
        $required = ['budget_category', 'responsibility_center', 'account_code', 'account_name', 'account_title', 'description'];
        if (array_diff($required, $headers)) {
            fclose($handle);

            return back()->withErrors(['csv_file' => 'CSV must contain these columns: budget_category, responsibility_center, account_code, account_name, account_title, description.']);
        }

        $index = array_flip($headers);
        $created = 0;
        $updated = 0;
        $seen = [];
        $dataRows = 0;
        $duplicates = 0;
        $skipped = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
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

            $item->wasRecentlyCreated ? $created++ : $updated++;
        }

        fclose($handle);

        if ($dataRows === 0) {
            return back()->withErrors([
                'csv_file' => 'The CSV contains only the header row. Add at least one account title row before importing.',
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
