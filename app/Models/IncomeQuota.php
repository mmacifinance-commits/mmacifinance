<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeQuota extends Model
{
    public const OVERALL_SOURCE = '__overall_fiscal_year__';

    protected $fillable = ['annual_budget_id', 'source', 'amount'];
    protected $casts = ['amount' => 'decimal:2'];
}
