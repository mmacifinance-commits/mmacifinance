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
use Tests\SafeRefreshDatabase as RefreshDatabase;
use Tests\TestCase;

class ExpenseBudgetAllocationSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_keeps_allocation_options_out_of_initial_response_and_loads_them_on_demand(): void
    {
        [$user, , , $item] = $this->fixtures();
        $initial = $this->actingAs($user)->get('/expenses')->assertOk()->assertInertia(fn ($page) => $page
            ->missing('budgetedCategories')->missing('particulars')->where('expenses.per_page', 25));

        $response = $this->get('/expenses', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $initial->viewData('page')['version'],
            'X-Inertia-Partial-Component' => 'Expenses/Index',
            'X-Inertia-Partial-Data' => 'budgetedCategories,particulars',
        ])->assertOk();
        $options = collect($response->json('props.budgetedCategories'))->flatMap(fn ($category) => $category['budget_items']);
        $allocation = $options->firstWhere('id', $item->id);
        $this->assertNotNull($allocation);
        $this->assertEquals($item->appropriation, $allocation['balance']);
        $this->assertNotEmpty($response->json('props.particulars'));
    }

    public function test_filters_search_all_expenses_before_paginating(): void
    {
        [$user, $category, $particular, $item] = $this->fixtures();
        foreach (range(1, 31) as $number) {
            Expense::create(['ref_no' => 'EXP-SEARCH-'.$number, 'description' => $number === 1 ? 'Old matching expense' : 'Other expense',
                'category_id' => $category->id, 'particular_id' => $particular->id, 'budget_item_id' => $item->id,
                'amount' => 10, 'date_encoded' => '2026-08-10', 'status' => 'pending']);
        }
        $this->actingAs($user)->get('/expenses?search=Old%20matching&status=pending&category_id='.$category->id.'&fiscal_period_id='.$item->budget_id)
            ->assertOk()->assertInertia(fn ($page) => $page->where('expenses.total', 1)
            ->where('expenses.data.0.ref_no', 'EXP-SEARCH-1'));
        $this->get('/expenses?page=2')->assertOk()->assertInertia(fn ($page) => $page
            ->where('expenses.total', 31)->has('expenses.data', 6));
        $this->get('/expenses?status=approved')->assertOk()->assertInertia(fn ($page) => $page->where('expenses.total', 0));
    }

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

    public function test_expense_amount_cannot_exceed_the_selected_allocations_available_balance(): void
    {
        [$user, $category, $particular, $augustItem] = $this->fixtures();
        $existingExpense = Expense::create([
            'ref_no' => 'EXP-POSTED-BALANCE',
            'description' => 'Previously posted expense',
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'budget_item_id' => $augustItem->id,
            'amount' => 10000,
            'date_encoded' => '2026-08-15',
            'status' => 'posted',
        ]);
        Disbursement::create([
            'disbursement_no' => 'DSB-POSTED-BALANCE',
            'expense_id' => $existingExpense->id,
            'description' => 'Posted allocation charge',
            'source' => 'Expenditure',
            'pay_to' => 'Supplier',
            'amount' => 10000,
            'method' => 'check',
            'date_encoded' => '2026-08-16',
            'status' => Disbursement::STATUS_POSTED,
        ]);

        $response = $this->actingAs($user)
            ->from(route('expenses.index'))
            ->post('/expenses', [
                'description' => 'Expense above remaining allocation',
                'category_id' => $category->id,
                'particular_id' => $particular->id,
                'budget_item_id' => $augustItem->id,
                'amount' => 20000.01,
                'date_encoded' => '2026-09-07',
                'status' => 'pending',
            ]);

        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('expenses', ['description' => 'Expense above remaining allocation']);
    }

    public function test_index_batches_allocation_totals_instead_of_querying_during_serialization(): void
    {
        [$user, $category, $particular, $item] = $this->fixtures();
        Expense::create([
            'ref_no' => 'EXP-PERFORMANCE',
            'description' => 'Allocation serialization',
            'category_id' => $category->id,
            'particular_id' => $particular->id,
            'budget_item_id' => $item->id,
            'amount' => 100,
            'date_encoded' => '2026-08-15',
            'status' => 'pending',
        ]);

        $queries = [];
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });
        $this->withoutVite();
        $this->actingAs($user)->get('/expenses')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Expenses/Index')
                ->where('expenses.data.0.budget_item.balance', 30000));

        $individualTotals = array_filter($queries, fn ($sql) => str_contains($sql, 'sum(') && str_contains($sql, 'disbursements')
            && ! str_contains($sql, 'group by'));
        $this->assertCount(0, $individualTotals, 'Serialization must not query totals per allocation.');
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
