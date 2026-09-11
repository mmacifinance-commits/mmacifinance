<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('end_date');
            $table->foreignId('closed_by_id')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->string('close_remarks')->nullable()->after('closed_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by_id');
            $table->dropColumn(['closed_at', 'close_remarks']);
        });
    }
};
