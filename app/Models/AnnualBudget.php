<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualBudget extends Model
{
    protected $fillable = ['year', 'semester'];

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class, 'budget_id');
    }
}
