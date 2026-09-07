<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE expenses
            MODIFY status ENUM(
                'pending',
                'for_approval',
                'approved',
                'returned_for_revision',
                'rejected',
                'posted',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE expenses
            MODIFY status ENUM(
                'pending',
                'approved',
                'posted',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'
        ");
    }
};
