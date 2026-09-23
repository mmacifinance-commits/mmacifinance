<?php

namespace Tests\Feature;

use App\Models\{AnnualBudget, Income, User};
use App\Services\CashFlowService;
use Illuminate\Http\UploadedFile;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class ProjectedIncomeSeparationTest extends TestCase
{
    use SafeRefreshDatabase;

    private function records(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        $fields = ['source' => 'Tuition', 'description' => 'Tuition forecast', 'amount' => 1000, 'date_encoded' => '2026-08-02'];
        $projected = Income::create(['income_no' => 'INC-PROJECTED'] + $fields);
        $receipt = Income::create(['income_no' => 'INC-LEGACY', 'receipt_no' => 'OR-LEGACY', 'receipt_type' => 'Tuition', 'amount' => 300] + $fields);
        return [$projected, $receipt, $fields];
    }

    public function test_pages_separate_existing_records_without_modifying_them(): void
    {
        [$projected, $receipt] = $this->records();
        $before = Income::orderBy('id')->get()->toArray();
        $this->get('/income')->assertOk()->assertInertia(fn ($page) => $page
            ->where('incomeRecords.total', 1)->where('incomeRecords.data.0.id', $projected->id)->where('stats.totalRevenue', 1000));
        $this->get('/receipts')->assertOk()->assertInertia(fn ($page) => $page->where('summary.totalAmount', 300));
        $this->get('/iaeo')->assertOk();
        $this->get('/reports')->assertOk()->assertInertia(fn ($page) => $page->where('reconciliationWarnings', []));
        $this->assertSame(300.0, app(CashFlowService::class)->totalReceipts());
        $this->assertSame($before, Income::orderBy('id')->get()->toArray());
    }

    public function test_income_cannot_change_or_delete_receipts_or_create_new_receipts(): void
    {
        [, $receipt, $fields] = $this->records();
        $before = $receipt->fresh()->getAttributes();
        $this->post('/income', ['receipt_no' => 'OR-NEW', 'receipt_type' => 'Tuition'] + $fields)->assertSessionHasErrors('receipt_no');
        $this->put('/income/'.$receipt->id, $fields)->assertSessionHasErrors('income');
        $this->delete('/income/'.$receipt->id)->assertSessionHasErrors('income');
        $this->postJson('/financial-records/income/bulk-delete', ['scope' => 'all'])->assertOk()->assertJsonPath('count', 1);
        $this->assertSame($before, $receipt->fresh()->getAttributes());
        $this->assertDatabaseCount('incomes', 2);
    }

    public function test_import_cannot_overwrite_matching_receipt_or_partially_save_mixed_file(): void
    {
        [, $receipt] = $this->records();
        $before = $receipt->fresh()->getAttributes();
        $csv = "source,description,amount,date_encoded,notes\nTuition,Tuition forecast,1200,2026-08-02,Updated forecast";
        $this->post('/income/import-csv', ['csv_file' => UploadedFile::fake()->createWithContent('income.csv', $csv)])->assertSessionHasNoErrors();
        $this->assertSame($before, $receipt->fresh()->getAttributes());
        $mixed = "source,description,amount,date_encoded,notes,receipt_no\nNew,Forecast,10,2026-08-02,,\nTuition,Collection,300,2026-08-02,,OR-NEW";
        $this->post('/income/import-csv', ['csv_file' => UploadedFile::fake()->createWithContent('income.csv', $mixed)])->assertSessionHasErrors('csv_file');
        $this->assertDatabaseCount('incomes', 2);
    }
}
