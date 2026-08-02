<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending', 'approved', 'posted', 'cancelled') NOT NULL DEFAULT 'pending'");

        DB::table('expenses')
            ->where('status', 'approved')
            ->update(['status' => 'posted']);

        DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending', 'posted', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending', 'approved', 'posted', 'cancelled') NOT NULL DEFAULT 'pending'");

        DB::table('expenses')
            ->where('status', 'posted')
            ->update(['status' => 'approved']);

        DB::statement("ALTER TABLE expenses MODIFY status ENUM('pending', 'approved', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
