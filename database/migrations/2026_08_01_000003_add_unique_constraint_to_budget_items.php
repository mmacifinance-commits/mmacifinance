<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        if (Schema::hasIndex('budget_items', 'budget_items_budget_particular_month_unique')) {
            try {
                Schema::table('budget_items', function (Blueprint $table) {
                    $table->dropUnique('budget_items_budget_particular_month_unique');
                });
            } catch (QueryException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) !== 1553) {
                    throw $exception;
                }
            }
        }
    }
};
