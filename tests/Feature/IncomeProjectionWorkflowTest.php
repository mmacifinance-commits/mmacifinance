<?php

namespace Tests\Feature;

use App\Models\{AnnualBudget, Income, User};
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class IncomeProjectionWorkflowTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_student_calculation_and_filtered_comparison_keep_receipts_separate(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $period = AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        $payload = ['source' => 'Tuition Fee', 'description' => 'Projected tuition', 'amount' => 1,
            'student_count' => 20, 'fee_per_student' => 500.50, 'date_encoded' => '2026-09-01'];
        $this->post('/income', $payload)->assertSessionHasNoErrors();
        $income = Income::projected()->firstOrFail();
        $this->assertEquals(10010, $income->amount);
        $this->put('/income/'.$income->id, array_replace($payload, ['student_count' => 30]))->assertSessionHasNoErrors();
        $this->assertEquals(15015, $income->fresh()->amount);
        $this->post('/income', array_replace($payload, ['student_count' => -1]))->assertSessionHasErrors('student_count');
        $this->post('/receipts', ['receipt_no' => 'OR-1', 'receipt_type' => 'Enrollment', 'source' => ' tuition fee ',
            'description' => 'Actual tuition', 'amount' => 2500, 'date_encoded' => '2026-09-05'])->assertSessionHasNoErrors();
        $this->getJson('/income-breakdown?fiscal_period_id='.$period->id.'&allocation_month=2026-09-01')
            ->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.projected', 15015)
            ->assertJsonPath('rows.0.actual', 2500)->assertJsonPath('rows.0.difference', 12515);
        $this->getJson('/income-breakdown?fiscal_period_id='.$period->id.'&allocation_month=2026-10-01')->assertJsonCount(0, 'rows');
        $this->getJson('/income-breakdown?start_date=2026-10-01&end_date=2026-09-01')->assertUnprocessable();
    }

    public function test_only_head_can_write_receipts_and_cashier_cannot_write_expenses(): void
    {
        foreach (['budget_officer', 'disbursement_officer', 'cashier', 'auditor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->postJson('/receipts', [])->assertForbidden();
            $this->postJson('/receipts/import-csv', [])->assertForbidden();
            $this->postJson('/imports/receipts/preview', [])->assertForbidden();
            $this->postJson('/income', [])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'cashier']));
        $this->postJson('/expenses', [])->assertForbidden();
        $this->postJson('/expenses/import-csv', [])->assertForbidden();
        $this->postJson('/imports/expenses/preview', [])->assertForbidden();
        $this->postJson('/disbursements', [])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'budget_officer']));
        $this->postJson('/expenses', [])->assertUnprocessable();
        $this->postJson('/disbursements', [])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'disbursement_officer']));
        $this->post('/departments', ['name' => 'Test Center', 'code' => 'TC'])->assertSessionHasNoErrors();
        $this->postJson('/annual-budgets', [])->assertUnprocessable();
    }

    public function test_quotas_are_head_only_and_collection_rate_uses_receipts_not_projection(): void
    {
        $period = AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        $payload = ['fiscal_period_id' => $period->id, 'source' => 'Tuition Fee', 'amount' => 10000];
        foreach (['cashier', 'budget_officer', 'disbursement_officer', 'auditor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->putJson('/income-quota', $payload)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->putJson('/income-quota', $payload)->assertOk();
        Income::create(['income_no' => 'QUOTA-RECEIPT', 'receipt_no' => 'OR-Q', 'source' => 'TUITION FEE',
            'description' => 'Tuition', 'amount' => 2500, 'date_encoded' => '2026-09-01']);
        $this->getJson('/income-breakdown?fiscal_period_id='.$period->id)->assertOk()
            ->assertJsonPath('rows.0.collection_rate', 25)->assertJsonPath('rows.0.quota_remaining', 7500);
        $this->getJson('/income-breakdown?fiscal_period_id='.$period->id.'&allocation_month=2026-10-01')
            ->assertJsonPath('rows.0.quota', 10000)->assertJsonPath('rows.0.actual', 0)->assertJsonPath('rows.0.collection_rate', 0);
        $this->putJson('/income-quota', array_replace($payload, ['amount' => 0]))->assertOk();
        $this->getJson('/income-breakdown?fiscal_period_id='.$period->id)->assertJsonPath('rows.0.collection_rate', null);
        $period->update(['closed_at' => now()]);
        $this->putJson('/income-quota', $payload)->assertUnprocessable();
    }
}
