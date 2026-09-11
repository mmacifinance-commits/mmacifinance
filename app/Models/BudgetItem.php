<?php

namespace App\Models;

use App\Services\BudgetUtilizationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BudgetItem extends Model
{
    protected $fillable = [
        'ref_no',
        'budget_id',
        'category_id',
        'particular_id',
        'month',
        'allocation_month',
        'appropriation',
    ];

    protected $casts = [
        'month' => 'integer',
        'allocation_month' => 'date',
        'appropriation' => 'decimal:2',
    ];

    protected $appends = [
        'expenditure',
        'balance',
        'utilization_rate',
        'allocation_month_label',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $model) {
            // Budget allocations must never store an independently edited expenditure.
            // The displayed expenditure is always derived from posted disbursements.
            $model->expenditure = 0;

            if (! $model->budget_id || ! $model->particular_id) {
                return;
            }

            $budget = AnnualBudget::find($model->budget_id);
            $particular = BudgetParticular::with('category', 'department')->find($model->particular_id);

            if (! $budget || ! $particular) {
                return;
            }

            $allocationMonth = $model->allocation_month
                ? Carbon::parse($model->allocation_month)->startOfMonth()
                : $budget->allocationMonthForNumber((int) ($model->month ?: 1));

            if (! $allocationMonth || ! $budget->containsDate($allocationMonth)) {
                throw ValidationException::withMessages([
                    'allocation_month' => "The allocation month must fall within {$budget->fiscal_year_label} ({$budget->period_label}).",
                ]);
            }

            $model->allocation_month = $allocationMonth->toDateString();
            $model->month = $allocationMonth->month;
            $month = $allocationMonth->month;

            $mustValidateReference = ! $model->exists || $model->isDirty('ref_no');
            if ($mustValidateReference && ! empty($model->ref_no) && preg_match('/^MB-(\d{4})-(\d{2})-(\d{4})$/', (string) $model->ref_no, $matches)) {
                $refYear = (int) $matches[1];
                $refMonth = (int) $matches[2];

                if (! in_array($refYear, [(int) $budget->year, $allocationMonth->year], true) || $refMonth !== $month) {
                    throw ValidationException::withMessages([
                        'ref_no' => "The monthly reference number {$model->ref_no} must match allocation month {$allocationMonth->format('F Y')}.",
                    ]);
                }
            }

            $duplicateExists = self::query()
                ->where('budget_id', $model->budget_id)
                ->where('particular_id', $model->particular_id)
                ->whereDate('allocation_month', $allocationMonth->toDateString())
                ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'particular_id' => "A budget record already exists for {$particular->particular} in {$allocationMonth->format('F Y')}.",
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
                $allocationMonth = $model->allocation_month
                    ? Carbon::parse($model->allocation_month)
                    : Carbon::create($year, (int) ($model->month ?: 1), 1);
                $count = self::where('budget_id', $model->budget_id)
                    ->whereDate('allocation_month', $allocationMonth->toDateString())
                    ->count() + 1;
                $model->ref_no = sprintf('MB-%d-%02d-%04d', $allocationMonth->year, $allocationMonth->month, $count);
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
        return $this->hasMany(Expense::class, 'budget_item_id');
    }

    public function postedExpenditureTotal(): float
    {
        if (array_key_exists('derived_expenditure', $this->attributes) && $this->attributes['derived_expenditure'] !== null) {
            return (float) $this->attributes['derived_expenditure'];
        }

        return app(BudgetUtilizationService::class)->expenditureForItem($this);
    }

    public static function hydrateDerivedTotals(Collection $items): void
    {
        app(BudgetUtilizationService::class)->hydrateItems($items);
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

    public function getAllocationMonthLabelAttribute(): string
    {
        if ($this->allocation_month) {
            return Carbon::parse($this->allocation_month)->format('F Y');
        }

        return date('F', mktime(0, 0, 0, (int) ($this->month ?: 1), 1)).' '.$this->budget?->year;
    }
}
