<?php

namespace App\Http\Controllers;

use App\Models\Department;
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
        $filename = sprintf('responsibility-centers-%s.csv', now()->format('Ymd-His'));
        $departments = Department::orderBy('name')->get(['name', 'code']);

        return Response::streamDownload(function () use ($departments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'code']);

            foreach ($departments as $department) {
                fputcsv($out, [$department->name, $department->code]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importCsv(Request $request)
    {
        $this->authorizeManagement($request);

        $validated = $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $handle = fopen($validated['csv_file']->getRealPath(), 'r');
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), fgetcsv($handle) ?: []);
        $required = ['name', 'code'];
        $missing = array_diff($required, $headers);
        if ($missing) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV must contain these columns: name, code.']);
        }

        $index = array_flip($headers);
        $created = 0;
        $updated = 0;
        $seen = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (!array_filter($row, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $name = trim((string) ($row[$index['name']] ?? ''));
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

        fclose($handle);

        return redirect()
            ->route('departments.index')
            ->with('success', "Responsibility centers imported successfully. Created: {$created}, Updated: {$updated}.");
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}
