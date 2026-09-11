<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualBudgetItemUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_monthly_allocation_returns_a_clear_validation_error(): void
    {
        [$user, $budget, $accountTitle] = $this->budgetFixtures();
        $augustItem = $this->createItem($budget, $accountTitle, 8);
        $septemberItem = $this->createItem($budget, $accountTitle, 9);

        $response = $this->actingAs($user)
            ->from(route('annual-budgets.show', $budget))
            ->put(route('annual-budgets.items.update', [$budget, $septemberItem]), [
                'category_id' => $accountTitle->category_id,
                'department_id' => $accountTitle->department_id,
                'particular_id' => $accountTitle->id,
                'month' => 8,
                'appropriation' => 10000,
            ]);

        $response->assertRedirect(route('annual-budgets.show', $budget));
        $response->assertSessionHasErrors([
            'month' => "An allocation already exists for {$accountTitle->particular} in August FY 2026. Edit the existing row or choose another month.",
        ]);
        $this->assertSame(9, $septemberItem->fresh()->month);
        $this->assertSame(8, $augustItem->fresh()->month);
    }

    public function test_moving_an_allocation_to_an_available_month_preserves_its_reference(): void
    {
        [$user, $budget, $accountTitle] = $this->budgetFixtures();
        $item = $this->createItem($budget, $accountTitle, 9);

        Income::create([
            'income_no' => 'INC-2026-0001',
            'source' => 'Tuition',
            'description' => 'Test income',
            'amount' => 50000,
            'date_encoded' => '2026-01-01',
            'created_by_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->put(
            route('annual-budgets.items.update', [$budget, $item]),
            [
                'category_id' => $accountTitle->category_id,
                'department_id' => $accountTitle->department_id,
                'particular_id' => $accountTitle->id,
                'month' => 10,
                'appropriation' => 10000,
            ]
        );

        $response->assertRedirect(route('annual-budgets.show', $budget));
        $response->assertSessionHasNoErrors();
        $this->assertSame(10, $item->fresh()->month);
        $this->assertSame('MB-2026-09-0001', $item->fresh()->ref_no);
    }

    private function budgetFixtures(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $department = Department::create(['name' => 'Human Resources', 'code' => 'HR']);
        $category = BudgetCategory::create(['name' => 'Office Supplies']);
        $accountTitle = BudgetParticular::create([
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '5-6016',
            'account_name' => 'Office Supplies',
            'particular' => 'Office Supplies',
        ]);
        $budget = AnnualBudget::create([
            'year' => 2026,
            'semester' => 'Full Year (Jan-Dec)',
        ]);

        return [$user, $budget, $accountTitle];
    }

    private function createItem(AnnualBudget $budget, BudgetParticular $accountTitle, int $month): BudgetItem
    {
        return BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $accountTitle->category_id,
            'particular_id' => $accountTitle->id,
            'month' => $month,
            'appropriation' => 10000,
        ]);
    }
}
