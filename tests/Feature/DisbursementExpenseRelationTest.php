<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Expense;
use App\Models\Disbursement;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\Income;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisbursementExpenseRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_disbursement_can_be_linked_to_an_expense_and_updates_paid_amount(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $cat = BudgetCategory::create(['name' => 'General', 'code' => 'GEN']);
        $dept = Department::create(['name' => 'IT Dept', 'code' => 'IT']);
        $part = BudgetParticular::create(['account_code' => 'ACC-001', 'account_name' => 'Software Expense', 'particular' => 'Software', 'category_id' => $cat->id, 'department_id' => $dept->id]);
        $budget = AnnualBudget::create(['year' => 2026, 'semester' => 'Full Year (Jan-Dec)']);
        $budgetItem = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $cat->id,
            'particular_id' => $part->id,
            'month' => 7,
            'appropriation' => 10000,
        ]);
        Income::create([
            'income_no' => 'INC-DSB-REL',
            'receipt_no' => 'OR-DSB-REL',
            'receipt_type' => 'Enrollment',
            'source' => 'Enrollment Collections',
            'description' => 'Cash available for linked disbursement',
            'amount' => 10000,
            'date_encoded' => '2026-07-01',
            'created_by_id' => $user->id,
        ]);

        $expense = Expense::create([
            'ref_no' => 'EXP26000001',
            'description' => 'Software License Purchase',
            'category_id' => $cat->id,
            'particular_id' => $part->id,
            'budget_item_id' => $budgetItem->id,
            'amount' => 10000.00,
            'paid' => 0.00,
            'date_encoded' => '2026-07-25',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->post('/disbursements', [
            'expense_id' => $expense->id,
            'description' => 'Partial Payment 1 for Software',
            'source' => 'Expenditure',
            'pay_to' => 'Software Provider Inc.',
            'amount' => 4000.00,
            'method' => 'check',
            'date_encoded' => '2026-07-25',
            'status' => 'for_approval',
        ]);

        $response->assertRedirect(route('disbursements.index'));

        // Verify Disbursement is created and linked
        $disbursement = Disbursement::where('expense_id', $expense->id)->first();
        $this->assertNotNull($disbursement);
        $this->assertEquals(4000.00, $disbursement->amount);
        $this->assertEquals($expense->id, $disbursement->expense->id);

        $this->assertEquals(0.00, $expense->fresh()->paid);

        $this->actingAs($user)->post("/disbursements/{$disbursement->id}/approve");
        $this->actingAs($user)->post("/disbursements/{$disbursement->id}/post");

        // Verify Expense paid amount was updated automatically to 4000.00
        $expense->refresh();
        $this->assertEquals(4000.00, $expense->paid);
    }
}
