<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'otp_code',
        'otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'otp_expires_at' => 'datetime',
        ];
    }

    // Role constants
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_DISBURSEMENT_OFFICER = 'disbursement_officer';
    const ROLE_BUDGET_OFFICER = 'budget_officer';
    const ROLE_AUDITOR = 'auditor';

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function canManageBudget(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_BUDGET_OFFICER]);
    }

    public function canManageExpenses(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_DISBURSEMENT_OFFICER]);
    }

    public function canManageDisbursements(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_DISBURSEMENT_OFFICER]);
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_DISBURSEMENT_OFFICER => 'Disbursement Officer',
            self::ROLE_BUDGET_OFFICER => 'Budget Monitoring Officer',
            self::ROLE_AUDITOR => 'Auditor',
            default => ucfirst($this->role),
        };
    }
}
