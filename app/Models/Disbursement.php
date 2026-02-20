<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    protected $fillable = [
        'disbursement_no',
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
}
