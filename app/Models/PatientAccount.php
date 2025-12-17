<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'account_number',
        'balance',
        'credit_limit',
        'credit_authorized_by',
        'credit_authorized_at',
        'credit_reason',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'credit_authorized_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PatientAccount $account) {
            if (empty($account->account_number)) {
                $account->account_number = static::generateAccountNumber();
            }
        });
    }

    public static function generateAccountNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "ACC{$year}";

        $lastAccount = static::where('account_number', 'like', "{$prefix}%")
            ->orderByDesc('account_number')
            ->first();

        if ($lastAccount) {
            $lastNumber = (int) substr($lastAccount->account_number, -6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creditAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_authorized_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }

    /**
     * Get deposit balance (only positive balance, for display).
     */
    public function getDepositBalanceAttribute(): float
    {
        return max(0, (float) $this->balance);
    }

    /**
     * Get the amount owed (absolute value of negative balance).
     */
    public function getAmountOwedAttribute(): float
    {
        return $this->balance < 0 ? abs((float) $this->balance) : 0;
    }

    /**
     * Get remaining credit available (credit limit minus current owing).
     */
    public function getRemainingCreditAttribute(): float
    {
        if ($this->credit_limit <= 0) {
            return 0;
        }

        return max(0, (float) $this->credit_limit - $this->amount_owed);
    }

    /**
     * Check if account is in debt (negative balance).
     */
    public function isInDebt(): bool
    {
        return $this->balance < 0;
    }

    /**
     * Check if patient has credit privileges.
     */
    public function hasCreditPrivilege(): bool
    {
        return $this->credit_limit > 0;
    }

    /**
     * Check if patient can receive services worth a given amount.
     * Returns true if they have deposit OR credit available.
     */
    public function canReceiveServices(float $amount = 0): bool
    {
        // Has positive deposit balance
        if ($this->balance > 0) {
            return true;
        }

        // Has credit privilege and within limit
        if ($this->credit_limit > 0) {
            $wouldOwe = $this->amount_owed + $amount;

            return $wouldOwe <= $this->credit_limit;
        }

        return false;
    }

    /**
     * Check if account has sufficient funds for an amount.
     * Only checks deposit balance, not credit.
     */
    public function hasSufficientFunds(float $amount): bool
    {
        return $this->deposit_balance >= $amount;
    }

    /**
     * Get or create account for a patient.
     */
    public static function getOrCreateForPatient(Patient $patient): self
    {
        return static::firstOrCreate(
            ['patient_id' => $patient->id],
            ['balance' => 0, 'credit_limit' => 0]
        );
    }
}
