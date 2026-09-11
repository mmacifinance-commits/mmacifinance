<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EditableRecordsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_category_edit_saves_and_invalid_edit_is_rejected(): void
    {
        $user = $this->superAdmin();
        $category = BudgetCategory::where('name', 'AUXILIARY FUND')->firstOrFail();

        $this->actingAs($user)->put("/budget-categories/{$category->id}", [
            'name' => 'Auxiliary Operations',
            'description' => 'Updated description',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budget_categories', [
            'id' => $category->id,
            'name' => 'Auxiliary Operations',
            'description' => 'Updated description',
        ]);

        $this->actingAs($user)->put("/budget-categories/{$category->id}", [
            'name' => '',
            'description' => 'Invalid update',
        ])->assertSessionHasErrors('name');

        $this->assertSame('Auxiliary Operations', $category->fresh()->name);
    }

    public function test_responsibility_center_edit_saves_and_duplicate_code_is_rejected(): void
    {
        $user = $this->superAdmin();
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        Department::create(['name' => 'Human Resources', 'code' => 'HR']);

        $this->actingAs($user)->put("/departments/{$department->id}", [
            'name' => 'Finance Office',
            'code' => 'FO',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'Finance Office', 'code' => 'FO']);

        $this->actingAs($user)->put("/departments/{$department->id}", [
            'name' => 'Duplicate Code Attempt',
            'code' => 'HR',
        ])->assertSessionHasErrors('code');

        $this->assertSame('FO', $department->fresh()->code);
    }

    public function test_income_edit_saves_and_negative_amount_is_rejected(): void
    {
        $user = $this->superAdmin();
        $income = Income::create([
            'income_no' => 'INC-2026-0001',
            'source' => 'Original Source',
            'description' => 'Original income',
            'amount' => 50000,
            'date_encoded' => '2026-01-05',
            'created_by_id' => $user->id,
        ]);

        $payload = [
            'receipt_no' => 'OR-2026-0001',
            'source' => 'Updated Source',
            'description' => 'Updated income',
            'amount' => 60000,
            'date_encoded' => '2026-02-05',
            'notes' => 'Updated notes',
        ];
        $this->actingAs($user)->put("/income/{$income->id}", $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incomes', [
            'id' => $income->id,
            'receipt_no' => 'OR-2026-0001',
            'source' => 'Updated Source',
            'amount' => 60000,
        ]);

        $payload['amount'] = -1;
        $this->actingAs($user)->put("/income/{$income->id}", $payload)->assertSessionHasErrors('amount');
        $this->assertEquals(60000, $income->fresh()->amount);
    }

    public function test_income_receipt_number_is_searchable_and_preserved_in_csv(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->post('/income', [
            'receipt_no' => 'OR-SEARCH-001',
            'source' => 'Collections',
            'description' => 'Receipt income',
            'amount' => 7500,
            'date_encoded' => '2026-08-01',
            'notes' => 'With receipt',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get('/income?search=OR-SEARCH-001')
            ->assertInertia(fn ($page) => $page
                ->where('incomeRecords.data.0.receipt_no', 'OR-SEARCH-001'));

        $export = $this->actingAs($user)->get('/income/export-csv');
        $export->assertDownload();

        [$headers, $exportRows] = \App\Support\SpreadsheetImportExport::readRows(new UploadedFile(
            $export->baseResponse->getFile()->getPathname(),
            'income-export.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        ));

        $this->assertContains('receipt_no', $headers);
        $this->assertSame('OR-SEARCH-001', $exportRows[0][1]);

        $csv = implode("\n", [
            'income_no,receipt_no,source,description,amount,date_encoded,notes',
            'INC-IGNORED,OR-IMPORT-001,Imported Collections,Imported income,12000,2026-08-02,Imported with receipt',
            '',
        ]);

        $this->actingAs($user)->post('/income/import-csv', [
            'csv_file' => UploadedFile::fake()->createWithContent('income.csv', $csv),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('incomes', [
            'receipt_no' => 'OR-IMPORT-001',
            'source' => 'Imported Collections',
            'description' => 'Imported income',
            'amount' => 12000,
        ]);
    }

    public function test_expense_edit_saves_and_invalid_edit_is_rejected(): void
    {
        [$user, $expense, $allocation] = $this->financialFixture('pending');
        $payload = [
            'description' => 'Updated supplies expense',
            'category_id' => $allocation->category_id,
            'particular_id' => $allocation->particular_id,
            'budget_item_id' => $allocation->id,
            'amount' => 12000,
            'date_encoded' => '2026-08-20',
            'status' => 'pending',
            'notes' => 'Updated notes',
        ];

        $this->actingAs($user)->put("/expenses/{$expense->id}", $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'description' => 'Updated supplies expense', 'amount' => 12000]);

        $payload['description'] = '';
        $this->actingAs($user)->put("/expenses/{$expense->id}", $payload)->assertSessionHasErrors('description');
        $this->assertSame('Updated supplies expense', $expense->fresh()->description);
    }

    public function test_disbursement_edit_saves_and_invalid_method_is_rejected(): void
    {
        [$user, $expense] = $this->financialFixture('approved');
        $disbursement = Disbursement::create([
            'disbursement_no' => 'DSB00000001',
            'expense_id' => $expense->id,
            'description' => 'Original payment',
            'source' => 'Expenditure',
            'pay_to' => 'Original Supplier',
            'amount' => 5000,
            'method' => 'check',
            'date_encoded' => '2026-08-21',
            'status' => 'draft',
            'prepared_by_id' => $user->id,
        ]);
        $payload = [
            'expense_id' => $expense->id,
            'description' => 'Updated payment',
            'source' => 'Expenditure',
            'pay_to' => 'Updated Supplier',
            'amount' => 6000,
            'method' => 'bank_transfer',
            'date_encoded' => '2026-08-22',
            'status' => 'draft',
            'notes' => 'Updated notes',
            'remarks' => 'Updated remarks',
        ];

        $this->actingAs($user)->put("/disbursements/{$disbursement->id}", $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('disbursements', [
            'id' => $disbursement->id,
            'description' => 'Updated payment',
            'pay_to' => 'Updated Supplier',
            'method' => 'bank_transfer',
        ]);

        $payload['method'] = 'invalid';
        $this->actingAs($user)->put("/disbursements/{$disbursement->id}", $payload)->assertSessionHasErrors('method');
        $this->assertSame('bank_transfer', $disbursement->fresh()->method);
    }

    public function test_unauthorized_user_cannot_use_any_edit_endpoint(): void
    {
        [, $expense, $allocation] = $this->financialFixture('approved');
        $disbursement = Disbursement::create([
            'disbursement_no' => 'DSB00000001',
            'expense_id' => $expense->id,
            'description' => 'Payment',
            'source' => 'Expenditure',
            'pay_to' => 'Supplier',
            'amount' => 5000,
            'method' => 'check',
            'date_encoded' => '2026-08-21',
            'status' => 'draft',
        ]);
        $income = Income::create([
            'income_no' => 'INC-2026-0001',
            'source' => 'Collections',
            'description' => 'Income',
            'amount' => 50000,
            'date_encoded' => '2026-01-05',
        ]);
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $accountTitle = $allocation->particular;

        $endpoints = [
            "/annual-budgets/{$allocation->budget_id}/items/{$allocation->id}",
            "/budget-categories/{$allocation->category_id}",
            "/budget-particulars/{$allocation->particular_id}",
            "/departments/{$accountTitle->department_id}",
            "/expenses/{$expense->id}",
            "/disbursements/{$disbursement->id}",
            "/income/{$income->id}",
        ];

        foreach ($endpoints as $endpoint) {
            $this->actingAs($auditor)
                ->withHeader('X-Inertia', 'true')
                ->put($endpoint)
                ->assertForbidden();
        }
    }

    public function test_all_edit_endpoints_return_not_found_for_stale_record_ids(): void
    {
        $user = $this->superAdmin();
        $endpoints = [
            '/annual-budgets/999999/items/999999',
            '/budget-categories/999999',
            '/budget-particulars/999999',
            '/departments/999999',
            '/expenses/999999',
            '/disbursements/999999',
            '/income/999999',
        ];

        foreach ($endpoints as $endpoint) {
            $this->actingAs($user)->put($endpoint)->assertNotFound();
        }
    }

    private function financialFixture(string $expenseStatus): array
    {
        $user = $this->superAdmin();
        $category = BudgetCategory::where('name', 'AUXILIARY FUND')->firstOrFail();
        $department = Department::create(['name' => 'Finance Office', 'code' => 'FIN']);
        $accountTitle = BudgetParticular::create([
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '5-6016',
            'account_name' => 'Office Supplies',
            'particular' => 'Office Supplies',
        ]);
        $budget = AnnualBudget::create(['year' => 2026, 'semester' => 'Full Year (Jan-Dec)']);
        $allocation = BudgetItem::create([
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'particular_id' => $accountTitle->id,
            'month' => 8,
            'appropriation' => 30000,
        ]);
        $expense = Expense::create([
            'ref_no' => 'EXP00000001',
            'description' => 'Original supplies expense',
            'category_id' => $category->id,
            'particular_id' => $accountTitle->id,
            'budget_item_id' => $allocation->id,
            'amount' => 10000,
            'paid' => 0,
            'date_encoded' => '2026-08-15',
            'status' => $expenseStatus,
        ]);

        return [$user, $expense, $allocation];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }
}
