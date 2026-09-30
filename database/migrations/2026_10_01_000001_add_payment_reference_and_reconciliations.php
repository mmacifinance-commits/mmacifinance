<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursements', function (Blueprint $table) {
            $table->string('payment_reference', 100)->nullable()->unique();
        });
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('as_of_date');
            $table->decimal('opening_balance', 18, 2);
            $table->decimal('receipts', 18, 2);
            $table->decimal('payments', 18, 2);
            $table->decimal('book_balance', 18, 2);
            $table->decimal('actual_cash', 18, 2);
            $table->decimal('bank_balance', 18, 2);
            $table->decimal('deposits_in_transit', 18, 2);
            $table->decimal('outstanding_payments', 18, 2);
            $table->decimal('difference', 18, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->string('created_by_name');
            $table->string('created_by_role');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
        Schema::table('disbursements', fn (Blueprint $table) => $table->dropColumn('payment_reference'));
    }
};
