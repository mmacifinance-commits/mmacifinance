<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('year');
            $table->date('end_date')->nullable()->after('start_date');
            $table->index(['start_date', 'end_date'], 'annual_budgets_period_index');
        });

        Schema::table('budget_items', function (Blueprint $table) {
            $table->date('allocation_month')->nullable()->after('month');
            $table->index(['budget_id', 'allocation_month'], 'budget_items_allocation_month_index');
        });

        DB::table('annual_budgets')->orderBy('id')->each(function ($budget) {
            $startDate = Carbon::create((int) $budget->year, 1, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfYear()->startOfDay();

            DB::table('annual_budgets')->where('id', $budget->id)->update([
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            DB::table('budget_items')
                ->where('budget_id', $budget->id)
                ->orderBy('id')
                ->each(function ($item) use ($budget) {
                    $month = min(12, max(1, (int) ($item->month ?: 1)));
                    DB::table('budget_items')->where('id', $item->id)->update([
                        'allocation_month' => Carbon::create((int) $budget->year, $month, 1)->toDateString(),
                    ]);
                });
        });

        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });

        Schema::table('budget_items', function (Blueprint $table) {
            $table->date('allocation_month')->nullable(false)->change();
        });

        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropUnique('budget_items_budget_particular_month_unique');
            $table->unique(
                ['budget_id', 'particular_id', 'allocation_month'],
                'budget_items_budget_particular_allocation_month_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropUnique('budget_items_budget_particular_allocation_month_unique');
            $table->dropIndex('budget_items_allocation_month_index');
            $table->dropColumn('allocation_month');
            $table->unique(['budget_id', 'particular_id', 'month'], 'budget_items_budget_particular_month_unique');
        });

        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->dropIndex('annual_budgets_period_index');
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
