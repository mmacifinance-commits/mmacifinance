<?php

namespace Tests\Feature;

use App\Models\{AnnualBudget, AuditTrail, BudgetCategory, BudgetItem, BudgetParticular, Department, Disbursement, Expense, Income, User};
use App\Services\{BudgetUtilizationService, FinancialReportService};
use Illuminate\Http\{Request, UploadedFile};
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class BudgetAllocationParticularsTest extends TestCase
{
    use SafeRefreshDatabase;

    private function fixture(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $category = BudgetCategory::create(['name' => 'Equipment']);
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $account = BudgetParticular::create(['category_id' => $category->id, 'department_id' => $department->id,
            'account_code' => 'OE', 'account_name' => 'Office Equipment', 'particular' => 'Office Equipment']);
        $budget = AnnualBudget::create(['year' => 2026, 'semester' => 'Fiscal Year']);
        Income::create(['source' => 'Tuition', 'description' => 'Projection', 'income_no' => 'INC-TEST', 'amount' => 10000, 'date_encoded' => '2026-08-01']);
        $data = ['category_id' => $category->id, 'department_id' => $department->id, 'particular_id' => $account->id,
            'allocation_month' => '2026-08-01', 'appropriation' => 1000];
        return [$budget, $account, $data];
    }

    public function test_same_account_and_month_accept_distinct_particulars_and_reject_identical_rows(): void
    {
        [$budget, $account, $data] = $this->fixture();
        foreach (['Photocopier', 'Printer'] as $particulars) {
            $this->post("/annual-budgets/{$budget->id}/items", $data + compact('particulars'))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('budget_items', 2);
        $this->assertCount(2, BudgetItem::pluck('ref_no')->unique());
        $this->assertDatabaseCount('budget_particulars', 1);
        $this->assertDatabaseHas('budget_items', ['particular_id' => $account->id, 'particulars' => 'Photocopier']);
        $this->post("/annual-budgets/{$budget->id}/items", $data + ['particulars' => ' photocopier '])->assertSessionHasErrors('particular_id');
        $this->assertDatabaseCount('budget_items', 2);
        $this->assertSame('Printer', AuditTrail::latest('id')->first()->metadata['particulars']);
    }

    public function test_particulars_can_be_edited_and_audited_without_changing_the_account_title(): void
    {
        [$budget, $account, $data] = $this->fixture();
        $item = $budget->items()->create($data + ['particulars' => 'Photocopier']);
        $this->put("/annual-budgets/{$budget->id}/items/{$item->id}", $data + ['particulars' => 'Network photocopier'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Network photocopier', $item->fresh()->particulars);
        $this->assertSame('Office Equipment', $account->fresh()->particular);
        $this->assertSame('Photocopier', AuditTrail::latest('id')->first()->metadata['previous_particulars']);
        $this->put("/annual-budgets/{$budget->id}/items/{$item->id}", $data + ['particulars' => str_repeat('a', 256)])
            ->assertSessionHasErrors('particulars');
    }

    public function test_budget_import_preview_and_export_keep_each_particular_separate(): void
    {
        [$budget] = $this->fixture();
        $csv = "allocation_month,budget_category,responsibility_center,account_title,particulars,appropriation\n2026-08,Equipment,Finance,Office Equipment,Photocopier,1000\n2026-08,Equipment,Finance,Office Equipment,Printer,500\n";
        $file = fn () => UploadedFile::fake()->createWithContent('budget.csv', $csv);
        $this->postJson('/imports/annual-budget-items/preview', ['annual_budget_id' => $budget->id, 'csv_file' => $file()])
            ->assertOk()->assertJsonPath('valid_count', 2)->assertJsonPath('duplicate_count', 0);
        $this->post("/annual-budgets/{$budget->id}/import-csv", ['csv_file' => $file()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('budget_items', 2);
        $this->assertDatabaseCount('budget_particulars', 1);
        $this->postJson('/imports/annual-budget-items/preview', ['annual_budget_id' => $budget->id, 'csv_file' => $file()])
            ->assertOk()->assertJsonPath('invalid_count', 2);
        $response = $this->get("/annual-budgets/{$budget->id}/export-csv")->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $book = IOFactory::load($path);
            $rows = $book->getActiveSheet()->toArray();
            $column = array_search('particulars', $rows[0], true);
            $this->assertNotFalse($column);
            $this->assertSame(['Photocopier', 'Printer'], [$rows[1][$column], $rows[2][$column]]);
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_payment_charges_only_the_selected_particular_and_reports_both_balances(): void
    {
        [$budget, $account, $data] = $this->fixture();
        $copier = $budget->items()->create($data + ['particulars' => 'Photocopier']);
        $printer = $budget->items()->create($data + ['particulars' => 'Printer']);
        $this->assertNull(app(BudgetUtilizationService::class)->resolveBudgetItem($account->category_id, $account->id, '2026-08-01'));
        $expenseData = ['description' => 'Buy photocopier', 'category_id' => $account->category_id, 'particular_id' => $account->id,
            'budget_item_id' => $copier->id, 'amount' => 250, 'date_encoded' => '2026-08-02', 'status' => 'pending'];
        $this->post('/expenses', $expenseData)->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();
        Disbursement::create(['disbursement_no' => 'DSB-TEST', 'expense_id' => $expense->id, 'description' => 'Copier payment',
            'source' => 'Expenditure', 'pay_to' => 'Supplier', 'amount' => 250, 'method' => 'cash', 'date_encoded' => '2026-08-03', 'status' => 'posted']);
        $this->assertSame(750.0, $copier->fresh()->balance);
        $this->assertSame(1000.0, $printer->fresh()->balance);
        $this->post('/expenses', array_replace($expenseData, ['amount' => 751]))->assertSessionHasErrors('amount');
        $report = app(FinancialReportService::class)->build(Request::create('/reports', 'GET', ['fiscal_period_id' => $budget->id]));
        $this->assertSame(['Photocopier', 'Printer'], $report['rows']->pluck('particulars')->all());
        $this->assertEquals([750, 1000], $report['rows']->pluck('balance')->all());
        $this->assertSame('Particulars', $report['sections'][0]['headers'][5]);
        $this->assertSame('Photocopier', $report['disbursementRows'][0]['particulars']);
        $this->get('/expenses?search=Photocopier')->assertInertia(fn ($page) => $page->where('expenses.total', 1)->where('expenses.data.0.budget_item.particulars', 'Photocopier'));
        $this->get('/disbursements')->assertInertia(fn ($page) => $page->where('disbursements.data.0.expense.budget_item.particulars', 'Photocopier'));
        $this->get('/reports')->assertInertia(fn ($page) => $page->where('sections.0.rows.0.5', 'Photocopier'));
    }

    public function test_old_rows_and_imports_without_particulars_remain_supported(): void
    {
        [$budget, , $data] = $this->fixture();
        $this->post("/annual-budgets/{$budget->id}/items", $data)->assertSessionHasNoErrors();
        $this->assertSame('', BudgetItem::first()->particulars);
        $csv = "allocation_month,budget_category,responsibility_center,account_title,appropriation\n2026-09,Equipment,Finance,Office Equipment,500\n";
        $this->post("/annual-budgets/{$budget->id}/import-csv", ['csv_file' => UploadedFile::fake()->createWithContent('old.csv', $csv)])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('budget_items', 2);
    }

    public function test_new_field_does_not_bypass_closed_period_or_role_restrictions(): void
    {
        [$budget, , $data] = $this->fixture();
        $budget->update(['closed_at' => now()]);
        $this->post("/annual-budgets/{$budget->id}/items", $data + ['particulars' => 'Photocopier'])->assertSessionHasErrors();
        $budget->update(['closed_at' => null]);
        $this->actingAs(User::factory()->create(['role' => 'auditor']))
            ->withHeader('X-Inertia', 'true')->post("/annual-budgets/{$budget->id}/items", $data + ['particulars' => 'Printer'])->assertForbidden();
        $this->assertDatabaseCount('budget_items', 0);
    }
}
