<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Support\SpreadsheetImportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Departments/Index', [
            'departments' => Department::with('particulars')->withCount('particulars')->orderBy('name')->get(),
            'canManageResponsibilityCenters' => $request->user()?->isSuperAdmin() ?? false,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManagement($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:departments,code',
        ]);

        Department::create($validated);

        return redirect()->route('departments.index')->with('success', 'Responsibility center created.');
    }

    public function update(Request $request, Department $department)
    {
        $this->authorizeManagement($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:departments,code,' . $department->id,
        ]);

        $department->update($validated);

        return redirect()->route('departments.index')->with('success', 'Responsibility center updated.');
    }

    public function destroy(Request $request, Department $department)
    {
        $this->authorizeManagement($request);

        if ($department->particulars()->exists()) {
            return redirect()
                ->route('departments.index')
                ->with('error', 'This responsibility center is used by account titles. Reassign or delete those account titles first.');
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Responsibility center deleted.');
    }

    public function exportCsv(Request $request)
    {
        $filename = sprintf('responsibility-centers-%s', now()->format('Ymd-His'));
        $departments = Department::orderBy('name')->get(['name', 'code']);

        $rows = [['responsibility_center', 'code']];
        foreach ($departments as $department) {
            $rows[] = [$department->name, $department->code];
        }

        return SpreadsheetImportExport::downloadXlsx($filename, $rows);
    }

    public function importCsv(Request $request)
    {
        $this->authorizeManagement($request);

        $validated = $request->validate(SpreadsheetImportExport::validationRules('csv_file', false));

        try {
            [$headers, $rows] = SpreadsheetImportExport::readRows($validated['csv_file']);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['csv_file' => $exception->getMessage()]);
        }

        if (empty($headers)) {
            return back()->withErrors(['csv_file' => 'CSV/Excel file is empty.']);
        }

        $required = ['responsibility_center', 'code'];
        $missing = array_diff($required, $headers);
        if ($missing) {
            return back()->withErrors(['csv_file' => 'CSV/Excel must contain these columns: responsibility_center, code.']);
        }

        $index = array_flip($headers);
        $created = 0;
        $updated = 0;
        $seen = [];

        foreach ($rows as $row) {
            if (!array_filter($row, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $name = trim((string) ($row[$index['responsibility_center']] ?? ''));
            $code = strtoupper(trim((string) ($row[$index['code']] ?? '')));

            if ($name === '' || $code === '') {
                continue;
            }

            $key = strtolower($code);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $department = Department::firstOrNew(['code' => $code]);
            $department->name = $name;
            $department->code = $code;
            $department->save();

            $department->wasRecentlyCreated ? $created++ : $updated++;
        }

        return redirect()
            ->route('departments.index')
            ->with('success', "Responsibility centers imported successfully. Created: {$created}, Updated: {$updated}.");
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}
