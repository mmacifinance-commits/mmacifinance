<?php

namespace Tests\Feature;

use App\Models\{AnnualBudget, BudgetCategory, Department, User};
use Illuminate\Support\Facades\DB;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class ManageItemsMemoryTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_large_catalog_is_sent_once_without_unused_descriptions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $budget = AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        $category = BudgetCategory::create(['name' => 'Memory test']);
        $department = Department::create(['name' => 'Finance', 'code' => 'MEM']);
        for ($batch = 0; $batch < 15; $batch++) {
            $rows = [];
            for ($i = 0; $i < 100; $i++) {
                $id = $batch * 100 + $i;
                $rows[] = ['category_id' => $category->id, 'department_id' => $department->id,
                    'account_code' => 'MEM-'.$id, 'account_name' => 'Account '.$id,
                    'particular' => 'Account '.$id, 'description' => str_repeat('Unused description ', 200)];
            }
            DB::table('budget_particulars')->insert($rows);
        }
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(\Illuminate\Http\Request::create('/'));
        $response = $this->get('/annual-budgets/'.$budget->id, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version]);
        $response->assertOk()->assertJsonCount(1500, 'props.accountTitles')
            ->assertJsonMissingPath('props.particulars')
            ->assertJsonMissingPath('props.accountTitles.0.description')
            ->assertJsonMissingPath('props.accountTitles.0.category')
            ->assertJsonPath('props.accountTitles.0.department.name', 'Finance')
            ->assertJsonPath('props.accountTitles.1499.account_code', 'MEM-1499');
        $this->assertLessThan(800000, strlen($response->getContent()));
    }
}
