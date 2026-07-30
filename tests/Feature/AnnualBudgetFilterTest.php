<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualBudgetFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_annual_budgets_show_page_loads_with_all_budgets_and_available_years()
    {
        $budget1 = AnnualBudget::create(['year' => 2025, 'semester' => '1st Semester']);
        $budget2 = AnnualBudget::create(['year' => 2026, 'semester' => null]);

        $response = $this->actingAs($this->user)->get("/annual-budgets/{$budget1->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('AnnualBudgets/Show')
            ->has('budget')
            ->has('allBudgets', 2)
            ->has('availableYears', 2)
            ->where('budget.id', $budget1->id)
        );
    }
}
