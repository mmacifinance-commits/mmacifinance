<?php

use App\Models\BudgetItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('budget_item_id')
                ->nullable()
                ->after('particular_id')
                ->constrained('budget_items')
                ->nullOnDelete();
        });

        DB::table('expenses')
            ->select(['id', 'category_id', 'particular_id', 'date_encoded'])
            ->orderBy('id')
            ->chunkById(200, function ($expenses) {
                foreach ($expenses as $expense) {
                    $year = (int) date('Y', strtotime((string) $expense->date_encoded));
                    $month = (int) date('n', strtotime((string) $expense->date_encoded));
                    $candidates = BudgetItem::query()
                        ->where('category_id', $expense->category_id)
                        ->where('particular_id', $expense->particular_id)
                        ->whereHas('budget', fn ($query) => $query->where('year', $year))
                        ->get(['id', 'month']);

                    $sameMonth = $candidates->where('month', $month);
                    $budgetItemId = $sameMonth->count() === 1 ? $sameMonth->first()->id : null;
                    if (! $budgetItemId && $candidates->count() === 1) {
                        $budgetItemId = $candidates->first()->id;
                    }

                    if ($budgetItemId) {
                        DB::table('expenses')->where('id', $expense->id)->update([
                            'budget_item_id' => $budgetItemId,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_item_id');
        });
    }
};
