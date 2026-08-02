<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualBudget extends Model
{
    protected $fillable = ['ref_no', 'year', 'semester'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ref_no)) {
                $count = self::where('year', $model->year)->count() + 1;
                $model->ref_no = sprintf('AB-%d-%04d', $model->year, $count);
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
}
