<?php
namespace App\Support;

use App\Models\{Income, BudgetCategory, Department};
use Illuminate\Validation\ValidationException;

class IncomeSpreadsheetFields
{
    public static function attributes(array $row, bool $projected = true): array
    {
        $attributes = [];
        foreach (['budget_category' => [BudgetCategory::class, 'budget_category_id'], 'responsibility_center' => [Department::class, 'department_id']] as $column => [$model, $field]) {
            if (!array_key_exists($column, $row)) continue;
            $value = trim((string) $row[$column]);
            if ($value === '') { $attributes[$field] = null; continue; }
            $query = $model::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($value)]);
            if ($column === 'responsibility_center') $query->orWhere('code', $value);
            $matches = $query->get();
            if ($matches->count() !== 1) throw ValidationException::withMessages(['csv_file' => "Unknown or ambiguous {$column}: {$value}. Use an existing name from Budget."]);
            $attributes[$field] = $matches->first()->id;
        }
        if ($projected && (array_key_exists('student_count', $row) || array_key_exists('fee_per_student', $row))) {
            $inputs = ['student_count' => filled($row['student_count'] ?? null) ? $row['student_count'] : null,
                'fee_per_student' => filled($row['fee_per_student'] ?? null) ? $row['fee_per_student'] : null];
            $validated = validator($inputs, ['student_count' => 'nullable|required_with:fee_per_student|integer|min:0|max:1000000',
                'fee_per_student' => 'nullable|required_with:student_count|numeric|min:0|max:9999999.99'])->validate();
            $attributes += $validated;
            if (isset($validated['student_count'], $validated['fee_per_student'])) {
                $attributes['fee_per_student'] = round((float) $validated['fee_per_student'], 2);
                $attributes['amount'] = round($validated['student_count'] * $attributes['fee_per_student'], 2);
            }
        }
        return $attributes;
    }

    public static function findProjected(array $row): ?Income
    {
        if (filled($row['income_no'] ?? null)) return Income::projected()->where('income_no', trim($row['income_no']))->first();
        $query = Income::projected()->where('source', trim($row['source'] ?? ''))
            ->where('description', trim($row['description'] ?? ''))->whereDate('date_encoded', $row['date_encoded']);
        foreach (self::attributes($row) as $field => $value) {
            if (in_array($field, ['budget_category_id', 'department_id'])) $query->where($field, $value);
        }
        $matches = $query->get();
        if ($matches->count() > 1) throw ValidationException::withMessages(['csv_file' => 'Ambiguous income match. Include income_no from an export.']);
        return $matches->first();
    }
}
