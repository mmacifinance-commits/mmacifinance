<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
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
