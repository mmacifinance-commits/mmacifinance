<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->index('date_encoded', 'expenses_date_encoded_index');
            $table->index(['status', 'date_encoded'], 'expenses_status_date_index');
        });

        Schema::table('disbursements', function (Blueprint $table) {
            $table->index('date_encoded', 'disbursements_date_encoded_index');
            $table->index(['status', 'date_encoded'], 'disbursements_status_date_index');
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->index('date_encoded', 'incomes_date_encoded_index');
        });

        Schema::table('budget_items', function (Blueprint $table) {
            $table->index(['budget_id', 'month'], 'budget_items_budget_month_index');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_date_encoded_index');
            $table->dropIndex('expenses_status_date_index');
        });

        Schema::table('disbursements', function (Blueprint $table) {
            $table->dropIndex('disbursements_date_encoded_index');
            $table->dropIndex('disbursements_status_date_index');
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->dropIndex('incomes_date_encoded_index');
        });

        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropIndex('budget_items_budget_month_index');
        });
    }
};
