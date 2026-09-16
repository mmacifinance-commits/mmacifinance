<?php

require __DIR__.'/browser-environment.php';
$handle = fopen($testDatabase, 'x');
if (!$handle) throw new RuntimeException('Refusing to reuse an existing browser database.');
fclose($handle);
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $testDatabase) throw new RuntimeException('Unsafe browser database configuration.');
\Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
foreach (['super_admin', 'cashier', 'budget_officer', 'disbursement_officer', 'auditor'] as $role) {
    \App\Models\User::factory()->create(['role' => $role]);
}
$category = \App\Models\BudgetCategory::firstOrCreate(['name' => 'AUXILIARY FUND']);
$department = \App\Models\Department::create(['name' => 'Browser Finance', 'code' => 'BF']);
$particular = \App\Models\BudgetParticular::create(['category_id' => $category->id, 'department_id' => $department->id, 'account_code' => 'B-1', 'account_name' => 'Browser Supplies', 'particular' => 'Browser Supplies']);
$budget = \App\Models\AnnualBudget::create(['year' => 2026, 'semester' => 'Fiscal Year', 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
$item = \App\Models\BudgetItem::create(['budget_id' => $budget->id, 'category_id' => $category->id, 'particular_id' => $particular->id, 'allocation_month' => '2026-09-01', 'month' => 9, 'appropriation' => 50000]);
\App\Models\Expense::create(['ref_no' => 'EXP00000001', 'description' => 'Browser supplies', 'category_id' => $category->id, 'particular_id' => $particular->id, 'budget_item_id' => $item->id, 'amount' => 1000, 'paid' => 0, 'date_encoded' => '2026-09-16', 'status' => 'pending']);
for ($i=0; $i<26; $i++) \App\Models\Department::create(['name' => "Browser Center $i", 'code' => "BC$i"]);
echo "Disposable browser fixture ready.\n";
