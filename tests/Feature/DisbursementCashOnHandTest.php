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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisbursementCashOnHandTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_disbursement_requires_enough_receipts_cash_on_hand(): void
    {
        [$user, $disbursement] = $this->approvedDisbursementFixture(10000);

        Income::create([
            'income_no' => 'INC-CASH-001',
            'receipt_no' => 'OR-CASH-001',
            'source' => 'Enrollment Collections',
            'description' => 'Actual cash receipt',
            'amount' => 5000,
            'date_encoded' => '2026-08-01',
        ]);

        $this->actingAs($user)
            ->post(route('disbursements.post', $disbursement), [
                'remarks' => 'Attempt posting without enough cash.',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame('approved', $disbursement->fresh()->status);
        $this->assertSame('approved', $disbursement->expense->fresh()->status);
    }

    public function test_posting_disbursement_uses_receipts_as_actual_cash_on_hand(): void
    {
        [$user, $disbursement] = $this->approvedDisbursementFixture(10000);

        Income::create([
            'income_no' => 'INC-CASH-001',
            'receipt_no' => 'OR-CASH-001',
            'source' => 'Enrollment Collections',
            'description' => 'Actual cash receipt',
            'amount' => 12000,
            'date_encoded' => '2026-08-01',
        ]);

        $this->actingAs($user)
            ->post(route('disbursements.post', $disbursement), [
                'remarks' => 'Post with enough cash.',
            ])
            ->assertRedirect(route('disbursements.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('posted', $disbursement->fresh()->status);
        $this->assertSame('posted', $disbursement->expense->fresh()->status);
        $this->assertEquals(10000, (float) $disbursement->expense->fresh()->paid);
    }

    public function test_receipts_page_reports_cash_on_hand_after_posted_disbursements(): void
    {
        [$user, $disbursement] = $this->approvedDisbursementFixture(10000);

        Income::create([
            'income_no' => 'INC-CASH-001',
            'receipt_no' => 'OR-CASH-001',
            'source' => 'Enrollment Collections',
            'description' => 'Actual cash receipt',
            'amount' => 12000,
            'date_encoded' => '2026-08-01',
        ]);

        $this->actingAs($user)->post(route('disbursements.post', $disbursement));

        $this->actingAs($user)
            ->get('/receipts?fiscal_period_id='.$disbursement->expense->budgetItem->budget_id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.totalAmount', fn ($value) => (float) $value === 12000.0)
                ->where('summary.postedDisbursements', fn ($value) => (float) $value === 10000.0)
                ->where('summary.cashOnHand', fn ($value) => (float) $value === 2000.0));
    }

    private function approvedDisbursementFixture(float $amount): array
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $category = BudgetCategory::where('name', 'AUXILIARY FUND')->firstOrFail();
        $department = Department::create(['name' => 'Finance Office', 'code' => 'FIN']);
        $accountTitle = BudgetParticular::create([
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '5-6016',
            'account_name' => 'Office Supplies',
            'particular' => 'Office Supplies',
        ]);
        $budget = AnnualBudget::create([
            'year' => 2026,
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'semester' => 'Fiscal Year',
        ]);
        $allocation = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'particular_id' => $accountTitle->id,
            'month' => 8,
            'allocation_month' => '2026-08-01',
            'appropriation' => 30000,
        ]);
        $expense = Expense::create([
            'ref_no' => 'EXP00000001',
            'description' => 'Approved supplies expense',
            'category_id' => $category->id,
            'particular_id' => $accountTitle->id,
            'budget_item_id' => $allocation->id,
            'amount' => $amount,
            'paid' => 0,
            'date_encoded' => '2026-08-15',
            'status' => 'approved',
        ]);
        $disbursement = Disbursement::create([
            'disbursement_no' => 'DSB00000001',
            'expense_id' => $expense->id,
            'description' => 'Approved payment',
            'source' => 'Expenditure',
            'pay_to' => 'Supplier',
            'amount' => $amount,
            'method' => 'check',
            'date_encoded' => '2026-08-21',
            'status' => 'approved',
            'prepared_by_id' => $user->id,
            'approved_by_id' => $user->id,
        ]);

        return [$user, $disbursement];
    }
}
