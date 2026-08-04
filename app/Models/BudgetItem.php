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

    protected $appends = [
        'expenditure',
        'balance',
        'utilization_rate',
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
        $budgetYear = $this->budgetFiscalYear();

        if ($budgetYear <= 0) {
            return 0.0;
        }

        return $this->postedFiscalYearExpenditureTotal($budgetYear);
    }

    protected function postedFiscalYearExpenditureTotal(int $budgetYear): float
    {
        $postedDisbursements = (float) $this->matchingPostedDisbursements($budgetYear)->sum('amount');
        $postedExpensePaid = (float) $this->matchingPostedExpenses($budgetYear)->sum('paid');
        $postedExpenseAmount = (float) $this->matchingPostedExpenses($budgetYear)->sum('amount');
        $postedExpenses = $postedExpensePaid > 0 ? $postedExpensePaid : $postedExpenseAmount;

        return max($postedDisbursements, $postedExpenses);
    }

    protected function matchingPostedDisbursements(int $budgetYear)
    {
        if ($budgetYear <= 0 || !$this->hasBudgetLineIdentity()) {
            return Disbursement::query()->whereRaw('1 = 0');
        }

        return Disbursement::query()
            ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) LIKE ?', ['%posted%'])
            ->whereHas('expense', function ($expenseQuery) use ($budgetYear) {
                $expenseQuery
                    ->whereYear('date_encoded', $budgetYear)
                    ->where(fn ($matchQuery) => $this->applyBudgetLineIdentityMatch($matchQuery));
            });
    }

    protected function matchingPostedExpenses(int $budgetYear)
    {
        if ($budgetYear <= 0 || !$this->hasBudgetLineIdentity()) {
            return Expense::query()->whereRaw('1 = 0');
        }

        return Expense::query()
            ->whereYear('date_encoded', $budgetYear)
            ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) LIKE ?', ['%posted%'])
            ->where(fn ($matchQuery) => $this->applyBudgetLineIdentityMatch($matchQuery));
    }

    protected function applyBudgetLineIdentityMatch($matchQuery): void
    {
        $budgetParticularId = (int) ($this->particular_id ?? 0);
        $budgetCategoryId = (int) ($this->category_id ?? $this->particular?->category_id ?? 0);
        $budgetDepartmentId = (int) ($this->budgetParticular()?->department_id ?? 0);
        $budgetTitle = $this->normalizedBudgetTitle();
        $budgetCategory = $this->normalizedBudgetCategory();
        $budgetDepartment = $this->normalizedBudgetDepartment();

        if ($budgetParticularId > 0) {
            $matchQuery->orWhere('particular_id', $budgetParticularId);
        }

        if ($budgetTitle !== '') {
            $matchQuery
                ->orWhereHas('particular', function ($particularQuery) use ($budgetTitle) {
                    $particularQuery
                        ->whereRaw('LOWER(TRIM(COALESCE(NULLIF(particular, ""), NULLIF(account_name, ""), ""))) = ?', [$budgetTitle])
                        ->orWhereRaw('LOWER(TRIM(COALESCE(NULLIF(particular, ""), NULLIF(account_name, ""), ""))) LIKE ?', ['%' . $budgetTitle . '%']);
                })
                ->orWhereRaw('LOWER(TRIM(COALESCE(description, ""))) = ?', [$budgetTitle])
                ->orWhereRaw('LOWER(TRIM(COALESCE(description, ""))) LIKE ?', ['%' . $budgetTitle . '%']);
        }

        if ($budgetCategoryId > 0 && $budgetDepartmentId > 0) {
            $matchQuery->orWhere(function ($comboQuery) use ($budgetCategoryId, $budgetDepartmentId) {
                $comboQuery
                    ->where('category_id', $budgetCategoryId)
                    ->whereHas('particular', fn ($particularQuery) => $particularQuery->where('department_id', $budgetDepartmentId));
            });
        }

        if ($budgetCategory !== '' && $budgetDepartment !== '') {
            $matchQuery->orWhere(function ($comboQuery) use ($budgetCategory, $budgetDepartment) {
                $comboQuery
                    ->whereHas('category', fn ($categoryQuery) => $categoryQuery->whereRaw('LOWER(TRIM(name)) = ?', [$budgetCategory]))
                    ->whereHas('particular.department', fn ($departmentQuery) => $departmentQuery->whereRaw('LOWER(TRIM(name)) = ?', [$budgetDepartment]));
            });
        }
    }

    protected function hasBudgetLineIdentity(): bool
    {
        return (int) ($this->particular_id ?? 0) > 0
            || $this->normalizedBudgetTitle() !== ''
            || ((int) ($this->category_id ?? 0) > 0 && (int) ($this->budgetParticular()?->department_id ?? 0) > 0);
    }

    protected function budgetFiscalYear(): int
    {
        if ($this->relationLoaded('budget') && $this->budget) {
            return (int) $this->budget->year;
        }

        if ($this->budget_id) {
            return (int) AnnualBudget::query()
                ->whereKey($this->budget_id)
                ->value('year');
        }

        return 0;
    }

    protected function normalizedBudgetTitle(): string
    {
        $particular = $this->budgetParticular();
        $title = trim((string) ($particular?->particular ?? '')) !== ''
            ? $particular?->particular
            : $particular?->account_name;

        return Str::of((string) ($title ?? ''))
            ->lower()
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->toString();
    }

    protected function normalizedBudgetCategory(): string
    {
        $category = $this->relationLoaded('category')
            ? $this->category
            : ($this->category_id ? BudgetCategory::query()->find($this->category_id) : null);

        return Str::of((string) ($category?->name ?? ''))
            ->lower()
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->toString();
    }

    protected function normalizedBudgetDepartment(): string
    {
        $particular = $this->budgetParticular();
        $department = $particular?->relationLoaded('department')
            ? $particular->department
            : $particular?->department()->first();

        return Str::of((string) ($department?->name ?? ''))
            ->lower()
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->toString();
    }

    protected function budgetParticular(): ?BudgetParticular
    {
        return $this->relationLoaded('particular')
            ? $this->particular
            : ($this->particular_id ? BudgetParticular::with('department')->find($this->particular_id) : null);
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


