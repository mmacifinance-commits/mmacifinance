<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\User;
use App\Models\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAndDisbursementEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected BudgetCategory $category;
    protected Department $department;
    protected BudgetParticular $accountTitle;
    protected Expense $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->cashier = User::factory()->create([
            'role' => User::ROLE_CASHIER,
        ]);

        $this->department = Department::create([
            'name' => 'Finance Dept',
            'code' => 'FIN',
        ]);

        $this->category = BudgetCategory::create([
            'name' => 'Operational Expenses',
            'description' => 'OpEx Category',
        ]);

        $this->accountTitle = BudgetParticular::create([
            'category_id' => $this->category->id,
            'department_id' => $this->department->id,
            'account_code' => '50201010',
            'account_name' => 'Office Supplies Expense',
            'particular' => 'Paper & Printing Supplies',
            'description' => 'Supplies for printing',
        ]);

        $this->expense = Expense::create([
            'ref_no' => 'EXP-2026-0001',
            'description' => 'Bond Paper Purchase',
            'category_id' => $this->category->id,
            'particular_id' => $this->accountTitle->id,
            'amount' => 5000.00,
            'paid' => 0.00,
            'date_encoded' => '2026-01-15',
            'status' => 'pending',
        ]);
    }

    public function test_annual_budget_and_monthly_item_auto_ref_no_generation()
    {
        $budget = AnnualBudget::create([
            'year' => 2026,
            'semester' => '1st Semester',
        ]);

        $this->assertNotNull($budget->ref_no);
        $this->assertStringStartsWith('AB-2026-', $budget->ref_no);

        $item = $budget->items()->create([
            'category_id' => $this->category->id,
            'particular_id' => $this->accountTitle->id,
            'month' => 2, // February
            'appropriation' => 10000.00,
            'expenditure' => 0.00,
        ]);

        $this->assertNotNull($item->ref_no);
        $this->assertStringStartsWith('MB-2026-02-', $item->ref_no);
    }

    public function test_cashier_can_create_disbursement_and_submit_for_approval()
    {
        $response = $this->actingAs($this->cashier)->post('/disbursements', [
            'expense_id' => $this->expense->id,
            'description' => 'Payment for supplies',
            'source' => 'Operational Expenses',
            'pay_to' => 'ABC Supplies Corp',
            'amount' => 2500.00,
            'method' => 'check',
            'date_encoded' => '2026-01-16',
            'status' => 'for_approval',
            'notes' => 'Release details entered by Cashier',
        ]);

        $response->assertRedirect('/disbursements');

        $this->assertDatabaseHas('disbursements', [
            'pay_to' => 'ABC Supplies Corp',
            'amount' => 2500.00,
            'status' => 'for_approval',
            'prepared_by_id' => $this->cashier->id,
        ]);
    }

    public function test_cashier_cannot_approve_or_post_disbursements()
    {
        $disbursement = Disbursement::create([
            'disbursement_no' => 'DSB00000001',
            'expense_id' => $this->expense->id,
            'description' => 'Test Disbursement',
            'source' => 'Operational Expenses',
            'pay_to' => 'Supplier',
            'amount' => 1000.00,
            'method' => 'check',
            'date_encoded' => '2026-01-16',
            'status' => 'for_approval',
            'prepared_by_id' => $this->cashier->id,
        ]);

        $response = $this->actingAs($this->cashier)->postJson("/disbursements/{$disbursement->id}/approve", [
            'remarks' => 'Unauthorized attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_unposted_disbursement_does_not_affect_expenditure_and_posting_updates_it()
    {
        $disbursement = Disbursement::create([
            'disbursement_no' => 'DSB00000002',
            'expense_id' => $this->expense->id,
            'description' => 'Test Disbursement',
            'source' => 'Operational Expenses',
            'pay_to' => 'Supplier',
            'amount' => 2000.00,
            'method' => 'check',
            'date_encoded' => '2026-01-16',
            'status' => 'for_approval',
        ]);

        // Verify expense paid amount remains 0 while unposted
        $this->assertEquals(0.00, $this->expense->fresh()->paid);

        // Head of Finance approves
        $this->actingAs($this->superAdmin)->post("/disbursements/{$disbursement->id}/approve", [
            'remarks' => 'Approved by Head of Finance',
        ]);

        $disbursement->refresh();
        $this->assertEquals('approved', $disbursement->status);
        $this->assertEquals(0.00, $this->expense->fresh()->paid); // Still 0 until posted!

        // Head of Finance posts to ledger
        $this->actingAs($this->superAdmin)->post("/disbursements/{$disbursement->id}/post", [
            'remarks' => 'Posted to GL',
        ]);

        $disbursement->refresh();
        $this->assertEquals('posted', $disbursement->status);
        $this->assertEquals(2000.00, $this->expense->fresh()->paid); // Updated ONLY after posting!
    }

    public function test_cashier_cannot_create_disbursement_with_posted_or_approved_status()
    {
        foreach (['approved', 'posted'] as $status) {
            $response = $this->actingAs($this->cashier)->postJson('/disbursements', [
                'expense_id' => $this->expense->id,
                'description' => 'Bypass attempt',
                'source' => 'Operational Expenses',
                'pay_to' => 'Supplier',
                'amount' => 2000.00,
                'method' => 'check',
                'date_encoded' => '2026-01-16',
                'status' => $status,
            ]);

            $response->assertStatus(422)->assertJsonValidationErrors('status');
        }

        $this->assertDatabaseCount('disbursements', 0);
        $this->assertEquals(0.00, $this->expense->fresh()->paid);
    }

    public function test_cashier_cannot_update_disbursement_status_to_posted()
    {
        $disbursement = Disbursement::create([
            'disbursement_no' => 'DSB00000010',
            'expense_id' => $this->expense->id,
            'description' => 'Test Disbursement',
            'source' => 'Operational Expenses',
            'pay_to' => 'Supplier',
            'amount' => 2000.00,
            'method' => 'check',
            'date_encoded' => '2026-01-16',
            'status' => 'for_approval',
            'prepared_by_id' => $this->cashier->id,
        ]);

        $response = $this->actingAs($this->cashier)->putJson("/disbursements/{$disbursement->id}", [
            'expense_id' => $this->expense->id,
            'description' => 'Test Disbursement',
            'source' => 'Operational Expenses',
            'pay_to' => 'Supplier',
            'amount' => 2000.00,
            'method' => 'check',
            'date_encoded' => '2026-01-16',
            'status' => 'posted',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertEquals('for_approval', $disbursement->fresh()->status);
        $this->assertEquals(0.00, $this->expense->fresh()->paid);
    }

    public function test_cashier_cannot_modify_or_delete_a_posted_disbursement()
    {
        $disbursement = Disbursement::create([
            'disbursement_no' => 'DSB00000011',
            'expense_id' => $this->expense->id,
            'description' => 'Posted Disbursement',
            'source' => 'Operational Expenses',
            'pay_to' => 'Supplier',
            'amount' => 2000.00,
            'method' => 'check',
            'date_encoded' => '2026-01-16',
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->cashier)->putJson("/disbursements/{$disbursement->id}", [
            'expense_id' => $this->expense->id,
            'description' => 'Reverted',
            'source' => 'Operational Expenses',
            'pay_to' => 'Supplier',
            'amount' => 2000.00,
            'method' => 'check',
            'date_encoded' => '2026-01-16',
            'status' => 'draft',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('posted', $disbursement->fresh()->status);

        $this->actingAs($this->cashier)
            ->deleteJson("/disbursements/{$disbursement->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('disbursements', ['id' => $disbursement->id]);
    }

    public function test_audit_trail_stamps_are_recorded_on_disbursement_actions()
    {
        $disbursement = Disbursement::create([
            'disbursement_no' => 'DSB00000003',
            'expense_id' => $this->expense->id,
            'description' => 'Test Disbursement with Audit Stamps',
            'source' => 'Operational Expenses',
            'pay_to' => 'Supplier',
            'amount' => 1500.00,
            'method' => 'check',
            'date_encoded' => '2026-01-16',
            'status' => 'for_approval',
            'prepared_by_id' => $this->cashier->id,
        ]);

        $this->actingAs($this->superAdmin)->post("/disbursements/{$disbursement->id}/approve", [
            'remarks' => 'Approved with remarks',
        ]);

        $disbursement->refresh();

        $this->assertDatabaseHas('audit_trails', [
            'auditable_type' => Disbursement::class,
            'auditable_id' => $disbursement->id,
            'action' => 'approved',
            'user_name' => $this->superAdmin->name,
            'remarks' => 'Approved with remarks',
        ]);
    }
}
