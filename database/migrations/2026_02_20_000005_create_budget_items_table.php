<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('annual_budgets')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('budget_categories')->cascadeOnDelete();
            $table->foreignId('particular_id')->constrained('budget_particulars')->cascadeOnDelete();
            $table->decimal('appropriation', 15, 2)->default(0);
            $table->decimal('expenditure', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
