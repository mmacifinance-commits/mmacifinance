<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
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
                ->get(),
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

        BudgetCategory::create($validated);

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

        return redirect()
            ->route('budget-categories.index')
            ->with('success', 'Category updated.');
    }

    /**
     * Delete a budget category.
     */
    public function destroy(BudgetCategory $budgetCategory)
    {
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
        $filename = sprintf(
            'budget-categories-%s.csv',
            now()->format('Ymd-His')
        );

        $categories = BudgetCategory::orderBy('name')
            ->get([
                'name',
                'description',
            ]);

        return Response::streamDownload(
            function () use ($categories) {
                $out = fopen('php://output', 'w');

                fputcsv($out, [
                    'budget_category',
                    'description',
                ]);

                foreach ($categories as $category) {
                    fputcsv($out, [
                        $category->name,
                        $category->description,
                    ]);
                }

                fclose($out);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    /**
     * Import budget categories from CSV.
     */
    public function importCsv(Request $request)
    {
        $validated = $request->validate([
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
            ],
        ]);

        $handle = fopen(
            $validated['csv_file']->getRealPath(),
            'r'
        );

        if (!$handle) {
            return back()->withErrors([
                'csv_file' => 'The CSV file could not be opened.',
            ]);
        }

        $headerRow = fgetcsv($handle) ?: [];

        $headers = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            $headerRow
        );

        $required = [
            'budget_category',
            'description',
        ];

        if (array_diff($required, $headers)) {
            fclose($handle);

            return back()->withErrors([
                'csv_file' =>
                    'CSV must contain these columns: budget_category, description.',
            ]);
        }

        $index = array_flip($headers);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        // Prevent duplicate rows inside the same CSV file.
        $seen = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Ignore completely empty rows.
            if (
                !array_filter(
                    $row,
                    fn ($value) =>
                        trim((string) $value) !== ''
                )
            ) {
                continue;
            }

            $name = $this->normalizeCategoryName(
                $row[$index['budget_category']] ?? ''
            );

            $description = $this->normalizeDescription(
                $row[$index['description']] ?? null
            );

            // Ignore rows without category names.
            if ($name === '') {
                $skipped++;
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Duplicate CSV row check
            |--------------------------------------------------------------------------
            |
            | These will be considered the same:
            |
            | AUXILIARY FUND
            | auxiliary fund
            | Auxiliary Fund
            |  Auxiliary    Fund
            |
            */
            $key = mb_strtolower($name);

            if (isset($seen[$key])) {
                $skipped++;
                continue;
            }

            $seen[$key] = true;

            /*
            |--------------------------------------------------------------------------
            | Find existing category
            |--------------------------------------------------------------------------
            |
            | We compare names case-insensitively so importing:
            |
            | "auxiliary fund"
            |
            | will find:
            |
            | "AUXILIARY FUND"
            |
            | instead of creating another record.
            |
            */
            $category = BudgetCategory::whereRaw(
                'LOWER(TRIM(name)) = ?',
                [mb_strtolower($name)]
            )->first();

            if ($category) {
                // Existing category: update description only.
                $category->description = $description;
                $category->save();

                $updated++;
            } else {
                // New category.
                BudgetCategory::create([
                    'name' => $name,
                    'description' => $description,
                ]);

                $created++;
            }
        }

        fclose($handle);

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