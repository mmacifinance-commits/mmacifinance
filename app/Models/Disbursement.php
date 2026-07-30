<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'remarks',
        'prepared_by_id',
        'released_by_id',
        'submitted_by_id',
        'approved_by_id',
        'rejected_by_id',
        'posted_by_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date_encoded' => 'date',
        'date_approved' => 'date',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    public function auditTrails(): MorphMany
    {
        return $this->morphMany(AuditTrail::class, 'auditable')->latest();
    }
}
