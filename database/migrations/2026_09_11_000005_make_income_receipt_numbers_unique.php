<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('incomes')
            ->select('receipt_no')
            ->whereNotNull('receipt_no')
            ->where('receipt_no', '<>', '')
            ->groupBy('receipt_no')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('receipt_no');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot add unique receipt number index. Duplicate receipt_no value(s): '.$duplicates->implode(', '));
        }

        Schema::table('incomes', function (Blueprint $table) {
            $table->dropIndex(['receipt_no']);
            $table->unique('receipt_no');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropUnique(['receipt_no']);
            $table->index('receipt_no');
        });
    }
};
