<?php

namespace Tests\Feature;

use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetParticularUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_title_edit_updates_the_existing_record(): void
    {
        [$user, $category, $department, $accountTitle] = $this->accountTitleFixture();

        $response = $this->actingAs($user)->put("/budget-particulars/{$accountTitle->id}", [
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '5-6016-A',
            'account_name' => 'Office Supplies and Materials',
            'particular' => 'Office Supplies Updated',
            'description' => 'Updated account title',
        ]);

        $response->assertRedirect(route('budget-particulars.index'));
        $response->assertSessionHas('success', 'Account Title updated successfully.');
        $this->assertDatabaseHas('budget_particulars', [
            'id' => $accountTitle->id,
            'account_code' => '5-6016-A',
            'account_name' => 'Office Supplies and Materials',
            'particular' => 'Office Supplies Updated',
            'description' => 'Updated account title',
        ]);
        $this->assertSame(1, BudgetParticular::count());
    }

    public function test_account_title_delete_removes_the_bound_record(): void
    {
        [$user, , , $accountTitle] = $this->accountTitleFixture();

        $response = $this->actingAs($user)->delete("/budget-particulars/{$accountTitle->id}");

        $response->assertRedirect(route('budget-particulars.index'));
        $this->assertDatabaseMissing('budget_particulars', ['id' => $accountTitle->id]);
    }

    public function test_invalid_account_title_edit_is_rejected_without_changing_the_record(): void
    {
        [$user, $category, $department, $accountTitle] = $this->accountTitleFixture();

        $response = $this->actingAs($user)->put("/budget-particulars/{$accountTitle->id}", [
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '',
            'account_name' => '',
            'particular' => '',
            'description' => 'Should not be saved',
        ]);

        $response->assertSessionHasErrors(['account_code', 'account_name', 'particular']);
        $this->assertDatabaseHas('budget_particulars', [
            'id' => $accountTitle->id,
            'account_code' => '5-6016',
            'account_name' => 'Office Supplies',
            'particular' => 'Office Supplies',
        ]);
    }

    private function accountTitleFixture(): array
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

        return [$user, $category, $department, $accountTitle];
    }
}
