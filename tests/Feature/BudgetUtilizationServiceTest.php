<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetUtilizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetUtilizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private BudgetItem $item;
    private Expense $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $department = Department::create(['name' => 'College of Computing and Library Studies', 'code' => 'CCLS']);
        $category = BudgetCategory::create(['name' => 'TRUST FUND']);
        $particular = BudgetParticular::create([
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '5-02-03-010',
            'account_name' => 'Paper and Ink Supplies',
            'particular' => 'Paper and Ink Supplies',
            'description' => 'Paper and ink supplies',
        ]);
        $budget = AnnualBudget::create(['year' => 2026, 'semester' => 'Full Year (Jan-Dec)']);
        $this->item = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'month' => 1,
            'appropriation' => 50000,
        ]);
        $this->expense = Expense::create([
            'ref_no' => 'EXP00000001',
            'description' => 'Supplies Expense',
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'budget_item_id' => $this->item->id,
            'amount' => 20000,
            'date_encoded' => '2026-08-27',
            'status' => 'approved',
        ]);
    }

    public function test_only_posted_disbursements_utilize_the_budget(): void
    {
        $this->makeDisbursement('DSB-PENDING', 20000, 'pending');
        $this->makeDisbursement('DSB-APPROVED', 20000, 'approved');
        $this->makeDisbursement('DSB-REJECTED', 20000, 'rejected');

        $this->assertSame(0.0, $this->service()->expenditureForItem($this->item));

        $this->makeDisbursement('DSB-POSTED', 20000, 'posted');

        $this->assertSame(20000.0, $this->service()->expenditureForItem($this->item));
    }

    public function test_partial_and_multiple_posted_disbursements_sum_without_counting_expense_amount(): void
    {
        $this->makeDisbursement('DSB-PART-1', 10000, 'posted');
        $this->assertSame(10000.0, $this->service()->expenditureForItem($this->item));

        $this->makeDisbursement('DSB-PART-2', 10000, 'posted');
        $this->assertSame(20000.0, $this->service()->expenditureForItem($this->item));
    }

    public function test_hydrated_annual_and_monthly_totals_reconcile(): void
    {
        $this->makeDisbursement('DSB-POSTED', 20000, 'posted');

        $items = BudgetItem::whereKey($this->item->id)->get();
        $this->service()->hydrateItems($items);
        $item = $items->first();

        $this->assertSame(20000.0, $item->postedExpenditureTotal());
        $this->assertSame(30000.0, (float) $item->balance);
        $this->assertSame(40.0, (float) $item->utilization_rate);
        $this->assertSame(
            20000.0,
            (float) Disbursement::where('status', Disbursement::STATUS_POSTED)
                ->whereHas('expense', fn ($query) => $query->where('budget_item_id', $item->id))
                ->sum('amount')
        );
    }

    public function test_financial_pages_share_posted_allocation_totals(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->makeDisbursement('DSB-POSTED', 20000, 'posted');

        Disbursement::create([
            'disbursement_no' => 'DSB-PENDING-LATEST',
            'expense_id' => $this->expense->id,
            'description' => 'Pending payment',
            'source' => 'Expenditure',
            'pay_to' => 'Supplier',
            'amount' => 9000,
            'method' => 'check',
            'date_encoded' => '2026-09-01',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get('/?year=2026&month=1')
            ->assertInertia(fn ($page) => $page
                ->where('stats.totalExpenditure', fn ($value) => (float) $value === 20000.0)
                ->where('stats.totalTransactions', 1)
                ->has('recentDisbursements', 1)
                ->where('recentDisbursements.0.status', Disbursement::STATUS_POSTED));

        $this->actingAs($user)
            ->get('/income?year=2026&month=1')
            ->assertInertia(fn ($page) => $page->where('stats.totalExpense', fn ($value) => (float) $value === 20000.0));

        $this->actingAs($user)
            ->get('/iaeo?year=2026&month=1')
            ->assertInertia(fn ($page) => $page->where('stats.totalExpense', fn ($value) => (float) $value === 20000.0));

        $this->actingAs($user)
            ->get('/reports?year=2026&month=1')
            ->assertInertia(fn ($page) => $page
                ->where('selectedMonthPerformance.expenditure', fn ($value) => (float) $value === 20000.0));

        $this->actingAs($user)
            ->get('/annual-budgets')
            ->assertInertia(fn ($page) => $page
                ->where('budgets.0.items.0.expenditure', fn ($value) => (float) $value === 20000.0)
                ->where('budgets.0.items.0.balance', fn ($value) => (float) $value === 30000.0)
                ->where('budgets.0.items.0.utilization_rate', fn ($value) => (float) $value === 40.0));
    }

    public function test_financial_report_filters_reconcile_all_selected_dimensions(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->makeDisbursement('DSB-PRIMARY-POSTED', 20000, 'posted');

        $otherDepartment = Department::create(['name' => 'Finance Office', 'code' => 'FIN']);
        $otherCategory = BudgetCategory::create(['name' => 'OPERATING FUND']);
        $otherParticular = BudgetParticular::create([
            'category_id' => $otherCategory->id,
            'department_id' => $otherDepartment->id,
            'account_code' => '5-02-03-011',
            'account_name' => 'Other Supplies',
            'particular' => 'Other Supplies',
        ]);
        $otherItem = BudgetItem::create([
            'budget_id' => $this->item->budget_id,
            'category_id' => $otherCategory->id,
            'particular_id' => $otherParticular->id,
            'month' => 1,
            'appropriation' => 30000,
        ]);
        $otherExpense = Expense::create([
            'ref_no' => 'EXP-OTHER-FILTER',
            'description' => 'Other filtered expense',
            'category_id' => $otherCategory->id,
            'particular_id' => $otherParticular->id,
            'budget_item_id' => $otherItem->id,
            'amount' => 5000,
            'date_encoded' => '2026-08-27',
            'status' => 'posted',
        ]);
        Disbursement::create([
            'disbursement_no' => 'DSB-OTHER-POSTED',
            'expense_id' => $otherExpense->id,
            'description' => 'Other posted payment',
            'source' => 'Expenditure',
            'pay_to' => 'Other Supplier',
            'amount' => 5000,
            'method' => 'check',
            'date_encoded' => '2026-08-27',
            'status' => Disbursement::STATUS_POSTED,
        ]);

        $this->actingAs($user)
            ->get('/reports?year=2026&month=1&start_date=2026-08-27&end_date=2026-08-27')
            ->assertInertia(fn ($page) => $page
                ->where('selectedMonthPerformance.month_label', 'January')
                ->where('selectedMonthPerformance.appropriation', fn ($value) => (float) $value === 80000.0)
                ->where('selectedMonthPerformance.expenditure', fn ($value) => (float) $value === 25000.0)
                ->where('yearEndUnusedBalances.0.expenditure', fn ($value) => (float) $value === 25000.0));

        $this->actingAs($user)
            ->get('/reports?year=2026&month=1&department_id=' . $this->item->particular->department_id)
            ->assertInertia(fn ($page) => $page
                ->where('selectedMonthPerformance.appropriation', fn ($value) => (float) $value === 50000.0)
                ->where('selectedMonthPerformance.expenditure', fn ($value) => (float) $value === 20000.0));

        $this->actingAs($user)
            ->get('/reports?year=2026&month=1&category_id=' . $otherCategory->id)
            ->assertInertia(fn ($page) => $page
                ->where('selectedMonthPerformance.appropriation', fn ($value) => (float) $value === 30000.0)
                ->where('selectedMonthPerformance.expenditure', fn ($value) => (float) $value === 5000.0));

        $this->actingAs($user)
            ->get('/reports?year=2026&month=1&end_date=2026-08-26')
            ->assertInertia(fn ($page) => $page
                ->where('selectedMonthPerformance.appropriation', fn ($value) => (float) $value === 80000.0)
                ->where('selectedMonthPerformance.expenditure', fn ($value) => (float) $value === 0.0));

        $this->actingAs($user)
            ->get('/reports?year=2026&month=1&start_date=2026-09-01&end_date=2026-08-01')
            ->assertInertia(fn ($page) => $page
                ->where('filters.start_date', '2026-08-01')
                ->where('filters.end_date', '2026-09-01')
                ->where('selectedMonthPerformance.expenditure', fn ($value) => (float) $value === 25000.0));
    }

    private function makeDisbursement(string $reference, float $amount, string $status): Disbursement
    {
        return Disbursement::create([
            'disbursement_no' => $reference,
            'expense_id' => $this->expense->id,
            'description' => 'Supplies payment',
            'source' => 'Expenditure',
            'pay_to' => 'Supplier',
            'amount' => $amount,
            'method' => 'check',
            'date_encoded' => '2026-08-27',
            'status' => $status,
        ]);
    }

    private function service(): BudgetUtilizationService
    {
        return app(BudgetUtilizationService::class);
    }
}
