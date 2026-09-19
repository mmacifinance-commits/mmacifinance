<?php

namespace Tests\Feature;

use App\Models\{AnnualBudget, AuditTrail, BudgetCategory, BudgetItem, BudgetParticular, Department, Disbursement, Expense, Income, IncomeAllocation, User};
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class BulkFinancialDeletionTest extends TestCase
{
    use SafeRefreshDatabase;

    private function income(string $number, bool $receipt = false): Income
    {
        return Income::create(['income_no' => $number, 'receipt_no' => $receipt ? 'OR-'.$number : null, 'source' => 'Collections', 'description' => 'Test record', 'amount' => 100, 'date_encoded' => '2026-08-02']);
    }

    private function preview(string $module, array $data): string
    {
        return $this->postJson("/financial-records/{$module}/bulk-delete", $data)->assertOk()->json('token');
    }

    private function expense(): Expense
    {
        $budget = AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        $category = BudgetCategory::firstOrCreate(['name' => 'Test']);
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $particular = BudgetParticular::create(['category_id' => $category->id, 'department_id' => $department->id, 'account_code' => 'TEST', 'account_name' => 'Test', 'particular' => 'Test']);
        $item = BudgetItem::create(['budget_id' => $budget->id, 'category_id' => $category->id, 'particular_id' => $particular->id, 'allocation_month' => '2026-08-01', 'appropriation' => 1000]);
        return Expense::create(['ref_no' => 'EXP-TEST', 'category_id' => $category->id, 'particular_id' => $particular->id, 'budget_item_id' => $item->id, 'description' => 'Test', 'amount' => 100, 'date_encoded' => '2026-08-02', 'status' => 'pending']);
    }

    public function test_selected_deletion_requires_preview_and_confirmation_and_keeps_other_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $one = $this->income('ONE'); $two = $this->income('TWO');
        $data = ['scope' => 'selected', 'ids' => [$one->id]];
        $token = $this->preview('income', $data);
        $this->assertDatabaseCount('incomes', 2);
        $this->postJson('/financial-records/income/bulk-delete', [...$data, 'token' => $token])->assertUnprocessable();
        $this->postJson('/financial-records/income/bulk-delete', [...$data, 'token' => $token, 'confirmation' => 'DELETE'])->assertOk();
        $this->assertDatabaseMissing('incomes', ['id' => $one->id]);
        $this->assertDatabaseHas('incomes', ['id' => $two->id]);
        $this->assertTrue(AuditTrail::where('action', 'deleted')->where('auditable_id', $one->id)->exists());
    }

    public function test_all_receipts_ignores_pagination_but_does_not_delete_non_receipt_income(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $keep = $this->income('KEEP');
        // Cross multiple server batches as well as UI pages.
        for ($i = 0; $i < 405; $i++) $this->income('R'.$i, true);
        $token = $this->preview('receipts', ['scope' => 'all']);
        $this->postJson('/financial-records/receipts/bulk-delete', ['scope' => 'all', 'token' => $token, 'confirmation' => 'DELETE'])->assertOk();
        $this->assertDatabaseCount('incomes', 1);
        $this->assertDatabaseHas('incomes', ['id' => $keep->id]);
    }

    public function test_changed_all_selection_is_not_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->income('ONE');
        $token = $this->preview('income', ['scope' => 'all']);
        $this->income('TWO');
        $this->postJson('/financial-records/income/bulk-delete', ['scope' => 'all', 'token' => $token, 'confirmation' => 'DELETE'])->assertUnprocessable();
        $this->assertDatabaseCount('incomes', 2);
    }

    public function test_protected_record_rolls_back_whole_batch_and_audit_logs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->income('FREE'); $funded = $this->income('FUNDED');
        $expense = $this->expense();
        IncomeAllocation::create(['income_id' => $funded->id, 'annual_budget_id' => $expense->budgetItem->budget_id, 'budget_item_id' => $expense->budget_item_id, 'amount' => 10]);
        $token = $this->preview('income', ['scope' => 'all']);
        $this->postJson('/financial-records/income/bulk-delete', ['scope' => 'all', 'token' => $token, 'confirmation' => 'DELETE'])->assertUnprocessable()->assertJsonValidationErrors('deletion');
        $this->assertDatabaseCount('incomes', 2);
        $this->assertSame(0, AuditTrail::where('action', 'deleted')->count());
        $this->deleteJson('/income/'.$funded->id)->assertUnprocessable();
    }

    public function test_linked_expense_is_protected_and_posted_payment_deletion_recalculates_paid(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $expense = $this->expense();
        $expense->update(['paid' => 50, 'status' => 'posted']);
        $payment = Disbursement::create(['disbursement_no' => 'DSB-TEST', 'expense_id' => $expense->id, 'description' => 'Test', 'source' => 'Expense', 'pay_to' => 'Vendor', 'amount' => 50, 'method' => 'cash', 'date_encoded' => '2026-08-02', 'status' => 'posted']);
        $this->deleteJson('/expenses/'.$expense->id)->assertUnprocessable();
        $data = ['scope' => 'all']; $token = $this->preview('disbursements', $data);
        $this->postJson('/financial-records/disbursements/bulk-delete', [...$data, 'token' => $token, 'confirmation' => 'DELETE'])->assertOk();
        $this->assertEquals(0, $expense->fresh()->paid);
        $token = $this->preview('expenses', $data);
        $this->postJson('/financial-records/expenses/bulk-delete', [...$data, 'token' => $token, 'confirmation' => 'DELETE'])->assertOk();
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_role_restrictions_and_closed_periods(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'auditor']));
        foreach (['income', 'receipts', 'expenses', 'disbursements'] as $module) {
            $this->postJson("/financial-records/{$module}/bulk-delete", ['scope' => 'all'])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->income('LOCKED');
        AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31', 'closed_at' => now()]);
        $token = $this->preview('income', ['scope' => 'all']);
        $this->postJson('/financial-records/income/bulk-delete', ['scope' => 'all', 'token' => $token, 'confirmation' => 'DELETE'])->assertUnprocessable();
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_manage_items_opens_without_income_and_explains_missing_funding(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $budget = AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        $this->get('/annual-budgets/'.$budget->id)->assertOk()->assertInertia(fn ($page) => $page
            ->component('AnnualBudgets/Show')->where('setupWarning', fn ($message) => str_contains($message, 'No income records')));
    }

    public function test_receipts_covering_payments_cannot_be_removed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $receipt = $this->income('CASH', true);
        $expense = $this->expense();
        Disbursement::create(['disbursement_no' => 'DSB-CASH', 'expense_id' => $expense->id, 'description' => 'Test', 'source' => 'Expense', 'pay_to' => 'Vendor', 'amount' => 50, 'method' => 'cash', 'date_encoded' => '2026-08-02', 'status' => 'draft']);
        $token = $this->preview('receipts', ['scope' => 'all']);
        $this->postJson('/financial-records/receipts/bulk-delete', ['scope' => 'all', 'token' => $token, 'confirmation' => 'DELETE'])
            ->assertUnprocessable()->assertJsonValidationErrors('deletion');
        $this->assertDatabaseHas('incomes', ['id' => $receipt->id]);
    }

    public function test_confirmation_cannot_be_reused_by_another_user_or_after_expiration(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        $this->income('KEEP');
        $token = $this->preview('income', ['scope' => 'all']);
        $data = ['scope' => 'all', 'token' => $token, 'confirmation' => 'DELETE'];
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->postJson('/financial-records/income/bulk-delete', $data)->assertUnprocessable();
        $this->actingAs($admin);
        $this->travel(6)->minutes();
        $this->postJson('/financial-records/income/bulk-delete', $data)->assertUnprocessable();
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_cashier_cannot_delete_finalized_payments_in_bulk(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier']));
        $expense = $this->expense();
        $payment = Disbursement::create(['disbursement_no' => 'DSB-LOCK', 'expense_id' => $expense->id, 'description' => 'Test', 'source' => 'Expense', 'pay_to' => 'Vendor', 'amount' => 50, 'method' => 'cash', 'date_encoded' => '2026-08-02', 'status' => 'posted']);
        $token = $this->preview('disbursements', ['scope' => 'all']);
        $this->postJson('/financial-records/disbursements/bulk-delete', ['scope' => 'all', 'token' => $token, 'confirmation' => 'DELETE'])->assertForbidden();
        $this->assertDatabaseHas('disbursements', ['id' => $payment->id]);
        $this->postJson('/financial-records/income/bulk-delete', ['scope' => 'all'])->assertForbidden();
    }
}
