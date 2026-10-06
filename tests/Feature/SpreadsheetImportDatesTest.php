<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SpreadsheetImportExport;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class SpreadsheetImportDatesTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_excel_dates_work_for_every_date_column_and_supported_file_format(): void
    {
        foreach (['Xlsx', 'Xls', 'Csv'] as $format) {
            $book = new Spreadsheet;
            $book->getActiveSheet()->fromArray([
                ['DATE_ENCODED', 'date_approved', 'allocation_month', 'fiscal_start_date', 'fiscal_end_date', 'start_date', 'end_date', 'amount', 'account_code', 'month'],
                [46569, 46569.5, 46569, 46569, 46569, 46569, 46569, 46569, '46204', 7],
            ]);
            $book->getActiveSheet()->getStyle('A2')->getNumberFormat()->setFormatCode('m/d/yyyy');
            $path = tempnam(sys_get_temp_dir(), 'date_test_');
            try {
                IOFactory::createWriter($book, $format)->save($path);
                [, $rows] = SpreadsheetImportExport::readRows(new UploadedFile($path, 'dates.'.strtolower($format), null, null, true));
                $this->assertSame(array_fill(0, 7, '2027-07-01'), array_slice($rows[0], 0, 7), $format);
                $this->assertEquals([46569, 46204, 7], array_slice($rows[0], 7));
            } finally {
                unlink($path);
                $book->disconnectWorksheets();
            }
        }
    }

    public function test_text_dates_months_and_optional_blank_dates_are_supported(): void
    {
        [, $rows] = SpreadsheetImportExport::readRows(UploadedFile::fake()->createWithContent('dates.csv', "date_encoded,allocation_month,date_approved\n7/1/2027,2027-07,\n"));
        $this->assertSame([['2027-07-01', '2027-07', '']], $rows);
    }

    public function test_mac_calendar_and_formula_dates_are_supported(): void
    {
        $book = new Spreadsheet;
        $book->setExcelCalendar(1904);
        $book->getActiveSheet()->fromArray([['date_encoded', 'date_approved'], [45107, '=DATE(2027,7,1)']]);
        $path = tempnam(sys_get_temp_dir(), 'date_test_');
        try {
            IOFactory::createWriter($book, 'Xlsx')->save($path);
            [, $rows] = SpreadsheetImportExport::readRows(new UploadedFile($path, 'dates.xlsx', null, null, true));
            $this->assertSame([['2027-07-01', '2027-07-01']], $rows);
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_income_and_receipt_preview_and_save_accept_excel_serials(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        foreach (['income' => "source,description,amount,date_encoded,notes\nTUITION FEE,BLIS - TUITION FEE,114521.33,46569,PROJECTED INCOME\n", 'receipts' => "receipt_no,receipt_type,source,description,amount,date_encoded\nOR-DATE,Tuition,Fees,Collection,100,46569\n"] as $module => $csv) {
            $this->postJson('/imports/'.$module.'/preview', ['csv_file' => UploadedFile::fake()->createWithContent('data.csv', $csv)])
                ->assertOk()->assertJsonPath('valid_count', 1)->assertJsonPath('invalid_count', 0);
            $this->post('/'.$module.'/import-csv', ['csv_file' => UploadedFile::fake()->createWithContent('data.csv', $csv)])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('incomes', ['description' => 'BLIS - TUITION FEE', 'date_encoded' => '2027-07-01 00:00:00']);
        $this->assertDatabaseHas('incomes', ['receipt_no' => 'OR-DATE', 'date_encoded' => '2027-07-01 00:00:00']);
    }

    public function test_invalid_date_returns_row_error_before_any_income_is_saved(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        foreach (['not-a-date', '2027-02-30', '-1', '9999999'] as $date) {
            $csv = "source,description,amount,date_encoded,notes\nTuition,Valid,100,46569,\nTuition,Invalid,100,{$date},\n";
            $this->post('/income/import-csv', ['csv_file' => UploadedFile::fake()->createWithContent('data.csv', $csv)])
                ->assertSessionHasErrors(['csv_file' => 'Row 3 has an invalid date_encoded. Use YYYY-MM-DD or a valid Excel date.']);
            $this->postJson('/imports/income/preview', ['csv_file' => UploadedFile::fake()->createWithContent('data.csv', $csv)])
                ->assertOk()->assertJsonPath('invalid_count', 1)->assertJsonPath('valid_count', 1);
            $this->assertDatabaseCount('incomes', 0);
        }
    }
}
