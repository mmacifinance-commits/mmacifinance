<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCategoryNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_category_relationships_are_moved_to_auxiliary_fund(): void
    {
        $legacyCategory = BudgetCategory::create([
            'name' => 'Office Supplies',
            'description' => 'Legacy expense-type category',
        ]);
        $department = Department::create(['name' => 'Finance Office', 'code' => 'FIN']);
        $accountTitle = BudgetParticular::create([
            'category_id' => $legacyCategory->id,
            'department_id' => $department->id,
            'account_code' => '5-6016',
            'account_name' => 'Office Supplies',
            'particular' => 'Office Supplies',
        ]);
        $budget = AnnualBudget::create(['year' => 2026, 'semester' => 'Full Year (Jan-Dec)']);
        $allocation = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $legacyCategory->id,
            'particular_id' => $accountTitle->id,
            'month' => 8,
            'appropriation' => 30000,
        ]);
        $expense = Expense::create([
            'ref_no' => 'EXP00000001',
            'description' => 'Laptop',
            'category_id' => $legacyCategory->id,
            'particular_id' => $accountTitle->id,
            'budget_item_id' => $allocation->id,
            'amount' => 10000,
            'paid' => 10000,
            'date_encoded' => '2026-09-07',
            'status' => 'posted',
        ]);

        $migration = require database_path('migrations/2026_09_09_000001_normalize_budget_categories_to_fund_classifications.php');
        $migration->up();

        $auxiliaryId = BudgetCategory::where('name', 'AUXILIARY FUND')->value('id');

        $this->assertNotNull($auxiliaryId);
        $this->assertDatabaseMissing('budget_categories', ['id' => $legacyCategory->id]);
        $this->assertSame($auxiliaryId, $accountTitle->fresh()->category_id);
        $this->assertSame($auxiliaryId, $allocation->fresh()->category_id);
        $this->assertSame($auxiliaryId, $expense->fresh()->category_id);
        $this->assertSame('Office Supplies', $accountTitle->fresh()->account_name);
        $this->assertEqualsCanonicalizing([
            'AUXILIARY FUND',
            'TRUST FUND',
            'TUITION AND OTHER FEES',
            'LABORATORY FEES',
            'MISCELLANEOUS INCOME',
        ], BudgetCategory::pluck('name')->all());
    }
}
