<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('budget_category_id')->nullable()->constrained('budget_categories')->nullOnDelete();
        });
        // Only unambiguous exact names are safe to classify automatically.
        $categories = DB::table('budget_categories')->get()->groupBy(fn ($category) => mb_strtolower(trim($category->name)));
        foreach ($categories as $name => $matches) {
            if ($matches->count() === 1 && $name !== '') {
                DB::table('incomes')->whereRaw('LOWER(TRIM(source)) = ?', [$name])
                    ->update(['budget_category_id' => $matches->first()->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_category_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
