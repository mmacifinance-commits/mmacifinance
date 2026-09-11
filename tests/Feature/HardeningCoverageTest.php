<?php

namespace Tests\Feature;

use App\Models\AnnualBudget;
use App\Models\AuditTrail;
use App\Models\BudgetCategory;
use App\Models\Department;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HardeningCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_preview_reports_missing_invalid_and_duplicate_rows(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $csv = implode("\n", [
            'receipt_no,receipt_type,source,description,amount,date_encoded',
            'OR-1,Enrollment,Collections,Valid receipt,100,2026-08-01',
            'OR-1,Enrollment,Collections,Duplicate receipt,100,2026-08-01',
            ',Enrollment,Collections,Missing receipt no,100,2026-08-01',
        ]);

        $response = $this->actingAs($user)->postJson('/imports/receipts/preview', [
            'csv_file' => UploadedFile::fake()->createWithContent('receipts.csv', $csv),
        ]);

        $response->assertOk()
            ->assertJsonPath('duplicate_count', 1)
            ->assertJsonPath('headers.0', 'receipt_no')
            ->assertJsonStructure(['valid_rows', 'invalid_rows', 'duplicates']);
    }

    public function test_full_system_backup_is_super_admin_only(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($cashier)->get('/system/backup/export')->assertRedirect('/');

        $this->actingAs($admin)->get('/system/backup/export')->assertOk();
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'exported',
            'remarks' => 'Full system backup exported.',
        ]);
    }

    public function test_closed_fiscal_period_blocks_budget_and_receipt_mutations(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $budget = AnnualBudget::create([
            'year' => 2026,
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'semester' => 'Fiscal Year',
            'closed_at' => now(),
            'closed_by_id' => $admin->id,
        ]);

        $category = BudgetCategory::create(['name' => 'AUXILIARY FUND']);
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN']);

        $this->actingAs($admin)->post("/annual-budgets/{$budget->id}/items", [
            'category_id' => $category->id,
            'department_id' => $department->id,
            'particular_id' => 999,
            'allocation_month' => '2026-08-01',
            'appropriation' => 100,
        ])->assertSessionHasErrors('allocation_month');

        $this->actingAs($admin)->post('/receipts', [
            'receipt_no' => 'OR-CLOSED-001',
            'receipt_type' => 'Enrollment',
            'source' => 'Collections',
            'description' => 'Closed period receipt',
            'amount' => 100,
            'date_encoded' => '2026-08-01',
        ])->assertSessionHasErrors('date_encoded');
    }

    public function test_imports_and_setup_edits_create_audit_trails(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->post('/budget-categories', [
            'name' => 'SPECIAL AUDIT FUND',
            'description' => 'Audit-only test category',
        ])->assertSessionHasNoErrors();

        $category = BudgetCategory::where('name', 'SPECIAL AUDIT FUND')->firstOrFail();
        $this->assertDatabaseHas('audit_trails', [
            'auditable_type' => BudgetCategory::class,
            'auditable_id' => $category->id,
            'action' => 'created',
        ]);

        $csv = implode("\n", [
            'source,description,amount,date_encoded,notes',
            'Collections,Imported income,500,2026-08-01,Imported',
        ]);

        $this->actingAs($admin)->post('/income/import-csv', [
            'csv_file' => UploadedFile::fake()->createWithContent('income.csv', $csv),
        ])->assertSessionHasNoErrors();

        $income = Income::where('description', 'Imported income')->firstOrFail();
        $this->assertDatabaseHas('audit_trails', [
            'auditable_type' => Income::class,
            'auditable_id' => $income->id,
            'action' => 'imported',
        ]);
    }

    public function test_role_permission_matrix_blocks_unauthorized_write_actions(): void
    {
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($auditor)->withHeader('X-Inertia', 'true')->post('/receipts', [])->assertForbidden();
        $this->actingAs($auditor)->withHeader('X-Inertia', 'true')->post('/expenses', [])->assertForbidden();
        $this->actingAs($cashier)->withHeader('X-Inertia', 'true')->post('/income', [])->assertForbidden();
        $this->actingAs($cashier)->withHeader('X-Inertia', 'true')->post('/budget-categories', [])->assertForbidden();
        $this->actingAs($cashier)->post('/imports/income/preview', [
            'csv_file' => UploadedFile::fake()->createWithContent('income.csv', 'source,description,amount,date_encoded,notes'),
        ])->assertForbidden();
    }
}
