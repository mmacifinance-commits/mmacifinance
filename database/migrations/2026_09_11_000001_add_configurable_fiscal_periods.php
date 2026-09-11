<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('annual_budgets', 'start_date')) {
            Schema::table('annual_budgets', function (Blueprint $table) {
                $table->date('start_date')->nullable()->after('year');
            });
        }

        if (! Schema::hasColumn('annual_budgets', 'end_date')) {
            Schema::table('annual_budgets', function (Blueprint $table) {
                $table->date('end_date')->nullable()->after('start_date');
            });
        }

        if (! Schema::hasIndex('annual_budgets', 'annual_budgets_period_index')) {
            Schema::table('annual_budgets', function (Blueprint $table) {
                $table->index(['start_date', 'end_date'], 'annual_budgets_period_index');
            });
        }

        if (! Schema::hasColumn('budget_items', 'allocation_month')) {
            Schema::table('budget_items', function (Blueprint $table) {
                $table->date('allocation_month')->nullable()->after('month');
            });
        }

        if (! Schema::hasIndex('budget_items', 'budget_items_allocation_month_index')) {
            Schema::table('budget_items', function (Blueprint $table) {
                $table->index(['budget_id', 'allocation_month'], 'budget_items_allocation_month_index');
            });
        }

        DB::table('annual_budgets')->orderBy('id')->each(function ($budget) {
            $startDate = Carbon::create((int) $budget->year, 1, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfYear()->startOfDay();

            $periodUpdates = [];
            if ($budget->start_date === null) {
                $periodUpdates['start_date'] = $startDate->toDateString();
            }
            if ($budget->end_date === null) {
                $periodUpdates['end_date'] = $endDate->toDateString();
            }
            if ($periodUpdates !== []) {
                DB::table('annual_budgets')->where('id', $budget->id)->update($periodUpdates);
            }

            DB::table('budget_items')
                ->where('budget_id', $budget->id)
                ->whereNull('allocation_month')
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

        // Keep the legacy month index in place for MySQL because it may be
        // reused as the supporting index for the budget_items foreign keys.
        // The allocation-month unique index below is the authoritative rule.

        if (! Schema::hasIndex('budget_items', 'budget_items_budget_particular_allocation_month_unique')) {
            Schema::table('budget_items', function (Blueprint $table) {
                $table->unique(
                    ['budget_id', 'particular_id', 'allocation_month'],
                    'budget_items_budget_particular_allocation_month_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropUnique('budget_items_budget_particular_allocation_month_unique');
            $table->dropIndex('budget_items_allocation_month_index');
            $table->dropColumn('allocation_month');
        });

        if (! Schema::hasIndex('budget_items', 'budget_items_budget_particular_month_unique')) {
            try {
                Schema::table('budget_items', function (Blueprint $table) {
                    $table->unique(['budget_id', 'particular_id', 'month'], 'budget_items_budget_particular_month_unique');
                });
            } catch (QueryException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) !== 1061) {
                    throw $exception;
                }
            }
        }

        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->dropIndex('annual_budgets_period_index');
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
