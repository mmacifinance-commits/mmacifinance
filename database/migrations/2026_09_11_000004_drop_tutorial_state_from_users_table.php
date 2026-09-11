<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'tutorial_status',
                'tutorial_version',
                'tutorial_current_step',
                'tutorial_completed_at',
                'tutorial_skipped_at',
            ];

            $existing = array_filter($columns, fn ($column) => Schema::hasColumn('users', $column));
            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'tutorial_status')) {
                $table->string('tutorial_status')->default('pending')->after('otp_sent_at');
            }
            if (! Schema::hasColumn('users', 'tutorial_version')) {
                $table->string('tutorial_version')->nullable()->after('tutorial_status');
            }
            if (! Schema::hasColumn('users', 'tutorial_current_step')) {
                $table->string('tutorial_current_step')->nullable()->after('tutorial_version');
            }
            if (! Schema::hasColumn('users', 'tutorial_completed_at')) {
                $table->timestamp('tutorial_completed_at')->nullable()->after('tutorial_current_step');
            }
            if (! Schema::hasColumn('users', 'tutorial_skipped_at')) {
                $table->timestamp('tutorial_skipped_at')->nullable()->after('tutorial_completed_at');
            }
        });
    }
};
