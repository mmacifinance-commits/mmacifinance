<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReceiptPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_page_lists_cash_receipt_income_records(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $period = AnnualBudget::create([
            'year' => 2026,
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'semester' => 'Fiscal Year',
        ]);

        Income::create([
            'income_no' => 'INC-ENROLL',
            'receipt_no' => 'OR-ENROLL-001',
            'source' => 'Enrollment Collections',
            'description' => 'Enrollment Fee',
            'amount' => 10000,
            'date_encoded' => '2026-08-01',
        ]);
        Income::create([
            'income_no' => 'INC-MIDTERM',
            'receipt_no' => 'OR-MID-001',
            'source' => 'Midterm Collections',
            'description' => 'Midterm Assessment',
            'amount' => 12000,
            'date_encoded' => '2026-10-01',
        ]);
        Income::create([
            'income_no' => 'INC-FINAL',
            'receipt_no' => 'OR-FINAL-001',
            'source' => 'Final Exam Collections',
            'description' => 'Final Exam Assessment',
            'amount' => 15000,
            'date_encoded' => '2027-03-01',
        ]);

        $this->actingAs($user)
            ->get('/receipts?fiscal_period_id='.$period->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receipts/Index')
                ->where('summary.totalAmount', fn ($value) => (float) $value === 37000.0)
                ->where('summary.recordCount', 3)
                ->where('summary.withReceiptNo', 3)
                ->where('summary.byType.0.type', 'Final Exam')
                ->where('summary.byType.0.amount', fn ($value) => (float) $value === 15000.0)
                ->where('receipts.data.0.receipt_no', 'OR-FINAL-001')
                ->where('receipts.data.0.receipt_type', 'Final Exam')
                ->where('receipts.data.1.receipt_type', 'Midterm')
                ->where('receipts.data.2.receipt_type', 'Enrollment'));
    }

    public function test_auditor_cannot_access_receipts_page(): void
    {
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);

        $this->actingAs($auditor)
            ->withHeader('Accept', 'application/json')
            ->get('/receipts')
            ->assertForbidden();
    }

    public function test_receipts_can_be_exported_and_imported(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CASHIER]);
        AnnualBudget::create([
            'year' => 2026,
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'semester' => 'Fiscal Year',
        ]);

        Income::create([
            'income_no' => 'INC-EXPORT',
            'receipt_no' => 'OR-EXPORT-001',
            'source' => 'Enrollment Collections',
            'description' => 'Enrollment Fee',
            'amount' => 10000,
            'date_encoded' => '2026-08-01',
        ]);

        $export = $this->actingAs($user)->get('/receipts/export-csv')->streamedContent();
        $this->assertStringContainsString('receipt_no', $export);
        $this->assertStringContainsString('receipt_type', $export);
        $this->assertStringContainsString('OR-EXPORT-001', $export);

        $csv = implode("\n", [
            'income_no,receipt_no,receipt_type,source,description,amount,date_encoded,notes',
            'INC-IGNORED,OR-IMPORT-RECEIPT,Midterm,Midterm Collections,Midterm Assessment,12500,2026-10-15,Imported receipt',
            '',
        ]);

        $this->actingAs($user)->post('/receipts/import-csv', [
            'csv_file' => UploadedFile::fake()->createWithContent('receipts.csv', $csv),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('incomes', [
            'receipt_no' => 'OR-IMPORT-RECEIPT',
            'source' => 'Midterm Collections',
            'description' => 'Midterm Assessment',
            'amount' => 12500,
            'notes' => 'Imported receipt',
        ]);
    }
}
