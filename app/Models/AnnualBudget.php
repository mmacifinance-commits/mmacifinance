<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AnnualBudget extends Model
{
    protected $fillable = ['ref_no', 'year', 'start_date', 'end_date', 'semester'];

    protected $casts = [
        'year' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $appends = [
        'fiscal_year_label',
        'period_label',
        'fiscal_months',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ref_no)) {
                $count = self::where('year', $model->year)->count() + 1;
                $model->ref_no = sprintf('AB-%d-%04d', $model->year, $count);
            }
        });

        static::saving(function (self $model) {
            if (! $model->start_date && $model->year) {
                $model->start_date = Carbon::create((int) $model->year, 1, 1)->toDateString();
            }
            if (! $model->end_date && $model->year) {
                $model->end_date = Carbon::create((int) $model->year, 12, 31)->toDateString();
            }
            if ($model->start_date) {
                $model->year = Carbon::parse($model->start_date)->year;
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class, 'budget_id');
    }

    public function incomeAllocations(): HasMany
    {
        return $this->hasMany(IncomeAllocation::class);
    }

    public function fiscalStart(): Carbon
    {
        return $this->start_date
            ? Carbon::parse($this->start_date)->startOfDay()
            : Carbon::create($this->year, 1, 1)->startOfDay();
    }

    public function fiscalEnd(): Carbon
    {
        return $this->end_date
            ? Carbon::parse($this->end_date)->startOfDay()
            : Carbon::create($this->year, 12, 31)->startOfDay();
    }

    public function containsDate(CarbonInterface|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        return $date->betweenIncluded($this->fiscalStart(), $this->fiscalEnd());
    }

    public function orderedFiscalMonths(): Collection
    {
        $month = $this->fiscalStart()->startOfMonth();
        $lastMonth = $this->fiscalEnd()->startOfMonth();
        $months = collect();

        while ($month->lessThanOrEqualTo($lastMonth)) {
            $months->push([
                'value' => $month->toDateString(),
                'year' => $month->year,
                'month' => $month->month,
                'label' => $month->format('F Y'),
                'short_label' => $month->format('M Y'),
            ]);
            $month->addMonth();
        }

        return $months;
    }

    public function allocationMonthForNumber(int $month): ?Carbon
    {
        $match = $this->orderedFiscalMonths()->firstWhere('month', $month);

        return $match ? Carbon::parse($match['value']) : null;
    }

    public function getFiscalYearLabelAttribute(): string
    {
        $startYear = $this->fiscalStart()->year;
        $endYear = $this->fiscalEnd()->year;

        return $startYear === $endYear ? "FY {$startYear}" : "FY {$startYear}-{$endYear}";
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->fiscalStart()->format('M j, Y').' - '.$this->fiscalEnd()->format('M j, Y');
    }

    public function getFiscalMonthsAttribute(): array
    {
        return $this->orderedFiscalMonths()->all();
    }

    public function scopeOverlapping(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);
    }
}
