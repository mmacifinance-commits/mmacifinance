<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('disbursement_no', 20)->unique();
            $table->string('description');
            $table->string('source');
            $table->string('pay_to');
            $table->decimal('amount', 15, 2)->default(0);
            $table->enum('method', ['check', 'cash', 'bank_transfer'])->default('check');
            $table->date('date_encoded');
            $table->date('date_approved')->nullable();
            $table->enum('status', ['pending', 'approved', 'posted', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
