<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\BudgetCategory;
use App\Models\BudgetParticular;
use App\Models\AnnualBudget;
use App\Models\BudgetItem;
use App\Models\Expense;
use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Users with Roles ---
        User::updateOrCreate(
            ['email' => 'admin@mmac.edu.ph'],
            [
                'name' => 'Head of Finance',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
            ]
        );
        User::updateOrCreate(
            ['email' => 'disbursement@mmac.edu.ph'],
            [
                'name' => 'Disbursement Officer',
                'password' => Hash::make('password'),
                'role' => 'disbursement_officer',
            ]
        );
        User::updateOrCreate(
            ['email' => 'budget@mmac.edu.ph'],
            [
                'name' => 'Budget Officer',
                'password' => Hash::make('password'),
                'role' => 'budget_officer',
            ]
        );
        User::updateOrCreate(
            ['email' => 'auditor@mmac.edu.ph'],
            [
                'name' => 'Auditor',
                'password' => Hash::make('password'),
                'role' => 'auditor',
            ]
        );

        // --- Departments ---
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

        // --- Budget Categories ---
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

        // --- Budget Particulars ---
        $particulars = [];
        $particularData = [
            ['cat' => 1, 'dept' => 'MT', 'account_code' => '5-1212', 'account_name' => 'Insurance Expense - Students - Marine Transportation', 'particular' => 'Insurance Expense', 'description' => 'Student insurance coverage'],
            ['cat' => 1, 'dept' => 'MT', 'account_code' => '5-1211', 'account_name' => 'Graduation Expense - Marine Transportation', 'particular' => 'Graduation Expense', 'description' => 'Graduation ceremony costs'],
            ['cat' => 1, 'dept' => 'IS', 'account_code' => '4-1005', 'account_name' => 'College - Information System', 'particular' => 'IS Operations', 'description' => 'Information System operations'],
            ['cat' => 2, 'dept' => 'ME', 'account_code' => '5-1114', 'account_name' => 'Medical and Dental - Marine Engineering', 'particular' => 'Medical-Dental Fund', 'description' => 'Medical and dental services'],
            ['cat' => 2, 'dept' => 'IS', 'account_code' => '4-1005', 'account_name' => 'College - Information System', 'particular' => 'Trust IT Fund', 'description' => 'IT trust fund allocation'],
            ['cat' => 3, 'dept' => 'IS', 'account_code' => '4-1005', 'account_name' => 'College - Information System', 'particular' => 'IT Tuition Allocation', 'description' => 'IT allocation from tuition'],
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

        // --- Annual Budgets & Items ---
        $budget2025 = AnnualBudget::updateOrCreate(['year' => 2025]);
        $budget2025Items = [
            ['cat' => 1, 'par' => 1, 'appropriation' => 2000000, 'expenditure' => 450000],
            ['cat' => 1, 'par' => 2, 'appropriation' => 1500000, 'expenditure' => 380000],
            ['cat' => 1, 'par' => 3, 'appropriation' => 1200000, 'expenditure' => 290000],
            ['cat' => 2, 'par' => 4, 'appropriation' => 800000, 'expenditure' => 200000],
            ['cat' => 2, 'par' => 5, 'appropriation' => 600000, 'expenditure' => 150000],
            ['cat' => 3, 'par' => 6, 'appropriation' => 3500000, 'expenditure' => 1200000],
            ['cat' => 3, 'par' => 7, 'appropriation' => 4800000, 'expenditure' => 3000000],
            ['cat' => 4, 'par' => 8, 'appropriation' => 1500000, 'expenditure' => 980000],
            ['cat' => 4, 'par' => 9, 'appropriation' => 1200000, 'expenditure' => 650000],
            ['cat' => 5, 'par' => 10, 'appropriation' => 2965005, 'expenditure' => 1450000],
            ['cat' => 5, 'par' => 11, 'appropriation' => 960452, 'expenditure' => 320000],
            ['cat' => 5, 'par' => 12, 'appropriation' => 1441000, 'expenditure' => 720000],
        ];
        foreach ($budget2025Items as $item) {
            BudgetItem::updateOrCreate(
                [
                    'budget_id' => $budget2025->id,
                    'particular_id' => $particulars[$item['par']]->id,
                ],
                [
                    'category_id' => $categories[$item['cat']]->id,
                    'appropriation' => $item['appropriation'],
                    'expenditure' => $item['expenditure'],
                ]
            );
        }

        $budget2026 = AnnualBudget::updateOrCreate(['year' => 2026]);
        $budget2026Items = [
            ['cat' => 1, 'par' => 1, 'appropriation' => 2200000, 'expenditure' => 0],
            ['cat' => 1, 'par' => 2, 'appropriation' => 1650000, 'expenditure' => 0],
            ['cat' => 1, 'par' => 3, 'appropriation' => 1350000, 'expenditure' => 0],
            ['cat' => 2, 'par' => 4, 'appropriation' => 880000, 'expenditure' => 0],
            ['cat' => 2, 'par' => 5, 'appropriation' => 660000, 'expenditure' => 0],
            ['cat' => 3, 'par' => 6, 'appropriation' => 3850000, 'expenditure' => 0],
            ['cat' => 3, 'par' => 7, 'appropriation' => 5500000, 'expenditure' => 0],
        ];
        foreach ($budget2026Items as $item) {
            BudgetItem::updateOrCreate(
                [
                    'budget_id' => $budget2026->id,
                    'particular_id' => $particulars[$item['par']]->id,
                ],
                [
                    'category_id' => $categories[$item['cat']]->id,
                    'appropriation' => $item['appropriation'],
                    'expenditure' => $item['expenditure'],
                ]
            );
        }

        // --- Expenses ---
        $expenseData = [
            ['ref_no' => 'EXP25000001', 'description' => 'Marine Engineering Fund', 'cat' => 3, 'par' => 7, 'amount' => 3000000, 'paid' => 3000000, 'date_encoded' => '2025-01-15', 'date_approved' => '2025-01-20', 'status' => 'approved', 'notes' => 'Marine Engineering program fees'],
            ['ref_no' => 'EXP25000002', 'description' => 'IT Tuition Allocation', 'cat' => 3, 'par' => 6, 'amount' => 1200000, 'paid' => 1200000, 'date_encoded' => '2025-02-01', 'date_approved' => '2025-02-05', 'status' => 'approved', 'notes' => 'IT allocation from tuition fees'],
            ['ref_no' => 'EXP25000003', 'description' => 'Insurance Expense', 'cat' => 1, 'par' => 1, 'amount' => 450000, 'paid' => 450000, 'date_encoded' => '2025-02-15', 'date_approved' => '2025-02-20', 'status' => 'approved', 'notes' => 'Marine Transportation student insurance Q1'],
            ['ref_no' => 'EXP25000004', 'description' => 'Energy Fee', 'cat' => 5, 'par' => 10, 'amount' => 1450000, 'paid' => 1450000, 'date_encoded' => '2025-03-20', 'date_approved' => '2025-03-25', 'status' => 'approved', 'notes' => 'Energy utility payments Q1'],
            ['ref_no' => 'EXP25000005', 'description' => 'Laboratory Supplies', 'cat' => 4, 'par' => 8, 'amount' => 980000, 'paid' => 500000, 'date_encoded' => '2025-04-01', 'date_approved' => null, 'status' => 'pending', 'notes' => 'Lab equipment purchase'],
        ];
        foreach ($expenseData as $exp) {
            Expense::updateOrCreate(
                ['ref_no' => $exp['ref_no']],
                [
                    'description' => $exp['description'],
                    'category_id' => $categories[$exp['cat']]->id,
                    'particular_id' => $particulars[$exp['par']]->id,
                    'amount' => $exp['amount'],
                    'paid' => $exp['paid'],
                    'date_encoded' => $exp['date_encoded'],
                    'date_approved' => $exp['date_approved'],
                    'status' => $exp['status'],
                    'notes' => $exp['notes'],
                ]
            );
        }

        // --- Disbursements ---
        $disbursementData = [
            ['disbursement_no' => 'DSB25000001', 'description' => 'Computer Equipment', 'source' => 'Expenditure', 'pay_to' => 'Tech Solutions Inc.', 'amount' => 850000, 'method' => 'check', 'date_encoded' => '2025-01-20', 'date_approved' => '2025-01-25', 'status' => 'posted', 'notes' => 'Computer lab equipment'],
            ['disbursement_no' => 'DSB25000002', 'description' => 'Faculty Salaries', 'source' => 'Expenditure', 'pay_to' => 'Payroll Account', 'amount' => 2500000, 'method' => 'bank_transfer', 'date_encoded' => '2025-01-30', 'date_approved' => '2025-02-01', 'status' => 'posted', 'notes' => 'January 2025 faculty payroll'],
            ['disbursement_no' => 'DSB25000003', 'description' => 'Office Supplies', 'source' => 'Expenditure', 'pay_to' => 'National Book Store', 'amount' => 125000, 'method' => 'cash', 'date_encoded' => '2025-02-15', 'date_approved' => null, 'status' => 'pending', 'notes' => 'Office supplies for registrar'],
            ['disbursement_no' => 'DSB25000004', 'description' => 'Electricity Bill', 'source' => 'Expenditure', 'pay_to' => 'BOHECO', 'amount' => 750000, 'method' => 'bank_transfer', 'date_encoded' => '2025-03-28', 'date_approved' => '2025-04-02', 'status' => 'posted', 'notes' => 'Electricity bill Jan-Mar 2025'],
        ];
        foreach ($disbursementData as $dsb) {
            Disbursement::updateOrCreate(
                ['disbursement_no' => $dsb['disbursement_no']],
                [
                    'description' => $dsb['description'],
                    'source' => $dsb['source'],
                    'pay_to' => $dsb['pay_to'],
                    'amount' => $dsb['amount'],
                    'method' => $dsb['method'],
                    'date_encoded' => $dsb['date_encoded'],
                    'date_approved' => $dsb['date_approved'],
                    'status' => $dsb['status'],
                    'notes' => $dsb['notes'],
                ]
            );
        }
    }
}
