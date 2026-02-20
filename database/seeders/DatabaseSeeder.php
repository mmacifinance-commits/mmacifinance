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
        // --- Admin User ---
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@mmac.edu.ph',
            'password' => Hash::make('password'),
        ]);

        // --- Departments ---
        $departments = [];
        foreach ([
            ['name' => 'College - Marine Transportation', 'code' => 'MT'],
            ['name' => 'College - Marine Engineering', 'code' => 'ME'],
            ['name' => 'College - Information System', 'code' => 'IS'],
            ['name' => 'Senior High School', 'code' => 'SHS'],
            ['name' => 'Basic Education', 'code' => 'BASED'],
        ] as $dept) {
            $departments[$dept['code']] = Department::create($dept);
        }

        // --- Budget Categories ---
        $categories = [];
        foreach ([
            ['name' => 'TUITION FEES', 'description' => 'College Tuition Fees and Graduate Program Fees'],
            ['name' => 'OTHER TUITION FEES', 'description' => 'Senior High School and Basic Education Tuition Fees'],
            ['name' => 'OTHER SCHOOL FEES', 'description' => 'Miscellaneous, Athletic, Lab, and Other Fees'],
            ['name' => 'LABORATORY FEES', 'description' => 'College Laboratory and Driving Fees'],
            ['name' => 'MISCELLANEOUS INCOME', 'description' => 'Energy Fee, Testing Fee, Registration, and Other Fees'],
        ] as $i => $cat) {
            $categories[$i + 1] = BudgetCategory::create($cat);
        }

        // --- Budget Particulars ---
        $particulars = [];
        $particularData = [
            ['cat' => 1, 'dept' => 'MT', 'account_code' => '4-1001', 'account_name' => 'College - Marine Transportation', 'particular' => 'Tuition Fee - MT', 'description' => 'Marine transportation program tuition'],
            ['cat' => 1, 'dept' => 'ME', 'account_code' => '4-1002', 'account_name' => 'College - Marine Engineering', 'particular' => 'Tuition Fee - ME', 'description' => 'Marine engineering program tuition'],
            ['cat' => 1, 'dept' => 'IS', 'account_code' => '4-1003', 'account_name' => 'College - Information System', 'particular' => 'Tuition Fee - IS', 'description' => 'Information system program tuition'],
            ['cat' => 2, 'dept' => 'SHS', 'account_code' => '4-2001', 'account_name' => 'SHS Tuition', 'particular' => 'SHS Tuition Fee', 'description' => 'Senior high school tuition'],
            ['cat' => 2, 'dept' => 'BASED', 'account_code' => '4-2002', 'account_name' => 'Basic Education Tuition', 'particular' => 'Basic Ed Tuition Fee', 'description' => 'Basic education tuition'],
            ['cat' => 3, 'dept' => 'IS', 'account_code' => '4-1005', 'account_name' => 'College - Information System', 'particular' => 'IT Tuition Allocation', 'description' => 'IT allocation from tuition'],
            ['cat' => 3, 'dept' => 'ME', 'account_code' => '4-1001', 'account_name' => 'College - Marine Engineering', 'particular' => 'Marine Engineering Fund', 'description' => 'Marine engineering program fees'],
            ['cat' => 4, 'dept' => 'MT', 'account_code' => '4-4001', 'account_name' => 'Laboratory Fee - Marine Transport', 'particular' => 'Lab Fee - MT', 'description' => 'Marine transportation lab fee'],
            ['cat' => 4, 'dept' => 'ME', 'account_code' => '4-4002', 'account_name' => 'Laboratory Fee - Marine Eng', 'particular' => 'Lab Fee - ME', 'description' => 'Marine engineering lab fee'],
            ['cat' => 5, 'dept' => 'MT', 'account_code' => '4-3023', 'account_name' => 'Energy Fee', 'particular' => 'Energy Fee', 'description' => 'Campus energy & utility fee'],
            ['cat' => 5, 'dept' => 'IS', 'account_code' => '4-3054', 'account_name' => 'Testing Fee', 'particular' => 'Testing Fee', 'description' => 'Student testing fee'],
            ['cat' => 5, 'dept' => 'BASED', 'account_code' => '4-3024', 'account_name' => 'Registration Fee', 'particular' => 'Registration Fee', 'description' => 'Student registration processing'],
        ];

        foreach ($particularData as $i => $p) {
            $particulars[$i + 1] = BudgetParticular::create([
                'category_id' => $categories[$p['cat']]->id,
                'department_id' => $departments[$p['dept']]->id,
                'account_code' => $p['account_code'],
                'account_name' => $p['account_name'],
                'particular' => $p['particular'],
                'description' => $p['description'],
            ]);
        }

        // --- Annual Budgets & Items ---
        $budget2025 = AnnualBudget::create(['year' => 2025]);
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
            BudgetItem::create([
                'budget_id' => $budget2025->id,
                'category_id' => $categories[$item['cat']]->id,
                'particular_id' => $particulars[$item['par']]->id,
                'appropriation' => $item['appropriation'],
                'expenditure' => $item['expenditure'],
            ]);
        }

        $budget2026 = AnnualBudget::create(['year' => 2026]);
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
            BudgetItem::create([
                'budget_id' => $budget2026->id,
                'category_id' => $categories[$item['cat']]->id,
                'particular_id' => $particulars[$item['par']]->id,
                'appropriation' => $item['appropriation'],
                'expenditure' => $item['expenditure'],
            ]);
        }

        // --- Expenses ---
        $expenseData = [
            ['ref_no' => 'EXP25000001', 'description' => 'TUITION AND OTHER FEES', 'cat' => 3, 'par' => 7, 'amount' => 3000000, 'paid' => 3000000, 'date_encoded' => '2025-01-15', 'date_approved' => '2025-01-20', 'status' => 'approved', 'notes' => 'Marine Engineering program fees'],
            ['ref_no' => 'EXP25000002', 'description' => 'OTHER SCHOOL FEES', 'cat' => 3, 'par' => 6, 'amount' => 1200000, 'paid' => 1200000, 'date_encoded' => '2025-02-01', 'date_approved' => '2025-02-05', 'status' => 'approved', 'notes' => 'IT allocation from tuition fees'],
            ['ref_no' => 'EXP25000003', 'description' => 'TUITION FEE - MT', 'cat' => 1, 'par' => 1, 'amount' => 450000, 'paid' => 450000, 'date_encoded' => '2025-02-15', 'date_approved' => '2025-02-20', 'status' => 'approved', 'notes' => 'Marine Transportation tuition Q1'],
            ['ref_no' => 'EXP25000004', 'description' => 'MISCELLANEOUS INCOME', 'cat' => 5, 'par' => 10, 'amount' => 1450000, 'paid' => 1450000, 'date_encoded' => '2025-03-20', 'date_approved' => '2025-03-25', 'status' => 'approved', 'notes' => 'Energy utility payments Q1'],
            ['ref_no' => 'EXP25000005', 'description' => 'LABORATORY FEES', 'cat' => 4, 'par' => 8, 'amount' => 980000, 'paid' => 500000, 'date_encoded' => '2025-04-01', 'date_approved' => null, 'status' => 'pending', 'notes' => 'Lab equipment purchase'],
        ];
        foreach ($expenseData as $exp) {
            Expense::create([
                'ref_no' => $exp['ref_no'],
                'description' => $exp['description'],
                'category_id' => $categories[$exp['cat']]->id,
                'particular_id' => $particulars[$exp['par']]->id,
                'amount' => $exp['amount'],
                'paid' => $exp['paid'],
                'date_encoded' => $exp['date_encoded'],
                'date_approved' => $exp['date_approved'],
                'status' => $exp['status'],
                'notes' => $exp['notes'],
            ]);
        }

        // --- Disbursements ---
        $disbursementData = [
            ['disbursement_no' => 'DSB25000001', 'description' => 'Computer Equipment', 'source' => 'Expenditure', 'pay_to' => 'Tech Solutions Inc.', 'amount' => 850000, 'method' => 'check', 'date_encoded' => '2025-01-20', 'date_approved' => '2025-01-25', 'status' => 'posted', 'notes' => 'Computer lab equipment'],
            ['disbursement_no' => 'DSB25000002', 'description' => 'Faculty Salaries', 'source' => 'Expenditure', 'pay_to' => 'Payroll Account', 'amount' => 2500000, 'method' => 'bank_transfer', 'date_encoded' => '2025-01-30', 'date_approved' => '2025-02-01', 'status' => 'posted', 'notes' => 'January 2025 faculty payroll'],
            ['disbursement_no' => 'DSB25000003', 'description' => 'Office Supplies', 'source' => 'Expenditure', 'pay_to' => 'National Book Store', 'amount' => 125000, 'method' => 'cash', 'date_encoded' => '2025-02-15', 'date_approved' => null, 'status' => 'pending', 'notes' => 'Office supplies for registrar'],
            ['disbursement_no' => 'DSB25000004', 'description' => 'Electricity Bill', 'source' => 'Expenditure', 'pay_to' => 'BOHECO', 'amount' => 750000, 'method' => 'bank_transfer', 'date_encoded' => '2025-03-28', 'date_approved' => '2025-04-02', 'status' => 'posted', 'notes' => 'Electricity bill Jan-Mar 2025'],
        ];
        foreach ($disbursementData as $dsb) {
            Disbursement::create($dsb);
        }
    }
}
