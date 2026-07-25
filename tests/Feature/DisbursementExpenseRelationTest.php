<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Expense;
use App\Models\Disbursement;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\Department;
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

        $expense = Expense::create([
            'ref_no' => 'EXP26000001',
            'description' => 'Software License Purchase',
            'category_id' => $cat->id,
            'particular_id' => $part->id,
            'amount' => 10000.00,
            'paid' => 0.00,
            'date_encoded' => '2026-07-25',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->post('/disbursements', [
            'expense_id' => $expense->id,
            'description' => 'Partial Payment 1 for Software',
            'source' => 'Expenditure',
            'pay_to' => 'Software Provider Inc.',
            'amount' => 4000.00,
            'method' => 'check',
            'date_encoded' => '2026-07-25',
            'status' => 'posted',
        ]);

        $response->assertRedirect(route('disbursements.index'));

        // Verify Disbursement is created and linked
        $disbursement = Disbursement::where('expense_id', $expense->id)->first();
        $this->assertNotNull($disbursement);
        $this->assertEquals(4000.00, $disbursement->amount);
        $this->assertEquals($expense->id, $disbursement->expense->id);

        // Verify Expense paid amount was updated automatically to 4000.00
        $expense->refresh();
        $this->assertEquals(4000.00, $expense->paid);
    }
}
