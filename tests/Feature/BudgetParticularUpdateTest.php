<?php

namespace Tests\Feature;

use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    public function test_account_title_import_rejects_header_only_csv(): void
    {
        [$user] = $this->accountTitleFixture();

        $csv = "budget_category,responsibility_center,account_code,account_name,account_title,description\n";

        $response = $this->actingAs($user)->post('/budget-particulars/import-csv', [
            'csv_file' => UploadedFile::fake()->createWithContent('account-titles.csv', $csv),
        ]);

        $response->assertSessionHasErrors([
            'csv_file' => 'The CSV/Excel contains only the header row. Add at least one account title row before importing.',
        ]);
    }

    public function test_account_title_import_creates_valid_rows(): void
    {
        [$user, $category, $department] = $this->accountTitleFixture();

        $csv = implode("\n", [
            'budget_category,responsibility_center,account_code,account_name,account_title,description',
            "{$category->name},{$department->code},5-7001,Testing Supplies,Testing Supplies,Imported from test",
            '',
        ]);

        $response = $this->actingAs($user)->post('/budget-particulars/import-csv', [
            'csv_file' => UploadedFile::fake()->createWithContent('account-titles.csv', $csv),
        ]);

        $response->assertRedirect(route('budget-particulars.index'));
        $response->assertSessionHas('success', 'Account titles imported successfully. Created: 1, Updated: 0.');
        $this->assertDatabaseHas('budget_particulars', [
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '5-7001',
            'account_name' => 'Testing Supplies',
            'particular' => 'Testing Supplies',
            'description' => 'Imported from test',
        ]);
    }

    public function test_account_title_import_supports_excel_files(): void
    {
        [$user, $category, $department] = $this->accountTitleFixture();

        $upload = $this->fakeXlsxUpload('account-titles.xlsx', [
            ['budget_category', 'responsibility_center', 'account_code', 'account_name', 'account_title', 'description'],
            [$category->name, $department->code, '1-1001', 'Petty Cash Fund', 'Petty Cash Fund', 'Imported from Excel'],
        ]);

        $response = $this->actingAs($user)->post('/budget-particulars/import-csv', [
            'csv_file' => $upload,
        ]);

        $response->assertRedirect(route('budget-particulars.index'));
        $response->assertSessionHas('success', 'Account titles imported successfully. Created: 1, Updated: 0.');
        $this->assertDatabaseHas('budget_particulars', [
            'category_id' => $category->id,
            'department_id' => $department->id,
            'account_code' => '1-1001',
            'account_name' => 'Petty Cash Fund',
            'particular' => 'Petty Cash Fund',
            'description' => 'Imported from Excel',
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

    private function fakeXlsxUpload(string $filename, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        $worksheet->fromArray($rows);

        $path = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        if ($path === false) {
            $this->fail('Unable to create temporary spreadsheet file.');
        }

        @unlink($path);
        $path .= '.xlsx';

        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
