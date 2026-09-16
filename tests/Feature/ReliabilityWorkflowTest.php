<?php

namespace Tests\Feature;

use App\Models\{AnnualBudget, AuditTrail, BudgetCategory, BudgetItem, BudgetParticular, Department, Disbursement, Expense, Income, User};
use App\Services\{CashFlowService, SystemBackupService};
use Illuminate\Support\Facades\{DB, Hash, Log, Route};
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class ReliabilityWorkflowTest extends TestCase
{
    use SafeRefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $category = BudgetCategory::firstOrCreate(['name' => 'AUXILIARY FUND']);
        $department = Department::create(['name' => 'Workflow Finance', 'code' => 'WF']);
        $particular = BudgetParticular::create(['category_id' => $category->id, 'department_id' => $department->id, 'account_code' => 'WF-1', 'account_name' => 'Supplies', 'particular' => 'Supplies']);
        $budget = AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31', 'semester' => 'Fiscal Year']);
        $item = BudgetItem::create(['budget_id' => $budget->id, 'category_id' => $category->id, 'particular_id' => $particular->id, 'allocation_month' => '2026-08-01', 'month' => 8, 'appropriation' => 5000]);
        return [$admin, $budget, $item];
    }

    public function test_create_approve_post_export_and_balances_reconcile(): void
    {
        [$admin, $budget, $item] = $this->fixture();
        $this->actingAs($admin)->post('/receipts', ['receipt_no' => 'WF-OR', 'receipt_type' => 'Tuition', 'source' => 'Collections', 'description' => 'Workflow receipt', 'amount' => 2500.50, 'date_encoded' => '2026-08-03'])->assertSessionHasNoErrors();
        $this->post('/expenses', ['category_id' => $item->category_id, 'particular_id' => $item->particular_id, 'budget_item_id' => $item->id, 'description' => 'Workflow supplies', 'amount' => 1000.25, 'date_encoded' => '2026-08-04', 'status' => 'pending'])->assertSessionHasNoErrors();
        $expense = Expense::where('description', 'Workflow supplies')->sole();
        $this->post("/expenses/{$expense->id}/submit")->assertSessionHasNoErrors();
        $this->post("/expenses/{$expense->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame('approved', $expense->fresh()->status);
        $this->post('/disbursements', ['expense_id' => $expense->id, 'description' => 'Workflow payment', 'source' => 'Expenditure', 'pay_to' => 'Supplier', 'amount' => 1000.25, 'method' => 'cash', 'date_encoded' => '2026-08-05', 'status' => 'draft'])->assertSessionHasNoErrors();
        $payment = Disbursement::where('expense_id', $expense->id)->sole();
        $this->post("/disbursements/{$payment->id}/submit")->assertSessionHasNoErrors();
        $this->post("/disbursements/{$payment->id}/approve")->assertSessionHasNoErrors();
        $this->post("/disbursements/{$payment->id}/post")->assertSessionHasNoErrors();
        $this->assertSame('posted', $payment->fresh()->status);
        $this->assertEquals(1000.25, $expense->fresh()->paid);
        $summary = app(CashFlowService::class)->summary($budget);
        $this->assertSame(2500.50, $summary['receipts']);
        $this->assertSame(1000.25, $summary['postedDisbursements']);
        $this->assertSame(1500.25, $summary['cashOnHand']);
        $this->get('/reports?fiscal_period_id='.$budget->id)->assertInertia(fn ($page) => $page->where('summaryCards.cashOnHand', 1500.25)->where('summaryCards.budgetBalance', 3999.75));
        $this->get('/receipts?fiscal_period_id='.$budget->id)->assertInertia(fn ($page) => $page->where('summary.cashOnHand', 1500.25));
        foreach (['overall_financial', 'budget_utilization', 'cash_receipts', 'disbursements', 'income_vs_receipts', 'fund_balance', 'responsibility_center', 'account_title_ledger', 'closing_report'] as $type) {
            $this->get('/reports/generate?fiscal_period_id='.$budget->id.'&report_type='.$type)->assertOk();
            $this->get('/reports/export?fiscal_period_id='.$budget->id.'&report_type='.$type)->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        $this->post("/disbursements/{$payment->id}/post")->assertSessionHas('error');
        $this->assertSame(1, AuditTrail::where('auditable_type', Disbursement::class)->where('auditable_id', $payment->id)->where('action', 'posted')->count());
        foreach (['submit', 'approve', 'reject', 'return'] as $action) {
            $this->postJson("/disbursements/{$payment->id}/{$action}", ['remarks' => 'Attempt to change posted payment'])->assertUnprocessable();
            $this->assertSame('posted', $payment->fresh()->status);
        }
    }

    public function test_offline_retry_is_idempotent_and_rejects_reused_key_with_different_data(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $headers = ['X-Offline-Sync' => 'true', 'X-Offline-Action-Id' => (string) Str::uuid()];
        $data = ['source' => 'Collections', 'description' => 'Offline income', 'amount' => 123.45, 'date_encoded' => '2026-08-03'];
        $first = $this->actingAs($admin)->postJson('/income', $data, $headers)->assertCreated();
        $this->postJson('/income', $data, $headers)->assertCreated()->assertJsonPath('id', $first->json('id'));
        $this->assertSame(1, Income::where('description', 'Offline income')->count());
        $this->postJson('/income', [...$data, 'amount' => 999], $headers)->assertConflict();
        $this->assertEquals(123.45, Income::first()->amount);
    }

    public function test_failed_offline_validation_does_not_consume_retry_key(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $headers = ['X-Offline-Sync' => 'true', 'X-Offline-Action-Id' => (string) Str::uuid()];
        $this->actingAs($admin)->postJson('/income', [], $headers)->assertUnprocessable();
        $this->assertSame(0, AuditTrail::where('action', 'offline_synced')->count());
        $this->postJson('/income', ['source' => 'Collection', 'description' => 'Retried', 'amount' => 1, 'date_encoded' => '2026-08-03'], $headers)->assertCreated();
    }

    public function test_offline_update_conflict_preserves_server_values(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $income = Income::create(['income_no' => 'CONFLICT', 'source' => 'Collections', 'description' => 'Server value', 'amount' => 50, 'date_encoded' => '2026-08-03']);
        $headers = ['X-Offline-Sync' => 'true', 'X-Offline-Action-Id' => (string) Str::uuid(), 'X-Offline-Base-Version' => '2020-01-01T00:00:00.000000Z'];
        $this->actingAs($admin)->putJson('/income/'.$income->id, ['source' => 'Collections', 'description' => 'Stale edit', 'amount' => 75, 'date_encoded' => '2026-08-03'], $headers)->assertConflict();
        $this->assertSame('Server value', $income->fresh()->description);
        $this->assertSame(0, AuditTrail::where('action', 'offline_synced')->count());
    }

    public function test_backup_restores_accounts_values_and_relationships_into_memory(): void
    {
        [$admin, $budget, $item] = $this->fixture();
        Income::create(['income_no' => 'BACKUP-1', 'source' => 'Collections', 'description' => "Quotes, commas\nand new lines", 'amount' => 123.45, 'date_encoded' => '2026-08-03', 'notes' => null]);
        $service = app(SystemBackupService::class);
        $path = $service->create();
        config(['database.connections.restore_drill' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $target = DB::connection('restore_drill');
        try {
            $manifest = $service->restoreForVerification($path, $target);
            foreach ($manifest['tables'] as $table => $entry) {
                $this->assertEquals(DB::table($table)->orderBy($entry['columns'][0])->get()->toArray(), $target->table($table)->orderBy($entry['columns'][0])->get()->toArray(), $table);
            }
            $this->assertTrue(Hash::check('password', $target->table('users')->where('id', $admin->id)->value('password')));
            $this->assertNull($target->table('incomes')->value('notes'));
            $this->assertSame($budget->id, $target->table('budget_items')->where('id', $item->id)->value('budget_id'));
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Restore target must be empty');
            $service->restoreForVerification($path, $target);
        } finally {
            unlink($path);
            DB::purge('restore_drill');
        }
    }

    public function test_server_errors_are_logged_without_exposing_details(): void
    {
        Log::spy();
        Route::middleware('web')->get('/_test/failure', fn () => throw new \RuntimeException('private database diagnostic'));
        $response = $this->getJson('/_test/failure')->assertStatus(500)->assertDontSee('private database diagnostic');
        $response->assertHeader('X-Request-Id');
        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_preview_does_not_save_until_confirm_and_pagination_spans_all_records(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $file = "receipt_no,receipt_type,source,description,amount,date_encoded\nPREVIEW-1,Tuition,Collections,Preview receipt,100,2026-08-03";
        $this->actingAs($admin)->postJson('/imports/receipts/preview', ['csv_file' => UploadedFile::fake()->createWithContent('receipts.csv', $file)])->assertOk()->assertJsonPath('valid_count', 1);
        $this->assertDatabaseMissing('incomes', ['receipt_no' => 'PREVIEW-1']);
        $this->post('/receipts/import-csv', ['csv_file' => UploadedFile::fake()->createWithContent('receipts.csv', $file)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incomes', ['receipt_no' => 'PREVIEW-1']);
        for ($i = 0; $i < 26; $i++) Department::create(['name' => "Pagination $i", 'code' => "PG$i"]);
        $this->get('/departments')->assertInertia(fn ($page) => $page->has('departments.data', 25)->where('departments.total', 26));
        $this->get('/departments?page=2')->assertInertia(fn ($page) => $page->has('departments.data', 1)->where('departments.current_page', 2));
        $this->get('/receipts?search=PREVIEW-1')->assertInertia(fn ($page) => $page->has('receipts.data', 1));
        $this->get('/receipts?search=DOES-NOT-EXIST')->assertInertia(fn ($page) => $page->has('receipts.data', 0));
    }

    public function test_generated_report_includes_more_than_25_receipts_and_xlsx_total_matches(): void
    {
        [$admin, $budget] = $this->fixture();
        for ($i=0; $i<30; $i++) Income::create(['income_no' => "REPORT-$i", 'receipt_no' => "OR-$i", 'receipt_type' => 'Tuition', 'source' => 'Collections', 'description' => 'Report row', 'amount' => 10.25, 'date_encoded' => '2026-08-03']);
        $query = '?fiscal_period_id='.$budget->id.'&report_type=cash_receipts';
        $this->actingAs($admin)->get('/reports/generate'.$query)->assertInertia(fn ($page) => $page->has('receiptRows', 30));
        $response = $this->get('/reports/export'.$query)->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();
        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
            $totalRow = null;
            for ($row=1; $row<=$sheet->getHighestRow(); $row++) {
                if ($sheet->getCell("A{$row}")->getValue() === 'TOTAL RECEIPTS') $totalRow = $row;
            }
            $this->assertNotNull($totalRow);
            $this->assertStringStartsWith('=SUM(', $sheet->getCell("I{$totalRow}")->getValue());
            $this->assertEquals(307.50, $sheet->getCell("I{$totalRow}")->getCalculatedValue());
        } finally { unlink($file); }
    }

    public function test_preview_validates_dates_and_totals_all_rows_beyond_preview_limit(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $csv = "receipt_no,receipt_type,source,description,amount,date_encoded\n";
        for ($i=0; $i<110; $i++) $csv .= "P-$i,Tuition,Collections,Preview,10,2026-08-03\n";
        $this->actingAs($admin)->postJson('/imports/receipts/preview', ['csv_file' => UploadedFile::fake()->createWithContent('preview.csv', $csv)])
            ->assertOk()->assertJsonPath('valid_count', 110)->assertJsonPath('total_amount', 1100)->assertJsonCount(100, 'valid_rows');
        $this->postJson('/imports/receipts/preview', ['csv_file' => UploadedFile::fake()->createWithContent('invalid.csv', str_replace('2026-08-03', 'not-a-date', $csv))])
            ->assertOk()->assertJsonPath('invalid_count', 110)->assertJsonPath('valid_count', 0);
        $this->assertDatabaseCount('incomes', 0);
    }
}
