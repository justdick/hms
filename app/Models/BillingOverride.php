<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingOverride extends Model
{
    /** @use HasFactory<\Database\Factories\BillingOverrideFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'patient_checkin_id',
        'charge_id',
        'authorized_by',
        'service_type',
        'reason',
        'status',
        'authorized_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function patientCheckin(): BelongsTo
    {
        return $this->belongsTo(PatientCheckin::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function authorizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->expires_at && $this->expires_at->isPast();
    }

    public function markAsUsed(): void
    {
        $this->update(['status' => self::STATUS_USED]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeUsed($query)
    {
        return $query->where('status', self::STATUS_USED);
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_EXPIRED)
                ->orWhere(function ($q2) {
                    $q2->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                });
        });
    }

    public function scopeForCheckin($query, int $checkinId)
    {
        return $query->where('patient_checkin_id', $checkinId);
    }

    public function scopeForServiceType($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }
}
