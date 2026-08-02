<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('annual_budgets')
            ->whereNull('semester')
            ->orWhere('semester', '')
            ->update(['semester' => 'Full Year (Jan-Dec)']);

        DB::table('annual_budgets')
            ->where('semester', 'Full Year (Jan – Dec)')
            ->update(['semester' => 'Full Year (Jan-Dec)']);
    }

    public function down(): void
    {
        // Keep existing records as-is; this migration only normalizes labels.
    }
};
