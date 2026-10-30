<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->unsignedInteger('student_count')->nullable();
            $table->decimal('fee_per_student', 12, 2)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('incomes', fn (Blueprint $table) => $table->dropColumn(['student_count', 'fee_per_student']));
    }
};
