<?php

namespace Tests\Feature;

use App\Models\Income;
use App\Models\User;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class ReferenceNumberSafetyTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_income_can_be_added_after_an_earlier_record_was_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $data = ['source' => 'Tuition', 'description' => 'Projected collections', 'amount' => 100, 'date_encoded' => '2026-10-01'];
        $first = Income::create($data + ['income_no' => 'INC-'.date('Y').'-0001']);
        Income::create($data + ['income_no' => 'INC-'.date('Y').'-0002']);
        $first->delete();
        $this->post('/income', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('incomes', 2);
    }

    public function test_identical_income_details_are_allowed_with_distinct_references(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $data = ['source' => 'ENERGY FEE', 'description' => 'Monthly projected income', 'amount' => 199.16, 'date_encoded' => '2026-11-01'];
        foreach (range(1, 3) as $attempt) {
            $this->post('/income', $data)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('incomes', 3);
        $this->assertCount(3, Income::pluck('income_no')->unique());
        $last = Income::latest('id')->first();
        $lastReference = $last->income_no;
        $last->delete();
        $this->post('/income', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotSame($lastReference, Income::latest('id')->first()->income_no);
    }

    public function test_receipts_and_imports_share_safe_income_numbering(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        Income::create(['income_no' => 'INC-'.date('Y').'-1975', 'source' => 'Existing', 'description' => 'Existing income', 'amount' => 10, 'date_encoded' => '2026-10-01']);
        $this->post('/receipts', ['receipt_no' => 'OR-SAFE', 'receipt_type' => 'Tuition', 'source' => 'Fees', 'description' => 'Collection', 'amount' => 10, 'date_encoded' => '2026-10-01'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/income/import-csv', ['csv_file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('income.csv', "source,description,amount,date_encoded,notes\nTuition,Forecast,100,2026-10-01,\n")])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incomes', ['income_no' => 'INC-'.date('Y').'-1976', 'receipt_no' => 'OR-SAFE']);
        $this->assertDatabaseHas('incomes', ['income_no' => 'INC-'.date('Y').'-1977', 'description' => 'Forecast']);
    }

    public function test_all_reference_types_reserve_distinct_numbers_before_rows_are_saved(): void
    {
        $service = app(\App\Services\ReferenceNumberService::class);
        foreach ([['incomes', 'income_no', 'INC-2026-', 4], ['expenses', 'ref_no', 'EXP', 8], ['disbursements', 'disbursement_no', 'DSB', 8], ['annual_budgets', 'ref_no', 'AB-2026-', 4], ['budget_items', 'ref_no', 'MB-2026-10-', 4]] as [$table, $column, $prefix, $width]) {
            $first = $service->next($table, $column, $prefix, $width);
            $second = $service->next($table, $column, $prefix, $width);
            $this->assertNotSame($first, $second);
        }
    }
}
