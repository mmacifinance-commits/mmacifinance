<?php

namespace App\Http\Controllers;

use App\Models\Disbursement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DisbursementController extends Controller
{
    public function index()
    {
        return Inertia::render('Disbursements/Index', [
            'disbursements' => Disbursement::latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'source' => 'required|string|max:255',
            'pay_to' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:check,cash,bank_transfer',
            'date_encoded' => 'required|date',
            'date_approved' => 'nullable|date',
            'status' => 'required|in:pending,approved,posted,cancelled',
            'notes' => 'nullable|string',
        ]);

        $lastDsb = Disbursement::latest('id')->first();
        $nextNum = $lastDsb ? intval(substr($lastDsb->disbursement_no, 3)) + 1 : 1;
        $validated['disbursement_no'] = 'DSB' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);

        Disbursement::create($validated);

        return redirect()->route('disbursements.index')->with('success', 'Disbursement created.');
    }

    public function update(Request $request, Disbursement $disbursement)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'source' => 'required|string|max:255',
            'pay_to' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:check,cash,bank_transfer',
            'date_encoded' => 'required|date',
            'date_approved' => 'nullable|date',
            'status' => 'required|in:pending,approved,posted,cancelled',
            'notes' => 'nullable|string',
        ]);

        $disbursement->update($validated);

        return redirect()->route('disbursements.index')->with('success', 'Disbursement updated.');
    }

    public function destroy(Disbursement $disbursement)
    {
        $disbursement->delete();

        return redirect()->route('disbursements.index')->with('success', 'Disbursement deleted.');
    }
}
