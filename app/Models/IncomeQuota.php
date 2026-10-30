<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeQuota extends Model
{
    protected $fillable = ['annual_budget_id', 'source', 'amount'];
    protected $casts = ['amount' => 'decimal:2'];
}
