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
        'failed_login_attempts',
        'lockout_level',
        'locked_until',
        'otp_sent_at',
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
            'locked_until' => 'datetime',
            'otp_sent_at' => 'datetime',
        ];
    }

    // Role constants
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_DISBURSEMENT_OFFICER = 'disbursement_officer';
    const ROLE_BUDGET_OFFICER = 'budget_officer';
    const ROLE_AUDITOR = 'auditor';
    const ROLE_CASHIER = 'cashier';

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isCashier(): bool
    {
        return $this->role === self::ROLE_CASHIER;
    }

    public function canManageBudget(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_BUDGET_OFFICER]);
    }

    public function canManageIncome(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function canManageExpenses(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_DISBURSEMENT_OFFICER, self::ROLE_CASHIER]);
    }

    public function canSubmitExpenses(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_DISBURSEMENT_OFFICER, self::ROLE_CASHIER]);
    }

    public function canManageDisbursements(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_DISBURSEMENT_OFFICER, self::ROLE_CASHIER]);
    }

    public function canApproveDisbursements(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function canPostDisbursements(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Head of Finance',
            self::ROLE_CASHIER => 'Cashier',
            self::ROLE_DISBURSEMENT_OFFICER => 'Disbursement Officer',
            self::ROLE_BUDGET_OFFICER => 'Budget Monitoring Officer',
            self::ROLE_AUDITOR => 'Auditor',
            default => ucfirst($this->role),
        };
    }
}
