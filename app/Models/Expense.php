<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    protected $fillable = [
        'ref_no',
        'description',
        'category_id',
        'particular_id',
        'amount',
        'paid',
        'date_encoded',
        'date_approved',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid' => 'decimal:2',
        'date_encoded' => 'date',
        'date_approved' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'category_id');
    }

    public function particular(): BelongsTo
    {
        return $this->belongsTo(BudgetParticular::class, 'particular_id');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class, 'expense_id');
    }
}
