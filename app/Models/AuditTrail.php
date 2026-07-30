<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditTrail extends Model
{
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
}
