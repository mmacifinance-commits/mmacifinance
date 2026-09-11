<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending', 'approved', 'posted', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        DB::table('expenses')
            ->where('status', 'approved')
            ->update(['status' => 'posted']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending', 'posted', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending', 'approved', 'posted', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        DB::table('expenses')
            ->where('status', 'posted')
            ->update(['status' => 'approved']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending', 'for_approval', 'approved', 'returned_for_revision', 'rejected', 'posted', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
