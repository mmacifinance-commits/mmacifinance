<?php

namespace Tests\Feature;

use App\Models\{BudgetCategory, Department, User};
use Tests\SafeRefreshDatabase;
use Tests\TestCase;

class BudgetSetupSearchTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_setup_search_filters_before_pagination_and_preserves_query(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'budget_officer']));
        for ($i = 0; $i < 26; $i++) {
            Department::create(['name' => 'Search Center '.$i, 'code' => 'SC'.$i]);
            BudgetCategory::create(['name' => 'Search Category '.$i, 'description' => 'Matching description']);
        }
        $this->get('/departments?search=SC25')->assertOk()->assertInertia(fn ($page) => $page
            ->where('departments.total', 1)->where('departments.data.0.code', 'SC25'));
        $this->get('/departments?search=Search%20Center&page=2')->assertOk()->assertInertia(fn ($page) => $page
            ->where('departments.total', 26)->has('departments.data', 1)
            ->where('filters.search', 'Search Center')
            ->where('departments.prev_page_url', fn ($url) => str_contains($url, 'search=Search%20Center')));
        $this->get('/budget-categories?search=Matching%20description&page=2')->assertOk()->assertInertia(fn ($page) => $page
            ->where('categories.total', 26)->has('categories.data', 1));
        $this->get('/budget-categories?search=Search%20Category%2025')->assertOk()->assertInertia(fn ($page) => $page
            ->where('categories.total', 1)->where('categories.data.0.name', 'Search Category 25'));
        $this->get('/departments?search=missing-record')->assertOk()->assertInertia(fn ($page) => $page->where('departments.total', 0));
    }
}
