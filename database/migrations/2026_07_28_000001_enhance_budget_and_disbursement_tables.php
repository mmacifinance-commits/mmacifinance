<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Update annual_budgets
        Schema::table('annual_budgets', function (Blueprint $table) {
            if (!Schema::hasColumn('annual_budgets', 'ref_no')) {
                $table->string('ref_no', 30)->nullable()->after('id');
            }
        });

        // 2. Update budget_items
        Schema::table('budget_items', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_items', 'ref_no')) {
                $table->string('ref_no', 30)->nullable()->after('id');
            }
            if (!Schema::hasColumn('budget_items', 'month')) {
                $table->unsignedTinyInteger('month')->default(1)->after('ref_no'); // 1-12
            }
        });

        // 3. Update disbursements
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE disbursements MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'draft'");

        Schema::table('disbursements', function (Blueprint $table) {
            if (!Schema::hasColumn('disbursements', 'remarks')) {
                $table->text('remarks')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('disbursements', 'prepared_by_id')) {
                $table->foreignId('prepared_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('disbursements', 'released_by_id')) {
                $table->foreignId('released_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('disbursements', 'submitted_by_id')) {
                $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('disbursements', 'approved_by_id')) {
                $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('disbursements', 'rejected_by_id')) {
                $table->foreignId('rejected_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('disbursements', 'posted_by_id')) {
                $table->foreignId('posted_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        // 4. Create audit_trails table
        if (!Schema::hasTable('audit_trails')) {
            Schema::create('audit_trails', function (Blueprint $table) {
                $table->id();
                $table->string('auditable_type');
                $table->unsignedBigInteger('auditable_id');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();
                $table->string('user_role')->nullable();
                $table->string('action'); // created, prepared, modified, released, submitted, approved, rejected, posted, deleted
                $table->text('remarks')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['auditable_type', 'auditable_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');

        Schema::table('disbursements', function (Blueprint $table) {
            $table->dropColumn([
                'remarks',
                'prepared_by_id',
                'released_by_id',
                'submitted_by_id',
                'approved_by_id',
                'rejected_by_id',
                'posted_by_id',
            ]);
        });

        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropColumn(['ref_no', 'month']);
        });

        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->dropColumn(['ref_no']);
        });
    }
};
