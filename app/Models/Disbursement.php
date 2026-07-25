<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    protected $fillable = [
        'disbursement_no',
        'expense_id',
        'description',
        'source',
        'pay_to',
        'amount',
        'method',
        'date_encoded',
        'date_approved',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date_encoded' => 'date',
        'date_approved' => 'date',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }
}
