<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetParticular extends Model
{
    protected $fillable = [
        'category_id',
        'department_id',
        'account_code',
        'account_name',
        'particular',
        'description',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(BudgetItem::class, 'particular_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'particular_id');
    }
}
