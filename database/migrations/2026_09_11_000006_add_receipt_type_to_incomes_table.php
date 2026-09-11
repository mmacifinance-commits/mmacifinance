<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->string('receipt_type', 100)->nullable()->after('receipt_no')->index();
        });

        DB::table('incomes')
            ->whereNotNull('receipt_no')
            ->where('receipt_no', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $text = strtolower(trim(($row->source ?? '').' '.($row->description ?? '')));
                    $type = match (true) {
                        str_contains($text, 'enrollment') => 'Enrollment',
                        str_contains($text, 'premidterm'), str_contains($text, 'pre midterm'), str_contains($text, 'prelim') => 'Premidterm / Prelim',
                        str_contains($text, 'midterm') => 'Midterm',
                        str_contains($text, 'pre-final'), str_contains($text, 'prefinal') => 'Pre-Final',
                        str_contains($text, 'final exam'), str_contains($text, 'final') => 'Final Exam',
                        default => 'Cash Receipt',
                    };

                    DB::table('incomes')->where('id', $row->id)->update(['receipt_type' => $type]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropIndex(['receipt_type']);
            $table->dropColumn('receipt_type');
        });
    }
};
