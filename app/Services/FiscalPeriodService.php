<?php

namespace App\Services;

use App\Models\AnnualBudget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class FiscalPeriodService
{
    public function all(): Collection
    {
        return AnnualBudget::query()
            ->orderByDesc('start_date')
            ->orderByDesc('year')
            ->get();
    }

    public function resolve(?int $fiscalPeriodId, ?int $legacyYear = null): ?AnnualBudget
    {
        if ($fiscalPeriodId) {
            $period = AnnualBudget::find($fiscalPeriodId);
            if ($period) {
                return $period;
            }
        }

        if ($legacyYear) {
            $period = AnnualBudget::query()->where('year', $legacyYear)->first();
            if ($period) {
                return $period;
            }
        }

        return AnnualBudget::query()
            ->orderByDesc('start_date')
            ->orderByDesc('year')
            ->first();
    }

    public function allocationMonth(AnnualBudget $period, ?string $allocationMonth, ?int $legacyMonth = null): ?string
    {
        if ($allocationMonth) {
            $month = Carbon::parse($allocationMonth)->startOfMonth();

            return $period->containsDate($month) ? $month->toDateString() : null;
        }

        return $legacyMonth ? $period->allocationMonthForNumber($legacyMonth)?->toDateString() : null;
    }

    public function options(Collection $periods): array
    {
        return $periods->map(fn (AnnualBudget $period) => [
            'id' => $period->id,
            'label' => $period->fiscal_year_label,
            'period_label' => $period->period_label,
            'start_date' => $period->fiscalStart()->toDateString(),
            'end_date' => $period->fiscalEnd()->toDateString(),
            'months' => $period->orderedFiscalMonths(),
        ])->values()->all();
    }
}
