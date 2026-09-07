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

class ExpenseBudgetAllocationSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_september_expense_can_explicitly_charge_an_august_allocation(): void
    {
        [$user, $category, $particular, $augustItem, $septemberItem] = $this->fixtures();

        $response = $this->actingAs($user)->post('/expenses', [
            'description' => 'August supplies encoded in September',
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'budget_item_id' => $augustItem->id,
            'amount' => 1000,
            'date_encoded' => '2026-09-07',
            'status' => 'pending',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHasNoErrors();

        $expense = Expense::where('description', 'August supplies encoded in September')->firstOrFail();
        $this->assertSame($augustItem->id, $expense->budget_item_id);

        Disbursement::create([
            'disbursement_no' => 'DSB-AUGUST-CHARGE',
            'expense_id' => $expense->id,
            'description' => 'September payment for August expense',
            'source' => 'Expenditure',
            'pay_to' => 'Supplier',
            'amount' => 1000,
            'method' => 'check',
            'date_encoded' => '2026-09-08',
            'status' => Disbursement::STATUS_POSTED,
        ]);

        $monthlyTotals = app(BudgetUtilizationService::class)->totalsByAllocationMonth(2026);

        $this->assertSame(1000.0, (float) $monthlyTotals->get(8, 0));
        $this->assertSame(0.0, (float) $monthlyTotals->get(9, 0));
        $this->assertSame(0.0, app(BudgetUtilizationService::class)->expenditureForItem($septemberItem));
    }

    public function test_selected_allocation_must_match_the_expense_fiscal_year(): void
    {
        [$user, $category, $particular, $augustItem] = $this->fixtures();
        $nextYearBudget = AnnualBudget::create(['year' => 2027, 'semester' => 'Full Year (Jan-Dec)']);
        BudgetItem::create([
            'budget_id' => $nextYearBudget->id,
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'month' => 9,
            'appropriation' => 10000,
        ]);

        $response = $this->actingAs($user)
            ->from(route('expenses.index'))
            ->post('/expenses', [
                'description' => 'Wrong fiscal year',
                'category_id' => $category->id,
                'particular_id' => $particular->id,
                'budget_item_id' => $augustItem->id,
                'amount' => 1000,
                'date_encoded' => '2027-09-07',
                'status' => 'pending',
            ]);

        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHasErrors('budget_item_id');
        $this->assertDatabaseMissing('expenses', ['description' => 'Wrong fiscal year']);
    }

    private function fixtures(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $department = Department::create(['name' => 'Human Resource and Development Office', 'code' => 'HRDO']);
        $category = BudgetCategory::create(['name' => 'Office Supplies']);
        $particular = BudgetParticular::create([
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '5-6016',
            'account_name' => 'Office Supplies',
            'particular' => 'Office Supplies',
        ]);
        $budget = AnnualBudget::create(['year' => 2026, 'semester' => 'Full Year (Jan-Dec)']);
        $augustItem = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'month' => 8,
            'appropriation' => 30000,
        ]);
        $septemberItem = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'month' => 9,
            'appropriation' => 10000,
        ]);

        return [$user, $category, $particular, $augustItem, $septemberItem];
    }
}
