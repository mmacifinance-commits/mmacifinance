<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class BudgetItem extends Model
{
    protected $fillable = [
        'ref_no',
        'budget_id',
        'category_id',
        'particular_id',
        'month',
        'appropriation',
    ];

    protected $casts = [
        'month' => 'integer',
        'appropriation' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $model) {
            // Budget allocations must never store an independently edited expenditure.
            // The displayed expenditure is always derived from posted disbursements.
            $model->expenditure = 0;

            if (!$model->budget_id || !$model->particular_id) {
                return;
            }

            $budget = AnnualBudget::find($model->budget_id);
            $particular = BudgetParticular::with('category', 'department')->find($model->particular_id);

            if (!$budget || !$particular) {
                return;
            }

            $month = (int) ($model->month ?: 1);
            if ($model->month !== null) {
                $model->month = $month;
            }

            if (!empty($model->ref_no) && preg_match('/^MB-(\d{4})-(\d{2})-(\d{4})$/', (string) $model->ref_no, $matches)) {
                $refYear = (int) $matches[1];
                $refMonth = (int) $matches[2];

                if ($refYear !== (int) $budget->year || $refMonth !== $month) {
                    throw ValidationException::withMessages([
                        'ref_no' => "The monthly reference number {$model->ref_no} must match fiscal year {$budget->year} and month {$month}.",
                    ]);
                }
            }

            $duplicateExists = self::query()
                ->where('budget_id', $model->budget_id)
                ->where('particular_id', $model->particular_id)
                ->where('month', $month)
                ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'particular_id' => "A budget record already exists for {$particular->particular} in {$budget->year}, month {$month}.",
                ]);
            }
        });

        static::creating(function ($model) {
            if (empty($model->ref_no)) {
                $year = 2026;
                if ($model->budget_id) {
                    $b = AnnualBudget::find($model->budget_id);
                    if ($b) {
                        $year = $b->year;
                    }
                }
                $m = $model->month ?: 1;
                $count = self::where('budget_id', $model->budget_id)->where('month', $m)->count() + 1;
                $model->ref_no = sprintf('MB-%d-%02d-%04d', $year, $m, $count);
            }
        });
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(AnnualBudget::class, 'budget_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'category_id');
    }

    public function particular(): BelongsTo
    {
        return $this->belongsTo(BudgetParticular::class, 'particular_id');
    }

    public function accountTitle(): BelongsTo
    {
        return $this->particular();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'particular_id', 'particular_id');
    }

    public function postedExpenditureTotal(): float
    {
        $budgetYear = (int) ($this->budget?->year ?? 0);
        $month = (int) ($this->month ?: 1);
        $departmentId = $this->particular?->department_id;
        $budgetTitle = $this->normalizedBudgetTitle();

        if ($budgetYear <= 0) {
            return 0.0;
        }

        $query = Disbursement::query()
            ->where('status', 'posted')
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->whereHas('expense.particular', function ($particularQuery) use ($departmentId) {
                    $particularQuery->where('department_id', $departmentId);
                });
            });

        $baseQuery = function ($query) use ($budgetYear) {
            $query->where('category_id', $this->category_id)
                ->whereYear('date_encoded', $budgetYear);
        };

        $strictTotal = (float) (clone $query)
            ->whereHas('expense', function ($expenseQuery) use ($budgetYear, $month, $baseQuery) {
                $baseQuery($expenseQuery);
                $expenseQuery->whereMonth('date_encoded', $month);
                $this->applyExpenseTitleMatch($expenseQuery, $budgetTitle);
            })
            ->sum('amount');

        if ($strictTotal > 0) {
            return $strictTotal;
        }

        return (float) $query
            ->whereHas('expense', function ($expenseQuery) use ($baseQuery) {
                $baseQuery($expenseQuery);
                $this->applyExpenseTitleMatch($expenseQuery, $budgetTitle);
            })
            ->sum('amount');
    }

    protected function applyExpenseTitleMatch($query, string $budgetTitle): void
    {
        if ($budgetTitle === '') {
            return;
        }

        $query->where(function ($titleQuery) use ($budgetTitle) {
            $titleQuery->where('particular_id', $this->particular_id)
                ->orWhereHas('particular', function ($particularQuery) use ($budgetTitle) {
                    $particularQuery->whereRaw('LOWER(TRIM(particular)) = ?', [$budgetTitle]);
                });
        });
    }

    protected function normalizedBudgetTitle(): string
    {
        return Str::of((string) ($this->particular?->particular ?? $this->particular?->account_name ?? ''))
            ->lower()
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->toString();
    }

    public function getExpenditureAttribute($value): float
    {
        return $this->postedExpenditureTotal();
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->appropriation - $this->postedExpenditureTotal();
    }

    public function getUtilizationRateAttribute(): float
    {
        $appropriation = (float) $this->appropriation;
        if ($appropriation <= 0) {
            return 0.0;
        }

        return round(($this->postedExpenditureTotal() / $appropriation) * 100, 2);
    }
}
