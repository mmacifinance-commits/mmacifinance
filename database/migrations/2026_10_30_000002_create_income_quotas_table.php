<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('income_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_budget_id')->constrained('annual_budgets')->cascadeOnDelete();
            $table->string('source');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['annual_budget_id', 'source']);
        });
    }
    public function down(): void { Schema::dropIfExists('income_quotas'); }
};
