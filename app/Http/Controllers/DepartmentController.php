<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
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

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}
