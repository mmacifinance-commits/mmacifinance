<?php

namespace Database\Seeders;

use App\Models\AnnualBudget;
use App\Models\AuditTrail;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetParticular;
use App\Models\Department;
use App\Models\IncomeAllocation;
use App\Models\Income;
use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- 1. Users & System Roles ---
        $headOfFinance = User::updateOrCreate(
            ['email' => 'admin@mmac.edu.ph'],
            [
                'name' => 'Dr. Maria Santos',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
            ]
        );

        $cashier = User::updateOrCreate(
            ['email' => 'cashier@mmac.edu.ph'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => Hash::make('password'),
                'role' => 'cashier',
            ]
        );

        $disbursementOfficer = User::updateOrCreate(
            ['email' => 'disbursement@mmac.edu.ph'],
            [
                'name' => 'Ana Reyes',
                'password' => Hash::make('password'),
                'role' => 'disbursement_officer',
            ]
        );

        $budgetOfficer = User::updateOrCreate(
            ['email' => 'budget@mmac.edu.ph'],
            [
                'name' => 'Carlos Gomez',
                'password' => Hash::make('password'),
                'role' => 'budget_officer',
            ]
        );

        $auditor = User::updateOrCreate(
            ['email' => 'auditor@mmac.edu.ph'],
            [
                'name' => 'Elena Torres',
                'password' => Hash::make('password'),
                'role' => 'auditor',
            ]
        );

        // --- 2. Departments ---
        $departments = [];
        $deptData = [
            ['name' => 'College - Marine Transportation', 'code' => 'MT'],
            ['name' => 'College - Marine Engineering', 'code' => 'ME'],
            ['name' => 'College - Information System', 'code' => 'IS'],
            ['name' => 'Senior High School', 'code' => 'SHS'],
            ['name' => 'Basic Education', 'code' => 'BASED'],
            ['name' => 'Administration', 'code' => 'ADMIN'],
        ];
        foreach ($deptData as $dept) {
            $departments[$dept['code']] = Department::updateOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name']]
            );
        }

        // --- 3. Budget Categories ---
        $categories = [];
        $catData = [
            ['name' => 'AUXILIARY FUND', 'description' => 'Income Generating Projects and Other Income'],
            ['name' => 'TRUST FUND', 'description' => 'Trust Liabilities'],
            ['name' => 'TUITION AND OTHER FEES', 'description' => 'Tuition Fee, Miscellaneous, and Laboratory Fees'],
            ['name' => 'LABORATORY FEES', 'description' => 'College Laboratory and Driving Fees'],
            ['name' => 'MISCELLANEOUS INCOME', 'description' => 'Energy Fee, Testing Fee, Registration, and Other Fees'],
        ];
        foreach ($catData as $i => $cat) {
            $categories[$i + 1] = BudgetCategory::updateOrCreate(
                ['name' => $cat['name']],
                ['description' => $cat['description']]
            );
        }

        // --- 4. Budget Particulars (Account Titles) ---
        $particulars = [];
        $particularData = [
            ['cat' => 1, 'dept' => 'MT', 'account_code' => '5-1212', 'account_name' => 'Insurance Expense - Students - Marine Transportation', 'particular' => 'Insurance Expense', 'description' => 'Student insurance coverage'],
            ['cat' => 1, 'dept' => 'MT', 'account_code' => '5-1211', 'account_name' => 'Graduation Expense - Marine Transportation', 'particular' => 'Graduation Expense', 'description' => 'Graduation ceremony costs'],
            ['cat' => 1, 'dept' => 'IS', 'account_code' => '4-1005', 'account_name' => 'College - Information System', 'particular' => 'IS Operations', 'description' => 'Information System operations'],
            ['cat' => 2, 'dept' => 'ME', 'account_code' => '5-1114', 'account_name' => 'Medical and Dental - Marine Engineering', 'particular' => 'Medical-Dental Fund', 'description' => 'Medical and dental services'],
            ['cat' => 2, 'dept' => 'IS', 'account_code' => '4-1005-T', 'account_name' => 'College - Information System Trust', 'particular' => 'Trust IT Fund', 'description' => 'IT trust fund allocation'],
            ['cat' => 3, 'dept' => 'IS', 'account_code' => '4-1005-A', 'account_name' => 'College - Information System Tuition', 'particular' => 'IT Tuition Allocation', 'description' => 'IT allocation from tuition'],
            ['cat' => 3, 'dept' => 'ME', 'account_code' => '4-1001', 'account_name' => 'College - Marine Engineering', 'particular' => 'Marine Engineering Fund', 'description' => 'Marine engineering program fees'],
            ['cat' => 4, 'dept' => 'IS', 'account_code' => '4-1070', 'account_name' => 'College Lab Fee', 'particular' => 'Laboratory Supplies', 'description' => 'Lab supplies and equipment'],
            ['cat' => 4, 'dept' => 'MT', 'account_code' => '4-1071', 'account_name' => 'Driving Fee', 'particular' => 'Driving Course', 'description' => 'Driving course fees'],
            ['cat' => 5, 'dept' => 'ADMIN', 'account_code' => '4-3017', 'account_name' => 'Energy Fee', 'particular' => 'Energy Fee', 'description' => 'Utility and energy expenses'],
            ['cat' => 5, 'dept' => 'ADMIN', 'account_code' => '4-3019', 'account_name' => 'Testing Fee', 'particular' => 'Testing Fee', 'description' => 'Assessment and testing costs'],
            ['cat' => 5, 'dept' => 'ADMIN', 'account_code' => '4-3024', 'account_name' => 'Registration Fee', 'particular' => 'Registration Fee', 'description' => 'Student registration processing'],
        ];

        foreach ($particularData as $i => $p) {
            $particulars[$i + 1] = BudgetParticular::updateOrCreate(
                ['account_code' => $p['account_code']],
                [
                    'category_id' => $categories[$p['cat']]->id,
                    'department_id' => $departments[$p['dept']]->id,
                    'account_name' => $p['account_name'],
                    'particular' => $p['particular'],
                    'description' => $p['description'],
                ]
            );
        }

        // --- Fresh FY 2026 sample reset ---
        AnnualBudget::where('year', 2026)->delete();
        Disbursement::whereYear('date_encoded', 2026)->delete();
        Expense::whereYear('date_encoded', 2026)->delete();
        Income::whereYear('date_encoded', 2026)->delete();

        // --- 4.5 Sample Income Records (2024 to 2026) ---
        $incomeData = [
            ['income_no' => 'INC-2024-1001', 'source' => 'Tuition Collections', 'description' => 'FY 2024 tuition collections for operations', 'amount' => 12000000.00, 'date_encoded' => '2024-01-10', 'notes' => 'Fresh sample income for FY 2024'],
            ['income_no' => 'INC-2024-1002', 'source' => 'Laboratory Fees', 'description' => 'FY 2024 laboratory fee collections', 'amount' => 9000000.00, 'date_encoded' => '2024-04-12', 'notes' => 'Fresh sample income for FY 2024'],
            ['income_no' => 'INC-2024-1003', 'source' => 'Trust Fund', 'description' => 'FY 2024 trust fund support', 'amount' => 7500000.00, 'date_encoded' => '2024-07-18', 'notes' => 'Fresh sample income for FY 2024'],
            ['income_no' => 'INC-2024-1004', 'source' => 'Miscellaneous Income', 'description' => 'FY 2024 miscellaneous and registration collections', 'amount' => 7500000.00, 'date_encoded' => '2024-10-22', 'notes' => 'Fresh sample income for FY 2024'],
            ['income_no' => 'INC-2024-1005', 'source' => 'Auxiliary Operations', 'description' => 'FY 2024 auxiliary operating support', 'amount' => 5000000.00, 'date_encoded' => '2024-11-15', 'notes' => 'Fresh sample income for FY 2024'],
            ['income_no' => 'INC-2025-0101', 'source' => 'Tuition Collections', 'description' => 'Updated tuition remittances for FY 2025', 'amount' => 21750000.00, 'date_encoded' => '2025-02-14', 'notes' => 'Fresh sample income for FY 2025'],
            ['income_no' => 'INC-2025-0102', 'source' => 'Laboratory Fees', 'description' => 'Updated laboratory fee remittances for FY 2025', 'amount' => 8940000.00, 'date_encoded' => '2025-05-19', 'notes' => 'Fresh sample income for FY 2025'],
            ['income_no' => 'INC-2025-0103', 'source' => 'Trust Fund', 'description' => 'Updated trust fund support for FY 2025', 'amount' => 12200000.00, 'date_encoded' => '2025-08-09', 'notes' => 'Fresh sample income for FY 2025'],
            ['income_no' => 'INC-2025-0104', 'source' => 'Miscellaneous Income', 'description' => 'Registration and testing collections for FY 2025', 'amount' => 6480000.00, 'date_encoded' => '2025-11-22', 'notes' => 'Fresh sample income for FY 2025'],
            ['income_no' => 'INC-2026-0101', 'source' => 'Tuition Collections', 'description' => 'FY 2026 tuition collections for core operations', 'amount' => 18500000.00, 'date_encoded' => '2026-02-12', 'notes' => 'Fresh sample income for FY 2026'],
            ['income_no' => 'INC-2026-0102', 'source' => 'Laboratory Fees', 'description' => 'FY 2026 laboratory fee collections', 'amount' => 9200000.00, 'date_encoded' => '2026-05-18', 'notes' => 'Fresh sample income for FY 2026'],
            ['income_no' => 'INC-2026-0103', 'source' => 'Trust Fund', 'description' => 'FY 2026 trust fund support', 'amount' => 12300000.00, 'date_encoded' => '2026-08-08', 'notes' => 'Fresh sample income for FY 2026'],
            ['income_no' => 'INC-2026-0104', 'source' => 'Miscellaneous Income', 'description' => 'FY 2026 miscellaneous and registration collections', 'amount' => 4800000.00, 'date_encoded' => '2026-10-21', 'notes' => 'Fresh sample income for FY 2026'],
            ['income_no' => 'INC-2026-0105', 'source' => 'Auxiliary Operations', 'description' => 'FY 2026 auxiliary operating support', 'amount' => 3200000.00, 'date_encoded' => '2026-12-05', 'notes' => 'Fresh sample income for FY 2026'],
        ];

        foreach ($incomeData as $incomeRow) {
            Income::updateOrCreate(
                ['income_no' => $incomeRow['income_no']],
                $incomeRow + ['created_by_id' => $headOfFinance->id]
            );
        }

        $syncIncomeAllocations = function (AnnualBudget $annualBudget) {
            IncomeAllocation::where('annual_budget_id', $annualBudget->id)->delete();

            $incomes = Income::query()
                ->whereYear('date_encoded', $annualBudget->year)
                ->orderBy('date_encoded')
                ->orderBy('id')
                ->get();

            $items = BudgetItem::query()
                ->where('budget_id', $annualBudget->id)
                ->orderBy('month')
                ->orderBy('id')
                ->get();

            $incomeBalances = [];
            foreach ($incomes as $income) {
                $incomeBalances[$income->id] = round((float) $income->amount, 2);
            }

            foreach ($items as $item) {
                $remaining = round((float) $item->appropriation, 2);

                foreach ($incomes as $income) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $available = round($incomeBalances[$income->id] ?? 0, 2);
                    if ($available <= 0) {
                        continue;
                    }

                    $allocated = min($available, $remaining);
                    IncomeAllocation::create([
                        'income_id' => $income->id,
                        'annual_budget_id' => $annualBudget->id,
                        'budget_item_id' => $item->id,
                        'amount' => $allocated,
                    ]);

                    $incomeBalances[$income->id] = round($available - $allocated, 2);
                    $remaining = round($remaining - $allocated, 2);
                }
            }
        };

        // --- 5. Complete Monthly Seeding (Jan to Dec for 2024, 2025, 2026) ---
        $yearsToSeed = [2024, 2025, 2026];

        $fy2024MonthlyData = [
            1 => ['cat' => 1, 'par' => 'Insurance Expense', 'dept' => 'MT', 'appropriation' => 2100000.00, 'expense' => 2100000.00],
            2 => ['cat' => 1, 'par' => 'Graduation Expense', 'dept' => 'MT', 'appropriation' => 2150000.00, 'expense' => 2150000.00],
            3 => ['cat' => 1, 'par' => 'IS Operations', 'dept' => 'IS', 'appropriation' => 2200000.00, 'expense' => 2200000.00],
            4 => ['cat' => 2, 'par' => 'Medical-Dental Fund', 'dept' => 'ME', 'appropriation' => 2250000.00, 'expense' => 2250000.00],
            5 => ['cat' => 2, 'par' => 'Trust IT Fund', 'dept' => 'IS', 'appropriation' => 2300000.00, 'expense' => 2300000.00],
            6 => ['cat' => 3, 'par' => 'IT Tuition Allocation', 'dept' => 'IS', 'appropriation' => 2350000.00, 'expense' => 2350000.00],
            7 => ['cat' => 3, 'par' => 'Marine Engineering Fund', 'dept' => 'ME', 'appropriation' => 2400000.00, 'expense' => 2400000.00],
            8 => ['cat' => 4, 'par' => 'Laboratory Supplies', 'dept' => 'IS', 'appropriation' => 2450000.00, 'expense' => 2450000.00],
            9 => ['cat' => 4, 'par' => 'Driving Course', 'dept' => 'MT', 'appropriation' => 2500000.00, 'expense' => 2500000.00],
            10 => ['cat' => 5, 'par' => 'Energy Fee', 'dept' => 'ADMIN', 'appropriation' => 2550000.00, 'expense' => 2550000.00],
            11 => ['cat' => 5, 'par' => 'Testing Fee', 'dept' => 'ADMIN', 'appropriation' => 2650000.00, 'expense' => 2650000.00],
            12 => ['cat' => 5, 'par' => 'Registration Fee', 'dept' => 'ADMIN', 'appropriation' => 3850000.00, 'expense' => 3850000.00],
        ];

        // Base monthly allocation curve for 12 months (Jan through Dec)
        $monthlyBaseAllocations = [
            1 => [180000, 185000, 210000, 195000, 205000, 215000, 200000, 210000, 220000, 230000, 240000, 260000],
            2 => [100000, 110000, 150000, 160000, 360000, 420000, 120000, 130000, 140000, 150000, 160000, 210000],
            3 => [150000, 155000, 160000, 165000, 170000, 175000, 180000, 185000, 190000, 195000, 200000, 215000],
            4 => [90000,  95000,  100000, 105000, 110000, 115000, 120000, 125000, 130000, 135000, 140000, 155000],
            5 => [70000,  75000,  80000,  85000,  90000,  95000,  100000, 105000, 110000, 115000, 120000, 135000],
            6 => [300000, 310000, 320000, 330000, 340000, 350000, 360000, 370000, 380000, 390000, 400000, 430000],
            7 => [450000, 460000, 470000, 480000, 490000, 500000, 510000, 520000, 530000, 540000, 550000, 590000],
            8 => [140000, 145000, 150000, 155000, 160000, 165000, 170000, 175000, 180000, 185000, 190000, 205000],
            9 => [100000, 105000, 110000, 115000, 120000, 125000, 130000, 135000, 140000, 145000, 150000, 165000],
            10 => [250000, 260000, 270000, 280000, 290000, 300000, 310000, 320000, 330000, 340000, 350000, 390000],
            11 => [80000,  85000,  90000,  95000,  100000, 105000, 110000, 115000, 120000, 125000, 130000, 145000],
            12 => [120000, 125000, 130000, 135000, 140000, 145000, 150000, 155000, 160000, 165000, 170000, 185000],
        ];

        $mbItemCounter = 1;
        $expCounter = 1;
        $dsbCounter = 1;

        foreach ($yearsToSeed as $year) {
            $annualBudget = AnnualBudget::updateOrCreate(
                ['year' => $year],
                [
                    'ref_no' => $year === 2024
                        ? 'AB-2024-0001'
                        : sprintf('AB-%d-%04d', $year, $year - 2020),
                    'semester' => 'Full Year (Jan-Dec)',
                ]
            );

            if ($year === 2024) {
                foreach ($fy2024MonthlyData as $month => $line) {
                    $particularModel = BudgetParticular::where('particular', $line['par'])->first();

                    BudgetItem::updateOrCreate(
                        [
                            'budget_id' => $annualBudget->id,
                            'particular_id' => $particularModel->id,
                            'month' => $month,
                        ],
                        [
                            'ref_no' => sprintf('MB-2024-%02d-0001', $month),
                            'category_id' => $categories[$line['cat']]->id,
                            'appropriation' => $line['appropriation'],
                            'expenditure' => 0,
                        ]
                    );

                    $dateStr = sprintf('2024-%02d-15', $month);
                    $expense = Expense::updateOrCreate(
                        ['ref_no' => sprintf('EXP240%02d0001', $month)],
                        [
                            'description' => sprintf('%s - %s', $line['par'], date('F Y', strtotime($dateStr))),
                            'category_id' => $categories[$line['cat']]->id,
                            'particular_id' => $particularModel->id,
                            'amount' => $line['expense'],
                            'paid' => $line['expense'],
                            'date_encoded' => $dateStr,
                            'date_approved' => $dateStr,
                            'status' => 'posted',
                            'notes' => sprintf('Official monthly expenditure for %s %d', date('F', strtotime($dateStr)), $year),
                        ]
                    );

                    $dsb = Disbursement::updateOrCreate(
                        ['disbursement_no' => sprintf('DSB240%02d0001', $month)],
                        [
                            'expense_id' => $expense->id,
                            'description' => sprintf('Payment for %s (%s)', $line['par'], date('M Y', strtotime($dateStr))),
                            'source' => $categories[$line['cat']]->name,
                            'pay_to' => 'MMAC Accredited Vendor / Service Provider',
                            'amount' => $line['expense'],
                            'method' => ($month % 2 === 0) ? 'check' : 'bank_transfer',
                            'date_encoded' => $dateStr,
                            'date_approved' => $dateStr,
                            'status' => 'posted',
                            'remarks' => 'Posted & verified by Head of Finance.',
                            'prepared_by_id' => $cashier->id,
                            'released_by_id' => $cashier->id,
                            'submitted_by_id' => $cashier->id,
                            'approved_by_id' => $headOfFinance->id,
                            'posted_by_id' => $headOfFinance->id,
                        ]
                    );

                    AuditTrail::log($expense, 'posted', $headOfFinance, $expense->notes);
                    AuditTrail::log($dsb, 'posted', $headOfFinance, $dsb->remarks);
                }

                $syncIncomeAllocations($annualBudget);
                continue;
            }

            // Loop through ALL 12 MONTHS (January to December)
            for ($month = 1; $month <= 12; $month++) {
                $monthAppropriations = [];

                // 5a. Seed Monthly Budget Allocations for every account title for this month
                foreach ($particularData as $parIdx => $p) {
                    $parId = $particulars[$parIdx + 1]->id;
                    $catId = $categories[$p['cat']]->id;

                    $baseAppr = $monthlyBaseAllocations[$parIdx + 1][$month - 1] ?? 200000;
                    $yearGrowth = 1 + (($year - 2024) * 0.10); // 10% growth per year
                    if ($year === 2025) {
                        $yearGrowth = 1.14; // Fresh FY 2025 sample set
                    }
                    $monthlyAppr = round($baseAppr * $yearGrowth, 2);

                    $itemRefNo = sprintf('MB-%d-%02d-%04d', $year, $month, $mbItemCounter++);

                    BudgetItem::updateOrCreate(
                        [
                            'budget_id' => $annualBudget->id,
                            'particular_id' => $parId,
                            'month' => $month,
                        ],
                        [
                            'ref_no' => $itemRefNo,
                            'category_id' => $catId,
                            'appropriation' => $monthlyAppr,
                            'expenditure' => 0,
                        ]
                    );

                    $monthAppropriations[$parIdx + 1] = $monthlyAppr;
                }

                // 5b. Seed Expenses & Posted Disbursements for this month
                if ($year === 2026) {
                    foreach ($particularData as $parIdx => $p) {
                        $parKey = $parIdx + 1;
                        $expRefNo = sprintf('EXP%d%02d%04d', $year - 2000, $month, $expCounter++);
                        $dsbNo = sprintf('DSB%d%02d%04d', $year - 2000, $month, $dsbCounter++);
                        $amount = round(($monthAppropriations[$parKey] ?? 0) * 0.75, 2);
                        $dateStr = sprintf('%d-%02d-%02d', $year, $month, min(28, 5 + ($parKey * 2)));

                        $expense = Expense::updateOrCreate(
                            ['ref_no' => $expRefNo],
                            [
                                'description' => sprintf('%s - %s', $p['particular'], date('F Y', strtotime($dateStr))),
                                'category_id' => $categories[$p['cat']]->id,
                                'particular_id' => $particulars[$parKey]->id,
                                'amount' => $amount,
                                'paid' => $amount,
                                'date_encoded' => $dateStr,
                                'date_approved' => $dateStr,
                                'status' => 'posted',
                                'notes' => sprintf('Official monthly expenditure for %s %d', date('F', strtotime($dateStr)), $year),
                            ]
                        );

                        $dsb = Disbursement::updateOrCreate(
                            ['disbursement_no' => $dsbNo],
                            [
                                'expense_id' => $expense->id,
                                'description' => sprintf('Payment for %s (%s)', $p['particular'], date('M Y', strtotime($dateStr))),
                                'source' => $categories[$p['cat']]->name,
                                'pay_to' => 'MMAC Accredited Vendor / Service Provider',
                                'amount' => $amount,
                                'method' => ($month % 2 === 0) ? 'check' : 'bank_transfer',
                                'date_encoded' => $dateStr,
                                'date_approved' => $dateStr,
                                'status' => 'posted',
                                'remarks' => 'Posted & verified by Head of Finance.',
                                'prepared_by_id' => $cashier->id,
                                'released_by_id' => $cashier->id,
                                'submitted_by_id' => $cashier->id,
                                'approved_by_id' => $headOfFinance->id,
                                'posted_by_id' => $headOfFinance->id,
                            ]
                        );

                        AuditTrail::log($expense, 'posted', $headOfFinance, $expense->notes);
                        AuditTrail::log($dsb, 'posted', $headOfFinance, $dsb->remarks);
                    }
                } elseif ($year <= 2025) {
                    // Pick 2 account titles to record monthly expenditures for
                    $parIndices = [($month % 12) + 1, (($month + 4) % 12) + 1];
                    foreach ($parIndices as $parKey) {
                        $p = $particularData[$parKey - 1];
                        $expRefNo = sprintf('EXP%d%02d%04d', $year - 2000, $month, $expCounter++);
                        $dsbNo = sprintf('DSB%d%02d%04d', $year - 2000, $month, $dsbCounter++);

                        $amountBase = round($monthlyBaseAllocations[$parKey][$month - 1] ?? 200000, 2);
                        $amount = round($amountBase * 0.75, 2);
                        $dateStr = sprintf('%d-%02d-%02d', $year, $month, min(28, 5 + ($parKey * 2)));

                        $isPosted = $month <= 5;
                        $status = $isPosted ? 'posted' : 'for_approval';

                        $expense = Expense::updateOrCreate(
                            ['ref_no' => $expRefNo],
                            [
                                'description' => sprintf('%s - %s', $p['particular'], date('F Y', strtotime($dateStr))),
                                'category_id' => $categories[$p['cat']]->id,
                                'particular_id' => $particulars[$parKey]->id,
                                'amount' => $amount,
                                'paid' => $isPosted ? $amount : 0,
                                'date_encoded' => $dateStr,
                                'date_approved' => $isPosted ? $dateStr : null,
                                'status' => $isPosted ? 'posted' : 'pending',
                                'notes' => sprintf('Official monthly expenditure for %s %d', date('F', strtotime($dateStr)), $year),
                            ]
                        );

                        $dsb = Disbursement::updateOrCreate(
                            ['disbursement_no' => $dsbNo],
                            [
                                'expense_id' => $expense->id,
                                'description' => sprintf('Payment for %s (%s)', $p['particular'], date('M Y', strtotime($dateStr))),
                                'source' => $categories[$p['cat']]->name,
                                'pay_to' => 'MMAC Accredited Vendor / Service Provider',
                                'amount' => $amount,
                                'method' => ($month % 2 === 0) ? 'check' : 'bank_transfer',
                                'date_encoded' => $dateStr,
                                'date_approved' => $isPosted ? $dateStr : null,
                                'status' => $status,
                                'remarks' => $isPosted ? 'Posted & verified by Head of Finance.' : 'Submitted by Cashier for approval.',
                                'prepared_by_id' => $cashier->id,
                                'released_by_id' => $cashier->id,
                                'submitted_by_id' => $cashier->id,
                                'approved_by_id' => $isPosted ? $headOfFinance->id : null,
                                'posted_by_id' => $isPosted ? $headOfFinance->id : null,
                            ]
                        );

                        AuditTrail::log(
                            $dsb,
                            $isPosted ? 'posted' : 'submitted',
                            $isPosted ? $headOfFinance : $cashier,
                            $dsb->remarks
                        );
                    }
                }
            }

            $syncIncomeAllocations($annualBudget);
        }
    }
}
