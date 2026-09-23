<?php

namespace Tests\Feature;

use App\Models\{AnnualBudget, BudgetCategory, BudgetItem, BudgetParticular, Department, Disbursement, Expense, Income, User};
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class FinancialReportAccuracyTest extends TestCase
{
    use SafeRefreshDatabase;

    private function fixture(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $period = AnnualBudget::create(['year' => 2026, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        $category = BudgetCategory::create(['name' => 'Report Test']);
        $items = [];
        foreach (['Alpha', 'Beta'] as $name) {
            $department = Department::create(['name' => $name, 'code' => $name]);
            $title = BudgetParticular::create(['category_id' => $category->id, 'department_id' => $department->id, 'account_code' => $name, 'account_name' => $name, 'particular' => $name]);
            $items[] = BudgetItem::create(['budget_id' => $period->id, 'category_id' => $category->id, 'particular_id' => $title->id, 'allocation_month' => '2026-08-01', 'appropriation' => 1000]);
        }
        foreach ($items as $i => $item) {
            $expense = Expense::create(['ref_no' => 'EXP-'.$i, 'category_id' => $category->id, 'particular_id' => $item->particular_id,
                'budget_item_id' => $item->id, 'description' => 'Expense', 'amount' => 1000, 'date_encoded' => '2026-08-01', 'status' => 'posted']);
            foreach ([['posted', '2026-08-05', 100], ['posted', '2026-09-05', 200], ['draft', '2026-09-06', 30], ['rejected', '2026-09-07', 900]] as $j => [$status, $date, $amount]) {
                Disbursement::create(['disbursement_no' => "DSB-$i-$j", 'expense_id' => $expense->id, 'description' => 'Payment', 'source' => 'Expense',
                    'pay_to' => 'Supplier', 'amount' => $amount, 'method' => 'cash', 'date_encoded' => $date, 'status' => $status]);
            }
        }
        foreach ([['2026-08-01', 1000, 'OR-OPEN'], ['2026-09-01', 500, 'OR-CURRENT'], ['2026-09-02', 75, null], ['2026-10-01', 999, 'OR-LATER']] as $i => [$date, $amount, $receipt]) {
            Income::create(['income_no' => 'INC-'.$i, 'receipt_no' => $receipt, 'receipt_type' => $receipt ? 'Tuition' : null, 'source' => 'Collections',
                'description' => '=NOT_A_FORMULA()', 'amount' => $amount, 'date_encoded' => $date]);
        }
        return [$period, $items];
    }

    private function data(array $filters): array
    {
        $request = Request::create('/reports/generate', 'GET', $filters);
        $request->setUserResolver(fn () => auth()->user());
        return app(FinancialReportService::class)->build($request);
    }

    public function test_filters_and_cash_scope_reconcile_across_page_and_print(): void
    {
        [$period, $items] = $this->fixture();
        $filters = ['fiscal_period_id' => $period->id, 'allocation_month' => '2026-08-01', 'department_id' => $items[0]->particular->department_id,
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30'];
        $data = $this->data($filters);
        $this->assertSame(1000.0, $data['totals']['appropriation']);
        $this->assertSame(200.0, $data['totals']['expenditure']);
        $this->assertSame(800.0, $data['totals']['balance']);
        $this->assertSame(500.0, $data['totals']['receipts']);
        $this->assertArrayNotHasKey('openingCash', $data['totals']);
        $this->assertSame(100.0, $data['totals']['cashOnHand']);
        $this->assertSame($data['totals']['receipts'] - $data['totals']['institutionalPostedDisbursements'], $data['totals']['cashOnHand']);
        $this->assertSame(30.0, $data['totals']['pendingCommitments']);
        $this->assertCount(1, $data['disbursementRows']);
        $this->assertCount(1, $data['receiptRows']);
        foreach (['/reports', '/reports/generate'] as $path) {
            $this->get($path.'?'.http_build_query($filters))->assertOk()->assertInertia(fn ($page) => $page->where('sections', json_decode(json_encode($data['sections']), true)));
        }
    }

    public function test_income_comparison_includes_unreceipted_records_without_double_counting(): void
    {
        [$period] = $this->fixture();
        $report = $this->data(['report_type' => 'income_vs_receipts', 'fiscal_period_id' => $period->id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
        $this->assertCount(3, $report['sections']);
        $this->assertSame(75.0, $report['totals']['income']);
        $this->assertSame(500.0, $report['totals']['receipts']);
        $this->assertSame([['Projected Income', 75.0], ['Actual Receipts', 500.0], ['Difference (Projected Income less Receipts)', -425.0]], $report['sections'][0]['rows']);
        $this->assertCount(1, $report['sections'][1]['rows']);
        $this->assertCount(1, $report['sections'][2]['rows']);
    }

    public function test_ledger_carries_prior_payments_into_opening_and_running_budget_balance(): void
    {
        [$period, $items] = $this->fixture();
        $report = $this->data(['report_type' => 'account_title_ledger', 'fiscal_period_id' => $period->id,
            'account_title_id' => $items[0]->particular_id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
        $this->assertCount(1, $report['sections']);
        $this->assertSame(900.0, $report['sections'][0]['rows'][0][5]);
        $this->assertSame(700.0, $report['sections'][0]['rows'][1][5]);
        $this->assertCount(2, $report['sections'][0]['rows']);
    }

    public function test_all_nine_exports_contain_the_same_sections_and_numeric_totals_as_print(): void
    {
        [$period] = $this->fixture();
        foreach (array_keys(FinancialReportService::TYPES) as $type) {
            $filters = ['report_type' => $type, 'fiscal_period_id' => $period->id];
            $data = $this->data($filters);
            $response = $this->get('/reports/export?'.http_build_query($filters))->assertOk();
            $path = $response->baseResponse->getFile()->getPathname();
            try {
                $book = IOFactory::load($path); $sheet = $book->getActiveSheet();
                $this->assertSame('portrait', $sheet->getPageSetup()->getOrientation());
                $searchFrom = 1;
                foreach ($data['sections'] as $section) {
                    $titleRow = null;
                    for ($r = $searchFrom; $r <= $sheet->getHighestRow(); $r++) {
                        if ($sheet->getCell('A'.$r)->getValue() === $section['title']) { $titleRow = $r; break; }
                    }
                    $this->assertNotNull($titleRow, $type.' missing '.$section['title']);
                    $count = count($section['headers']); $widths = array_fill(0, $count, 1); $widths[$count > 3 ? 2 : 0] += 9 - $count;
                    $columns = []; $c = 1;
                    foreach ($widths as $width) { $columns[] = $c; $c += $width; }
                    foreach ($section['rows'] as $i => $values) {
                        foreach ($values as $column => $expected) {
                            $cell = $sheet->getCell([$columns[$column], $titleRow + 2 + $i]);
                            $actual = $cell->getCalculatedValue();
                            if ($section['percent'] === $column) $expected /= 100;
                            if (is_numeric($expected)) $this->assertEqualsWithDelta($expected, (float) $actual, 0.0001, $type);
                            else $this->assertEquals($expected ?? '', $actual ?? '', $type);
                        }
                    }
                    if ($section['totalLabel']) {
                        $totalRow = $titleRow + 2 + max(1, count($section['rows']));
                        foreach ($section['totals'] as $column => $expected) {
                            if ($section['percent'] === $column) $expected /= 100;
                            $this->assertEqualsWithDelta($expected, $sheet->getCell([$columns[$column], $totalRow])->getCalculatedValue(), 0.0001, $type.' total');
                        }
                    }
                    $searchFrom = $titleRow + 2 + max(1, count($section['rows']));
                }
                $book->disconnectWorksheets();
            } finally { unlink($path); }
        }
    }

    public function test_closing_report_discloses_provisional_status_and_rejects_partial_scope(): void
    {
        [$period] = $this->fixture();
        $report = $this->data(['report_type' => 'closing_report', 'fiscal_period_id' => $period->id]);
        $this->assertStringContainsString('PROVISIONAL', implode(' ', $report['reportNotes']));
        $this->get('/reports/generate?'.http_build_query(['report_type' => 'closing_report', 'fiscal_period_id' => $period->id, 'start_date' => '2026-09-01']))
            ->assertSessionHasErrors('report_type');
    }
}
