<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckRole;
use App\Models\{AnnualBudget, BudgetCategory, BudgetItem, BudgetParticular, Department, Disbursement, Expense, Income, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class RoleRouteCoverageTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_every_role_rejects_every_unauthorized_protected_route(): void
    {
        $category = BudgetCategory::firstOrCreate(['name' => 'AUXILIARY FUND']);
        $department = Department::create(['name' => 'Role Test', 'code' => 'ROLE']);
        $particular = BudgetParticular::create(['category_id' => $category->id, 'department_id' => $department->id, 'account_code' => 'R1', 'account_name' => 'Role Test', 'particular' => 'Role Test']);
        $budget = AnnualBudget::create(['year' => 2026, 'semester' => 'Fiscal Year', 'start_date' => '2026-08-01', 'end_date' => '2027-07-31']);
        $item = BudgetItem::create(['budget_id' => $budget->id, 'category_id' => $category->id, 'particular_id' => $particular->id, 'month' => 8, 'allocation_month' => '2026-08-01', 'appropriation' => 100]);
        $expense = Expense::create(['ref_no' => 'ROLE-EXP', 'category_id' => $category->id, 'particular_id' => $particular->id, 'budget_item_id' => $item->id, 'description' => 'Role test', 'amount' => 10, 'date_encoded' => '2026-08-02', 'status' => 'approved']);
        $payment = Disbursement::create(['disbursement_no' => 'ROLE-DSB', 'expense_id' => $expense->id, 'description' => 'Role test', 'source' => 'Expense', 'pay_to' => 'Vendor', 'amount' => 10, 'method' => 'cash', 'date_encoded' => '2026-08-02', 'status' => 'draft']);
        $income = Income::create(['income_no' => 'ROLE-INC', 'receipt_no' => 'ROLE-OR', 'source' => 'Collection', 'description' => 'Role test', 'amount' => 100, 'date_encoded' => '2026-08-01']);
        $params = ['annual_budget' => $budget->id, 'item' => $item->id, 'budget_category' => $category->id, 'budget_particular' => $particular->id, 'department' => $department->id, 'expense' => $expense->id, 'disbursement' => $payment->id, 'income' => $income->id, 'receipt' => $income->id];
        foreach (['super_admin', 'budget_officer', 'disbursement_officer', 'cashier', 'auditor'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            foreach (Route::getRoutes() as $route) {
                $rule = collect($route->gatherMiddleware())->first(fn ($rule) => str_starts_with($rule, 'role:'));
                if (!$rule) continue;
                $roles = explode(',', substr($rule, 5));
                $uri = '/'.preg_replace_callback('/\{([^}]+)\}/', fn ($m) => $params[$m[1]] ?? 1, $route->uri());
                if (!in_array($role, $roles, true)) {
                    $this->json($route->methods()[0], $uri)->assertForbidden();
                } else {
                    // Exercise allowed middleware without mutating fixtures needed by subsequent routes.
                    $request = Request::create($uri, $route->methods()[0]);
                    $request->setUserResolver(fn () => $user);
                    $this->assertSame(200, (new CheckRole)->handle($request, fn () => response('allowed'), ...$roles)->getStatusCode());
                }
            }
            $this->get('/reports')->assertOk();
            $this->get('/reports/generate')->assertOk();
            $this->get('/reports/export')->assertOk();
        }
    }
}
