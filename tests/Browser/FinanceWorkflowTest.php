<?php

namespace Tests\Browser;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FinanceWorkflowTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_head_of_finance_can_click_through_core_finance_pages(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $category = BudgetCategory::create(['name' => 'AUXILIARY FUND']);
        $department = Department::create(['name' => 'Finance Office', 'code' => 'FIN']);
        $particular = BudgetParticular::create([
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '5-0001',
            'account_name' => 'Office Supplies',
            'particular' => 'Office Supplies',
        ]);
        $budget = AnnualBudget::create([
            'year' => 2026,
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'semester' => 'Fiscal Year',
        ]);
        $item = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'month' => 8,
            'allocation_month' => '2026-08-01',
            'appropriation' => 10000,
            'expenditure' => 0,
        ]);
        Income::create([
            'income_no' => 'INC-2026-0001',
            'receipt_no' => 'OR-2026-0001',
            'receipt_type' => 'Enrollment',
            'source' => 'Enrollment Collections',
            'description' => 'Enrollment receipt',
            'amount' => 10000,
            'date_encoded' => '2026-08-01',
        ]);
        Expense::create([
            'ref_no' => 'EXP00000001',
            'description' => 'Office supplies',
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'budget_item_id' => $item->id,
            'amount' => 1000,
            'paid' => 0,
            'date_encoded' => '2026-08-02',
            'status' => 'approved',
        ]);

        $this->browse(function (Browser $browser) use ($user, $budget) {
            $browser->loginAs($user)
                ->visit('/')
                ->waitForText('Dashboard', 10)
                ->clickLink('BUDGET')
                ->waitForText('Annual Budget', 10)
                ->visit("/annual-budgets/{$budget->id}")
                ->waitForText('Annual Budget Allocations', 10)
                ->press('Export CSV')
                ->visit('/receipts')
                ->waitForText('Receipts', 10)
                ->press('Add Receipt')
                ->waitForText('Add Receipt', 10)
                ->press('Cancel')
                ->visit('/expenses')
                ->waitForText('Expenditures', 10)
                ->press('Add Expense')
                ->waitForText('Add Expense', 10)
                ->press('Cancel')
                ->visit('/disbursements')
                ->waitForText('Disbursements', 10)
                ->press('Create Payment Release')
                ->waitForText('Create Disbursement', 10)
                ->press('Cancel')
                ->visit('/reports')
                ->waitForText('Financial', 10);
        });
    }
}
