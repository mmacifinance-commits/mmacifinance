<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Services\BudgetUtilizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConfigurableFiscalYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_calendar_fiscal_year_is_one_record_with_august_to_july_months(): void
    {
        $budget = $this->crossCalendarBudget();

        $this->assertSame('FY 2026-2027', $budget->fiscal_year_label);
        $this->assertSame('Aug 1, 2026 - Jul 31, 2027', $budget->period_label);
        $this->assertTrue($budget->containsDate('2026-08-01'));
        $this->assertTrue($budget->containsDate('2027-01-15'));
        $this->assertTrue($budget->containsDate('2027-07-31'));
        $this->assertFalse($budget->containsDate('2027-08-01'));
        $this->assertSame(
            ['Aug 2026', 'Sep 2026', 'Oct 2026', 'Nov 2026', 'Dec 2026', 'Jan 2027', 'Feb 2027', 'Mar 2027', 'Apr 2027', 'May 2027', 'Jun 2027', 'Jul 2027'],
            $budget->orderedFiscalMonths()->pluck('short_label')->all()
        );
        $this->assertDatabaseCount('annual_budgets', 1);
    }

    public function test_calendar_year_budget_remains_backward_compatible(): void
    {
        $budget = AnnualBudget::create(['year' => 2025, 'semester' => 'Fiscal Year']);
        [$category, $particular] = $this->account();
        $item = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'month' => 1,
            'appropriation' => 50000,
        ]);

        $this->assertSame('FY 2025', $budget->fiscal_year_label);
        $this->assertSame('2025-01-01', $item->allocation_month->format('Y-m-d'));
    }

    public function test_overlapping_and_invalid_fiscal_periods_are_rejected(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->crossCalendarBudget();
        Income::create([
            'income_no' => 'INC-OVERLAP',
            'source' => 'Fees',
            'description' => 'Period income',
            'amount' => 100000,
            'date_encoded' => '2027-08-01',
        ]);

        $this->actingAs($user)->post('/annual-budgets', [
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ])->assertSessionHasErrors('start_date');

        $this->actingAs($user)->post('/annual-budgets', [
            'start_date' => '2027-08-01',
            'end_date' => '2028-06-30',
        ])->assertSessionHasErrors('end_date');
    }

    public function test_allocation_must_be_inside_parent_period(): void
    {
        $budget = $this->crossCalendarBudget();
        [$category, $particular] = $this->account();

        $this->expectException(ValidationException::class);
        BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'allocation_month' => '2027-08-01',
            'appropriation' => 50000,
        ]);
    }

    public function test_january_expense_uses_cross_calendar_period_without_separate_2027_budget(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        [$budget, $category, $particular, $item] = $this->allocation('2027-01-01');

        $this->actingAs($user)->post('/expenses', [
            'description' => 'January supplies',
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'budget_item_id' => $item->id,
            'amount' => 10000,
            'date_encoded' => '2027-01-15',
            'status' => 'pending',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expenses', ['description' => 'January supplies', 'budget_item_id' => $item->id]);
        $this->assertDatabaseMissing('annual_budgets', ['year' => 2027]);
        $this->assertSame(2026, $budget->year);
    }

    public function test_expense_outside_linked_fiscal_period_is_rejected(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        [, $category, $particular, $item] = $this->allocation('2026-08-01');

        $this->actingAs($user)->post('/expenses', [
            'description' => 'Outside period',
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'budget_item_id' => $item->id,
            'amount' => 1000,
            'date_encoded' => '2027-08-01',
            'status' => 'pending',
        ])->assertSessionHasErrors('budget_item_id');
    }

    public function test_posting_month_remains_distinct_from_allocation_month_and_only_posted_amounts_utilize(): void
    {
        [$budget, $category, $particular, $item] = $this->allocation('2026-08-01');
        $expense = Expense::create([
            'ref_no' => 'EXP-FY-0001',
            'description' => 'August allocation',
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'budget_item_id' => $item->id,
            'amount' => 20000,
            'date_encoded' => '2026-08-20',
            'status' => 'approved',
        ]);

        foreach (['pending', 'approved', 'rejected', 'cancelled', 'voided', 'reversed'] as $index => $status) {
            $this->disbursement($expense, "DSB-NONPOSTED-{$index}", 20000, $status, '2026-09-05');
        }
        $this->assertSame(0.0, app(BudgetUtilizationService::class)->expenditureForItem($item));

        $this->disbursement($expense, 'DSB-POSTED-1', 10000, Disbursement::STATUS_POSTED, '2026-09-05');
        $this->assertSame(10000.0, app(BudgetUtilizationService::class)->expenditureForItem($item));

        $this->disbursement($expense, 'DSB-POSTED-2', 10000, Disbursement::STATUS_POSTED, '2026-10-05');
        $service = app(BudgetUtilizationService::class);
        $this->assertSame(20000.0, $service->expenditureForItem($item));
        $this->assertSame(20000.0, (float) $service->totalsByFiscalAllocationMonth($budget)->get('2026-08-01'));
        $this->assertSame('2026-09-05', Disbursement::where('disbursement_no', 'DSB-POSTED-1')->first()->date_encoded->format('Y-m-d'));
    }

    public function test_income_dashboard_iaeo_and_reports_use_the_same_fiscal_period(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        [$budget, $category, $particular, $item] = $this->allocation('2026-08-01');
        Income::create([
            'income_no' => 'INC-FY-0001',
            'source' => 'Tuition',
            'description' => 'January collection',
            'amount' => 100000,
            'date_encoded' => '2027-01-10',
        ]);
        Income::create([
            'income_no' => 'INC-OUTSIDE',
            'source' => 'Tuition',
            'description' => 'Outside collection',
            'amount' => 900000,
            'date_encoded' => '2027-08-01',
        ]);
        $expense = Expense::create([
            'ref_no' => 'EXP-RECONCILE',
            'description' => 'Reconciled expense',
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'budget_item_id' => $item->id,
            'amount' => 20000,
            'date_encoded' => '2026-08-20',
            'status' => 'approved',
        ]);
        $this->disbursement($expense, 'DSB-RECONCILE', 20000, 'posted', '2026-09-05');

        $query = '?fiscal_period_id='.$budget->id;
        $this->actingAs($user)->get('/'.$query)->assertInertia(fn ($page) => $page
            ->where('stats.totalExpenditure', fn ($value) => (float) $value === 20000.0)
            ->where('monthlyBreakdown.0.month', 'Aug 2026')
            ->where('monthlyBreakdown.5.month', 'Jan 2027'));
        $this->actingAs($user)->get('/income'.$query)->assertInertia(fn ($page) => $page
            ->where('stats.totalRevenue', fn ($value) => (float) $value === 100000.0)
            ->where('stats.totalExpense', fn ($value) => (float) $value === 20000.0));
        $this->actingAs($user)->get('/iaeo'.$query)->assertInertia(fn ($page) => $page
            ->where('stats.totalExpense', fn ($value) => (float) $value === 20000.0)
            ->where('monthlyRevenue.0.allocation_month', '2026-08-01'));
        $this->actingAs($user)->get('/reports'.$query)->assertInertia(fn ($page) => $page
            ->where('selectedMonthPerformance.expenditure', fn ($value) => (float) $value === 20000.0));
        $this->actingAs($user)->get('/annual-budgets')->assertInertia(fn ($page) => $page
            ->where('budgets.0.items.0.expenditure', fn ($value) => (float) $value === 20000.0));
    }

    public function test_monthly_allocation_csv_preserves_fiscal_period_and_exact_month(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $budget = $this->crossCalendarBudget();
        $this->account();
        Income::create([
            'income_no' => 'INC-CSV-FY',
            'source' => 'Tuition',
            'description' => 'Fiscal income',
            'amount' => 100000,
            'date_encoded' => '2026-08-01',
        ]);
        $csv = implode("\n", [
            'fiscal_year,fiscal_year_label,fiscal_start_date,fiscal_end_date,allocation_month,budget_category,responsibility_center,account_title,appropriation',
            '2026,FY 2026-2027,2026-08-01,2027-07-31,2027-01,TRUST FUND,Finance Office,Office Supplies,10000',
        ]);

        $this->actingAs($user)->post(route('annual-budgets.import-csv', $budget), [
            'csv_file' => UploadedFile::fake()->createWithContent('allocations.csv', $csv),
        ])->assertSessionHasNoErrors();

        $item = BudgetItem::where('budget_id', $budget->id)->firstOrFail();
        $this->assertSame('2027-01-01', $item->allocation_month->format('Y-m-d'));

        $content = $this->actingAs($user)
            ->get(route('annual-budgets.export-csv', $budget))
            ->streamedContent();
        $this->assertStringContainsString('fiscal_year_label', $content);
        $this->assertStringContainsString('fiscal_start_date', $content);
        $this->assertStringContainsString('fiscal_end_date', $content);
        $this->assertStringContainsString('allocation_month', $content);
        $this->assertStringContainsString('2027-01', $content);
    }

    private function crossCalendarBudget(): AnnualBudget
    {
        return AnnualBudget::create([
            'year' => 2026,
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'semester' => 'Fiscal Year',
        ]);
    }

    private function account(): array
    {
        $department = Department::firstOrCreate(['code' => 'FIN'], ['name' => 'Finance Office']);
        $category = BudgetCategory::firstOrCreate(['name' => 'TRUST FUND']);
        $particular = BudgetParticular::firstOrCreate(['account_code' => '5-001'], [
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_name' => 'Office Supplies',
            'particular' => 'Office Supplies',
        ]);

        return [$category, $particular];
    }

    private function allocation(string $month): array
    {
        $budget = $this->crossCalendarBudget();
        [$category, $particular] = $this->account();
        $item = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'allocation_month' => $month,
            'appropriation' => 50000,
        ]);

        return [$budget, $category, $particular, $item];
    }

    private function disbursement(Expense $expense, string $reference, float $amount, string $status, string $date): Disbursement
    {
        return Disbursement::create([
            'disbursement_no' => $reference,
            'expense_id' => $expense->id,
            'description' => 'Payment',
            'source' => 'Expenditure',
            'pay_to' => 'Supplier',
            'amount' => $amount,
            'method' => 'check',
            'date_encoded' => $date,
            'status' => $status,
        ]);
    }
}
