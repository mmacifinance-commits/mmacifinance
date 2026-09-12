<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\AuditTrail;
use App\Support\SpreadsheetImportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class BudgetCategoryController extends Controller
{
    /**
     * Display budget categories.
     */
    public function index()
    {
        return Inertia::render('BudgetCategories/Index', [
            'categories' => BudgetCategory::with('particulars')
                ->withCount('particulars', 'budgetItems')
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    /**
     * Store a new budget category.
     */
    public function store(Request $request)
    {
        // Clean the category name first.
        $request->merge([
            'name' => $this->normalizeCategoryName($request->input('name')),
            'description' => $this->normalizeDescription(
                $request->input('description')
            ),
        ]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',

                // Case-insensitive duplicate check
                function ($attribute, $value, $fail) {
                    $exists = BudgetCategory::whereRaw(
                        'LOWER(TRIM(name)) = ?',
                        [mb_strtolower(trim($value))]
                    )->exists();

                    if ($exists) {
                        $fail('This budget category already exists.');
                    }
                },
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ], [
            'name.required' => 'Budget category name is required.',
            'name.max' => 'Budget category name cannot exceed 255 characters.',
        ]);

        $category = BudgetCategory::create($validated);
        AuditTrail::log($category, 'created', auth()->user(), 'Budget category created.');

        return redirect()
            ->route('budget-categories.index')
            ->with('success', 'Category created.');
    }

    /**
     * Update an existing budget category.
     */
    public function update(
        Request $request,
        BudgetCategory $budgetCategory
    ) {
        // Clean submitted values first.
        $request->merge([
            'name' => $this->normalizeCategoryName($request->input('name')),
            'description' => $this->normalizeDescription(
                $request->input('description')
            ),
        ]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',

                // Check duplicates but ignore the category being edited.
                function ($attribute, $value, $fail) use ($budgetCategory) {
                    $exists = BudgetCategory::where(
                        'id',
                        '!=',
                        $budgetCategory->id
                    )
                        ->whereRaw(
                            'LOWER(TRIM(name)) = ?',
                            [mb_strtolower(trim($value))]
                        )
                        ->exists();

                    if ($exists) {
                        $fail('This budget category already exists.');
                    }
                },
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ], [
            'name.required' => 'Budget category name is required.',
            'name.max' => 'Budget category name cannot exceed 255 characters.',
        ]);

        $budgetCategory->update($validated);
        AuditTrail::log($budgetCategory, 'modified', auth()->user(), 'Budget category updated.', [
            'changes' => $validated,
        ]);

        return redirect()
            ->route('budget-categories.index')
            ->with('success', 'Category updated.');
    }

    /**
     * Delete a budget category.
     */
    public function destroy(BudgetCategory $budgetCategory)
    {
        AuditTrail::log($budgetCategory, 'deleted', auth()->user(), 'Budget category deleted.');
        $budgetCategory->delete();

        return redirect()
            ->route('budget-categories.index')
            ->with('success', 'Category deleted.');
    }

    /**
     * Export budget categories to CSV.
     */
    public function exportCsv()
    {
        $filename = sprintf('budget-categories-%s', now()->format('Ymd-His'));

        $categories = BudgetCategory::orderBy('name')->get([
            'name',
            'description',
        ]);

        $rows = [['budget_category', 'description']];
        foreach ($categories as $category) {
            $rows[] = [$category->name, $category->description];
        }

        return SpreadsheetImportExport::downloadXlsx($filename, $rows);
    }

    /**
     * Import budget categories from CSV or Excel.
     */
    public function importCsv(Request $request)
    {
        $validated = $request->validate(SpreadsheetImportExport::validationRules('csv_file', false));

        try {
            [$headers, $rows] = SpreadsheetImportExport::readRows($validated['csv_file']);
        } catch (\RuntimeException $exception) {
            return back()->withErrors([
                'csv_file' => $exception->getMessage(),
            ]);
        }

        if (empty($headers)) {
            return back()->withErrors([
                'csv_file' => 'The CSV/Excel file could not be opened.',
            ]);
        }

        $required = ['budget_category', 'description'];

        if (array_diff($required, $headers)) {
            return back()->withErrors([
                'csv_file' => 'CSV/Excel must contain these columns: budget_category, description.',
            ]);
        }

        $index = array_flip($headers);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $seen = [];

        foreach ($rows as $row) {
            if (!array_filter($row, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $name = $this->normalizeCategoryName(
                $row[$index['budget_category']] ?? ''
            );

            $description = $this->normalizeDescription(
                $row[$index['description']] ?? null
            );

            if ($name === '') {
                $skipped++;
                continue;
            }

            $key = mb_strtolower($name);

            if (isset($seen[$key])) {
                $skipped++;
                continue;
            }

            $seen[$key] = true;

            $category = BudgetCategory::whereRaw(
                'LOWER(TRIM(name)) = ?',
                [mb_strtolower($name)]
            )->first();

            if ($category) {
                $category->description = $description;
                $category->save();
                AuditTrail::log($category, 'imported', auth()->user(), 'Budget category updated from CSV/Excel.');

                $updated++;
            } else {
                $category = BudgetCategory::create([
                    'name' => $name,
                    'description' => $description,
                ]);
                AuditTrail::log($category, 'imported', auth()->user(), 'Budget category created from CSV/Excel.');

                $created++;
            }
        }

        return redirect()
            ->route('budget-categories.index')
            ->with(
                'success',
                "Budget categories imported successfully. Created: {$created}, Updated: {$updated}, Skipped: {$skipped}."
            );
    }

    /**
     * Normalize a budget category name.
     *
     * Example:
     *
     * "   AUXILIARY     FUND   "
     *
     * becomes:
     *
     * "AUXILIARY FUND"
     */
    private function normalizeCategoryName(?string $value): string
    {
        $value = trim((string) $value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    /**
     * Normalize descriptions.
     */
    private function normalizeDescription(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}
