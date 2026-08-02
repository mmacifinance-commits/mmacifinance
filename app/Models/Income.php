<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Income extends Model
{
    protected $fillable = [
        'income_no',
        'source',
        'description',
        'amount',
        'date_encoded',
        'notes',
        'created_by_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date_encoded' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(IncomeAllocation::class);
    }

    public function auditTrails(): MorphMany
    {
        return $this->morphMany(AuditTrail::class, 'auditable')->latest();
    }
}
