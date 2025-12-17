<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PatientAccountService
{
    /**
     * Get or create account for a patient.
     */
    public function getOrCreateAccount(Patient $patient): PatientAccount
    {
        return PatientAccount::getOrCreateForPatient($patient);
    }

    /**
     * Deposit funds into patient account.
     */
    public function deposit(
        Patient $patient,
        float $amount,
        int $paymentMethodId,
        User $processedBy,
        ?string $paymentReference = null,
        ?string $notes = null
    ): AccountTransaction {
        return DB::transaction(function () use ($patient, $amount, $paymentMethodId, $processedBy, $paymentReference, $notes) {
            $account = $this->getOrCreateAccount($patient);
            $balanceBefore = $account->balance;

            $account->balance += $amount;
            $account->save();

            return AccountTransaction::create([
                'patient_account_id' => $account->id,
                'type' => AccountTransaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $account->balance,
                'payment_method_id' => $paymentMethodId,
                'payment_reference' => $paymentReference,
                'description' => 'Deposit',
                'notes' => $notes,
                'processed_by' => $processedBy->id,
                'transacted_at' => now(),
            ]);
        });
    }

    /**
     * Apply account balance to a charge.
     *
     * Only deducts from POSITIVE balance (deposits).
     * Credit limit is NOT auto-deducted - it only allows services to proceed
     * with charges remaining as "owing".
     */
    public function applyToCharge(Charge $charge, ?User $processedBy = null): float
    {
        $patient = $charge->patientCheckin?->patient;
        if (! $patient) {
            return 0;
        }

        $account = PatientAccount::where('patient_id', $patient->id)->first();
        if (! $account || ! $account->is_active) {
            return 0;
        }

        $remainingAmount = $charge->amount - $charge->paid_amount;
        if ($remainingAmount <= 0) {
            return 0;
        }

        // Only deduct from positive balance (deposits), NOT from credit limit
        // Credit limit just allows owing - it's not virtual money to spend
        if ($account->balance <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($account, $charge, $remainingAmount, $processedBy) {
            $account->refresh();
            $account->lockForUpdate();

            // Only deduct up to the positive balance (deposit amount)
            $deductible = min($remainingAmount, max(0, (float) $account->balance));
            if ($deductible <= 0) {
                return 0;
            }

            $balanceBefore = $account->balance;
            $account->balance -= $deductible;
            $account->save();

            // Record transaction
            AccountTransaction::create([
                'patient_account_id' => $account->id,
                'type' => AccountTransaction::TYPE_CHARGE_DEDUCTION,
                'amount' => -$deductible,
                'balance_before' => $balanceBefore,
                'balance_after' => $account->balance,
                'charge_id' => $charge->id,
                'description' => "Charge: {$charge->description}",
                'processed_by' => $processedBy?->id ?? auth()->id(),
                'transacted_at' => now(),
            ]);

            // Update charge
            $charge->paid_amount += $deductible;
            if ($charge->paid_amount >= $charge->amount) {
                $charge->status = 'paid';
                $charge->paid_at = now();
            }
            $charge->save();

            return $deductible;
        });
    }

    /**
     * Check if patient can receive services (has deposit or credit privilege).
     */
    public function canReceiveServices(Patient $patient, float $chargeAmount = 0): bool
    {
        $account = PatientAccount::where('patient_id', $patient->id)->first();

        // No account = no special privileges, follow normal billing rules
        if (! $account || ! $account->is_active) {
            return false;
        }

        // Has positive balance (deposit) - can receive services
        if ($account->balance > 0) {
            return true;
        }

        // Has credit privilege - check if within limit
        if ($account->credit_limit > 0) {
            $currentOwing = abs(min(0, (float) $account->balance));
            $wouldOwe = $currentOwing + $chargeAmount;

            // Allow if total owing would be within credit limit
            return $wouldOwe <= $account->credit_limit;
        }

        return false;
    }

    /**
     * Get remaining credit available (how much more they can owe).
     */
    public function getRemainingCredit(Patient $patient): float
    {
        $account = PatientAccount::where('patient_id', $patient->id)->first();

        if (! $account || $account->credit_limit <= 0) {
            return 0;
        }

        $currentOwing = abs(min(0, (float) $account->balance));

        return max(0, $account->credit_limit - $currentOwing);
    }

    /**
     * Set credit limit for a patient.
     */
    public function setCreditLimit(
        Patient $patient,
        float $creditLimit,
        User $authorizedBy,
        ?string $reason = null
    ): PatientAccount {
        return DB::transaction(function () use ($patient, $creditLimit, $authorizedBy, $reason) {
            $account = $this->getOrCreateAccount($patient);
            $oldLimit = $account->credit_limit;

            $account->update([
                'credit_limit' => $creditLimit,
                'credit_authorized_by' => $creditLimit > 0 ? $authorizedBy->id : null,
                'credit_authorized_at' => $creditLimit > 0 ? now() : null,
                'credit_reason' => $creditLimit > 0 ? $reason : null,
            ]);

            // Record the change
            AccountTransaction::create([
                'patient_account_id' => $account->id,
                'type' => AccountTransaction::TYPE_CREDIT_LIMIT_CHANGE,
                'amount' => 0,
                'balance_before' => $account->balance,
                'balance_after' => $account->balance,
                'description' => "Credit limit changed from {$oldLimit} to {$creditLimit}",
                'notes' => $reason,
                'processed_by' => $authorizedBy->id,
                'transacted_at' => now(),
            ]);

            return $account;
        });
    }

    /**
     * Process a payment to reduce owing balance.
     */
    public function processPayment(
        Patient $patient,
        float $amount,
        int $paymentMethodId,
        User $processedBy,
        ?string $paymentReference = null,
        ?string $notes = null
    ): AccountTransaction {
        return DB::transaction(function () use ($patient, $amount, $paymentMethodId, $processedBy, $paymentReference, $notes) {
            $account = $this->getOrCreateAccount($patient);
            $balanceBefore = $account->balance;

            $account->balance += $amount;
            $account->save();

            return AccountTransaction::create([
                'patient_account_id' => $account->id,
                'type' => AccountTransaction::TYPE_PAYMENT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $account->balance,
                'payment_method_id' => $paymentMethodId,
                'payment_reference' => $paymentReference,
                'description' => 'Payment received',
                'notes' => $notes,
                'processed_by' => $processedBy->id,
                'transacted_at' => now(),
            ]);
        });
    }

    /**
     * Make an adjustment to patient account (positive or negative).
     */
    public function makeAdjustment(
        Patient $patient,
        float $amount,
        User $processedBy,
        ?string $reason = null
    ): AccountTransaction {
        return DB::transaction(function () use ($patient, $amount, $processedBy, $reason) {
            $account = $this->getOrCreateAccount($patient);
            $balanceBefore = $account->balance;

            // For negative adjustments, validate sufficient balance
            if ($amount < 0 && ($account->balance + $amount) < -$account->credit_limit) {
                throw new \InvalidArgumentException('Adjustment would exceed credit limit');
            }

            $account->balance += $amount;
            $account->save();

            $description = $amount >= 0 ? 'Credit adjustment' : 'Debit adjustment';

            return AccountTransaction::create([
                'patient_account_id' => $account->id,
                'type' => AccountTransaction::TYPE_ADJUSTMENT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $account->balance,
                'description' => $description,
                'notes' => $reason,
                'processed_by' => $processedBy->id,
                'transacted_at' => now(),
            ]);
        });
    }

    /**
     * Process a refund from patient account.
     */
    public function processRefund(
        Patient $patient,
        float $amount,
        User $processedBy,
        string $reason
    ): AccountTransaction {
        return DB::transaction(function () use ($patient, $amount, $processedBy, $reason) {
            $account = $this->getOrCreateAccount($patient);

            if ($account->balance < $amount) {
                throw new \InvalidArgumentException('Insufficient balance for refund');
            }

            $balanceBefore = $account->balance;
            $account->balance -= $amount;
            $account->save();

            return AccountTransaction::create([
                'patient_account_id' => $account->id,
                'type' => AccountTransaction::TYPE_REFUND,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $account->balance,
                'description' => 'Refund processed',
                'notes' => $reason,
                'processed_by' => $processedBy->id,
                'transacted_at' => now(),
            ]);
        });
    }

    /**
     * Get account summary for a patient.
     */
    public function getAccountSummary(Patient $patient): array
    {
        $account = PatientAccount::where('patient_id', $patient->id)
            ->with('creditAuthorizedBy')
            ->first();

        if (! $account) {
            return [
                'has_account' => false,
                'balance' => 0,
                'deposit_balance' => 0,
                'credit_limit' => 0,
                'remaining_credit' => 0,
                'amount_owed' => 0,
                'has_credit_privilege' => false,
            ];
        }

        return [
            'has_account' => true,
            'account_number' => $account->account_number,
            'balance' => (float) $account->balance,
            'deposit_balance' => $account->deposit_balance,
            'credit_limit' => (float) $account->credit_limit,
            'remaining_credit' => $account->remaining_credit,
            'amount_owed' => $account->amount_owed,
            'has_credit_privilege' => $account->hasCreditPrivilege(),
            'credit_authorized_by' => $account->creditAuthorizedBy?->name,
            'credit_reason' => $account->credit_reason,
        ];
    }

    /**
     * Get transaction history for a patient.
     */
    public function getTransactionHistory(Patient $patient, int $limit = 50): array
    {
        $account = PatientAccount::where('patient_id', $patient->id)->first();

        if (! $account) {
            return [];
        }

        return $account->transactions()
            ->with(['paymentMethod', 'processedBy', 'charge'])
            ->orderByDesc('transacted_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
