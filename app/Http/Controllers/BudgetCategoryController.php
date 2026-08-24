<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class BudgetCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('BudgetCategories/Index', [
            'categories' => BudgetCategory::with('particulars')->withCount('particulars', 'budgetItems')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        BudgetCategory::create($validated);

        return redirect()->route('budget-categories.index')->with('success', 'Category created.');
    }

    public function update(Request $request, BudgetCategory $budgetCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $budgetCategory->update($validated);

        return redirect()->route('budget-categories.index')->with('success', 'Category updated.');
    }

    public function destroy(BudgetCategory $budgetCategory)
    {
        $budgetCategory->delete();

        return redirect()->route('budget-categories.index')->with('success', 'Category deleted.');
    }

    public function exportCsv()
    {
        $filename = sprintf('budget-categories-%s.csv', now()->format('Ymd-His'));
        $categories = BudgetCategory::orderBy('name')->get(['name', 'description']);

        return Response::streamDownload(function () use ($categories) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['budget_category', 'description']);

            foreach ($categories as $category) {
                fputcsv($out, [$category->name, $category->description]);
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
        $required = ['budget_category', 'description'];
        if (array_diff($required, $headers)) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV must contain these columns: budget_category, description.']);
        }

        $index = array_flip($headers);
        $created = 0;
        $updated = 0;
        $seen = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (!array_filter($row, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $name = trim((string) ($row[$index['budget_category']] ?? ''));
            $description = trim((string) ($row[$index['description']] ?? ''));

            if ($name === '') {
                continue;
            }

            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $category = BudgetCategory::firstOrNew(['name' => $name]);
            $category->description = $description !== '' ? $description : null;
            $category->save();

            $category->wasRecentlyCreated ? $created++ : $updated++;
        }

        fclose($handle);

        return redirect()
            ->route('budget-categories.index')
            ->with('success', "Budget categories imported successfully. Created: {$created}, Updated: {$updated}.");
    }
}
