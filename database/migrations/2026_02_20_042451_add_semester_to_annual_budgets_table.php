<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->string('semester', 20)->nullable()->after('year');
        });

        // Drop the unique constraint on year alone (year+semester combo can repeat)
        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->dropUnique(['year']);
            $table->unique(['year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->dropUnique(['year', 'semester']);
            $table->unique('year');
            $table->dropColumn('semester');
        });
    }
};
