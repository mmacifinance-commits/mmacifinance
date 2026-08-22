<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tutorial_status')->default('pending')->after('otp_sent_at');
            $table->string('tutorial_version')->nullable()->after('tutorial_status');
            $table->string('tutorial_current_step')->nullable()->after('tutorial_version');
            $table->timestamp('tutorial_completed_at')->nullable()->after('tutorial_current_step');
            $table->timestamp('tutorial_skipped_at')->nullable()->after('tutorial_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tutorial_status',
                'tutorial_version',
                'tutorial_current_step',
                'tutorial_completed_at',
                'tutorial_skipped_at',
            ]);
        });
    }
};
