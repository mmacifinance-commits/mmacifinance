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

    public function test_overall_collection_rate_is_weighted_across_all_sources_without_source_quotas(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $period = AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        foreach ([['Tuition', 1000, null], ['Tuition', 500, 'OR-A'], ['Trust Fund', 9000, null], ['Trust Fund', 900, 'OR-B']] as $i => [$source, $amount, $receipt]) {
            Income::create(['income_no' => 'WEIGHT-'.$i, 'source' => $source, 'description' => $source,
                'amount' => $amount, 'receipt_no' => $receipt, 'date_encoded' => '2026-09-01']);
        }
        $this->getJson('/income-breakdown?fiscal_period_id='.$period->id)->assertOk()
            ->assertJsonPath('overall.projected', 10000)->assertJsonPath('overall.actual', 1400)
            ->assertJsonPath('overall.collection_rate', 14)->assertJsonPath('overall.quota', null);
    }

    public function test_overall_quota_is_head_only_and_rates_use_the_correct_denominators(): void
    {
        $period = AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        $payload = ['fiscal_period_id' => $period->id, 'amount' => 10000];
        foreach (['cashier', 'budget_officer', 'disbursement_officer', 'auditor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->putJson('/income-quota', $payload)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->putJson('/income-quota', $payload)->assertOk();
        Income::create(['income_no' => 'QUOTA-RECEIPT', 'receipt_no' => 'OR-Q', 'source' => 'TUITION FEE',
            'description' => 'Tuition', 'amount' => 2500, 'date_encoded' => '2026-09-01']);
        Income::create(['income_no' => 'QUOTA-PROJECTED', 'source' => 'Tuition Fee', 'description' => 'Projection', 'amount' => 5000, 'date_encoded' => '2026-09-01']);
        $this->getJson('/income-breakdown?fiscal_period_id='.$period->id)->assertOk()
            ->assertJsonPath('rows.0.collection_rate', 50)->assertJsonPath('overall.collection_rate', 50)->assertJsonPath('overall.quota_progress', 25)->assertJsonPath('overall.quota_remaining', 7500);
        $this->getJson('/income-breakdown?fiscal_period_id='.$period->id.'&allocation_month=2026-10-01')
            ->assertJsonPath('overall.quota', 10000)->assertJsonPath('overall.actual', 0)->assertJsonPath('overall.collection_rate', null);
        $this->putJson('/income-quota', array_replace($payload, ['amount' => 0]))->assertOk();
        $this->getJson('/income-breakdown?fiscal_period_id='.$period->id)->assertJsonPath('overall.quota_progress', null)->assertJsonPath('overall.collection_rate', 50);
        $period->update(['closed_at' => now()]);
        $this->putJson('/income-quota', $payload)->assertUnprocessable();
    }
}
