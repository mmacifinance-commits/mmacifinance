<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditTrail extends Model
{
    protected $with = ['user:id,name'];

    protected $hidden = ['user'];

    protected $appends = ['display_user_name', 'record_label'];

    public function getDisplayUserNameAttribute(): string
    {
        return $this->user?->name ?? $this->user_name ?? 'System';
    }

    public function getRecordLabelAttribute(): string
    {
        if (!$this->auditable) return 'System';
        $record = $this->auditable;
        return $record->ref_no ?? $record->receipt_no ?? $record->description
            ?? $record->name ?? ($this->auditable_type.' #'.$this->auditable_id);
    }

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'user_id',
        'user_name',
        'user_role',
        'action',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function log(
        Model $model,
        string $action,
        ?User $user = null,
        ?string $remarks = null,
        ?array $metadata = null
    ): self {
        $currentUser = $user ?? auth()->user();

        return self::create([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'user_id' => $currentUser?->id,
            'user_name' => $currentUser?->name ?? 'System',
            'user_role' => $currentUser?->role_label ?? $currentUser?->role ?? 'System',
            'action' => $action,
            'remarks' => $remarks,
            'metadata' => $metadata,
        ]);
    }

    /** Record a system event that may not belong to a database record. */
    public static function systemLog(
        string $action,
        ?User $user = null,
        ?string $remarks = null,
        ?array $metadata = null
    ): self {
        $currentUser = $user ?? auth()->user();

        return self::create([
            // A non-zero placeholder keeps system/guest events compatible with
            // the original non-nullable polymorphic audit columns.
            'auditable_type' => 'System',
            'auditable_id' => $currentUser?->id ?? 0,
            'user_id' => $currentUser?->id,
            'user_name' => $currentUser?->name ?? 'System/Guest',
            'user_role' => $currentUser?->role_label ?? $currentUser?->role ?? 'Guest',
            'action' => $action,
            'remarks' => $remarks,
            'metadata' => $metadata,
        ]);
    }
}
