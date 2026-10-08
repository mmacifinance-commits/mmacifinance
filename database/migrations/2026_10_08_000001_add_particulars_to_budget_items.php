<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->string('particulars', 255)->default('');
        });
        // Create the replacement first so MySQL retains an index for the foreign key.
        Schema::table('budget_items', function (Blueprint $table) {
            $table->unique(['budget_id', 'particular_id', 'allocation_month', 'particulars'], 'budget_items_allocation_particulars_unique');
        });
        foreach (['budget_items_budget_particular_month_unique', 'budget_items_budget_particular_allocation_month_unique'] as $index) {
            if (Schema::hasIndex('budget_items', $index)) {
                Schema::table('budget_items', fn (Blueprint $table) => $table->dropUnique($index));
            }
        }
    }

    public function down(): void
    {
        if (DB::table('budget_items')->groupBy('budget_id', 'particular_id', 'month')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot remove particulars while multiple allocations share an account title and month. Preserve these allocations before reverting.');
        }
        Schema::table('budget_items', function (Blueprint $table) {
            $table->unique(['budget_id', 'particular_id', 'month'], 'budget_items_budget_particular_month_unique');
            $table->unique(['budget_id', 'particular_id', 'allocation_month'], 'budget_items_budget_particular_allocation_month_unique');
        });
        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropUnique('budget_items_allocation_particulars_unique');
            $table->dropColumn('particulars');
        });
    }
};
