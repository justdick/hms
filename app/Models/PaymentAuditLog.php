<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentAuditLogFactory> */
    use HasFactory;

    public const ACTION_PAYMENT = 'payment';

    public const ACTION_VOID = 'void';

    public const ACTION_REFUND = 'refund';

    public const ACTION_RECEIPT_PRINTED = 'receipt_printed';

    public const ACTION_STATEMENT_GENERATED = 'statement_generated';

    public const ACTION_OVERRIDE = 'override';

    public const ACTION_CREDIT_TAG_ADDED = 'credit_tag_added';

    public const ACTION_CREDIT_TAG_REMOVED = 'credit_tag_removed';

    protected $fillable = [
        'charge_id',
        'patient_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'json',
            'new_values' => 'json',
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForCharge($query, int $chargeId)
    {
        return $query->where('charge_id', $chargeId);
    }

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopePayments($query)
    {
        return $query->where('action', self::ACTION_PAYMENT);
    }

    public function scopeVoids($query)
    {
        return $query->where('action', self::ACTION_VOID);
    }

    public function scopeRefunds($query)
    {
        return $query->where('action', self::ACTION_REFUND);
    }

    public function scopeOverrides($query)
    {
        return $query->where('action', self::ACTION_OVERRIDE);
    }

    public function scopeCreditTagChanges($query)
    {
        return $query->whereIn('action', [self::ACTION_CREDIT_TAG_ADDED, self::ACTION_CREDIT_TAG_REMOVED]);
    }

    /**
     * Create a payment audit log entry.
     */
    public static function logPayment(Charge $charge, User $user, array $newValues, ?string $ipAddress = null): self
    {
        return self::create([
            'charge_id' => $charge->id,
            'patient_id' => $charge->patientCheckin?->patient_id,
            'user_id' => $user->id,
            'action' => self::ACTION_PAYMENT,
            'old_values' => [
                'status' => $charge->getOriginal('status'),
                'paid_amount' => $charge->getOriginal('paid_amount'),
            ],
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Create a receipt printed audit log entry.
     */
    public static function logReceiptPrinted(Charge $charge, User $user, ?string $ipAddress = null): self
    {
        return self::create([
            'charge_id' => $charge->id,
            'patient_id' => $charge->patientCheckin?->patient_id,
            'user_id' => $user->id,
            'action' => self::ACTION_RECEIPT_PRINTED,
            'new_values' => [
                'receipt_number' => $charge->receipt_number,
                'printed_at' => now()->toIso8601String(),
            ],
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Create an override audit log entry.
     */
    public static function logOverride(Charge $charge, User $user, string $reason, ?string $ipAddress = null): self
    {
        return self::create([
            'charge_id' => $charge->id,
            'patient_id' => $charge->patientCheckin?->patient_id,
            'user_id' => $user->id,
            'action' => self::ACTION_OVERRIDE,
            'old_values' => [
                'status' => $charge->getOriginal('status'),
            ],
            'new_values' => [
                'status' => 'owing',
            ],
            'reason' => $reason,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Create a credit tag change audit log entry.
     */
    public static function logCreditTagChange(Patient $patient, User $user, bool $added, string $reason, ?string $ipAddress = null): self
    {
        return self::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'action' => $added ? self::ACTION_CREDIT_TAG_ADDED : self::ACTION_CREDIT_TAG_REMOVED,
            'new_values' => [
                'is_credit_eligible' => $added,
            ],
            'reason' => $reason,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Create a void audit log entry.
     */
    public static function logVoid(Charge $charge, User $user, string $reason, ?string $ipAddress = null): self
    {
        return self::create([
            'charge_id' => $charge->id,
            'patient_id' => $charge->patientCheckin?->patient_id,
            'user_id' => $user->id,
            'action' => self::ACTION_VOID,
            'old_values' => [
                'status' => $charge->getOriginal('status'),
                'paid_amount' => $charge->getOriginal('paid_amount'),
            ],
            'new_values' => [
                'status' => 'voided',
                'voided_at' => now()->toIso8601String(),
            ],
            'reason' => $reason,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Create a refund audit log entry.
     */
    public static function logRefund(Charge $charge, User $user, float $refundAmount, string $reason, ?string $ipAddress = null): self
    {
        return self::create([
            'charge_id' => $charge->id,
            'patient_id' => $charge->patientCheckin?->patient_id,
            'user_id' => $user->id,
            'action' => self::ACTION_REFUND,
            'old_values' => [
                'status' => $charge->getOriginal('status'),
                'paid_amount' => $charge->getOriginal('paid_amount'),
            ],
            'new_values' => [
                'status' => 'refunded',
                'refund_amount' => $refundAmount,
                'refunded_at' => now()->toIso8601String(),
            ],
            'reason' => $reason,
            'ip_address' => $ipAddress,
        ]);
    }
}
