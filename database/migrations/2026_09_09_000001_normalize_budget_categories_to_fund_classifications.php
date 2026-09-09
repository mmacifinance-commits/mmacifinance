<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $fundClassifications = [
                'AUXILIARY FUND' => 'Income Generating Projects and Other Income',
                'TRUST FUND' => 'Trust Liabilities',
                'TUITION AND OTHER FEES' => 'Tuition Fee, Miscellaneous, and Laboratory Fees',
                'LABORATORY FEES' => 'College Laboratory and Driving Fees',
                'MISCELLANEOUS INCOME' => 'Energy Fee, Testing Fee, Registration, and Other Fees',
            ];
            $canonicalIds = [];

            foreach ($fundClassifications as $name => $description) {
                $matches = DB::table('budget_categories')
                    ->get()
                    ->filter(fn ($category) => mb_strtoupper(trim((string) $category->name)) === $name)
                    ->sortBy('id')
                    ->values();

                $primary = $matches->first();

                if ($primary) {
                    $primaryId = (int) $primary->id;
                    DB::table('budget_categories')->where('id', $primaryId)->update([
                        'name' => $name,
                        'description' => $description,
                        'updated_at' => now(),
                    ]);
                } else {
                    $primaryId = (int) DB::table('budget_categories')->insertGetId([
                        'name' => $name,
                        'description' => $description,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $duplicateIds = $matches->pluck('id')->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === $primaryId);
                $this->moveCategoryReferences($duplicateIds->all(), $primaryId);
                DB::table('budget_categories')->whereIn('id', $duplicateIds->all())->delete();

                $canonicalIds[$name] = $primaryId;
            }

            $legacyIds = DB::table('budget_categories')
                ->whereNotIn('id', array_values($canonicalIds))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->moveCategoryReferences($legacyIds, $canonicalIds['AUXILIARY FUND']);
            DB::table('budget_categories')->whereIn('id', $legacyIds)->delete();
        });
    }

    public function down(): void
    {
        // Legacy category meanings cannot be reconstructed after their records are consolidated.
    }

    private function moveCategoryReferences(array $sourceIds, int $targetId): void
    {
        if ($sourceIds === []) {
            return;
        }

        foreach (['budget_particulars', 'budget_items', 'expenses'] as $table) {
            DB::table($table)->whereIn('category_id', $sourceIds)->update(['category_id' => $targetId]);
        }
    }
};
