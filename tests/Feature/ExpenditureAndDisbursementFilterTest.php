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

class ExpenditureAndDisbursementFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_expenses_index_returns_available_years_and_default_year(): void
    {
        $user = User::factory()->create();

        $cat = BudgetCategory::create(['name' => 'General', 'code' => 'GEN']);
        $dept = Department::create(['name' => 'IT Dept', 'code' => 'IT']);
        $part = BudgetParticular::create(['account_code' => 'ACC-001', 'account_name' => 'Software Expense', 'particular' => 'Software', 'category_id' => $cat->id, 'department_id' => $dept->id]);

        Expense::create([
            'ref_no' => 'EXP25000001',
            'description' => 'Test Expense 2025',
            'category_id' => $cat->id,
            'particular_id' => $part->id,
            'amount' => 1000,
            'paid' => 500,
            'date_encoded' => '2025-05-10',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get('/expenses');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Expenses/Index')
            ->has('availableYears')
            ->has('defaultYear')
        );
    }

    public function test_disbursements_index_returns_available_years_and_default_year(): void
    {
        $user = User::factory()->create();

        Disbursement::create([
            'disbursement_no' => 'DSB25000001',
            'description' => 'Test Disbursement 2025',
            'source' => 'Expenditure',
            'pay_to' => 'Supplier A',
            'amount' => 500,
            'method' => 'check',
            'date_encoded' => '2025-05-15',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get('/disbursements');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Disbursements/Index')
            ->has('availableYears')
            ->has('defaultYear')
        );
    }
}
