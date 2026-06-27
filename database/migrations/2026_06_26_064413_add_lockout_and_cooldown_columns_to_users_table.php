<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('failed_login_attempts')->default(0)->after('otp_expires_at');
            $table->integer('lockout_level')->default(0)->after('failed_login_attempts');
            $table->timestamp('locked_until')->nullable()->after('lockout_level');
            $table->timestamp('otp_sent_at')->nullable()->after('locked_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'failed_login_attempts',
                'lockout_level',
                'locked_until',
                'otp_sent_at',
            ]);
        });
    }
};
